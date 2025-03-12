<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// TEMPORAL: Forzar reinstalación (ELIMINAR EN PRODUCCIÓN)
if (isset($_GET['force_reinstall'])) {
    if (file_exists('config/config.php')) {
        unlink('config/config.php');
    }
    if (file_exists('config/database.php')) {
        unlink('config/database.php');
    }
    if (file_exists('config/mail.php')) {
        unlink('config/mail.php');
    }
    if (file_exists('config/app.php')) {
        unlink('config/app.php');
    }
    if (is_dir('config')) {
        rmdir('config');
    }
    header('Location: install.php');
    exit;
}

// Verificar si ya está instalado - NUEVA VERSIÓN
function isInstalled() {
    // Verificar si existe el archivo de configuración
    if (!file_exists('config/config.php')) {
        return false;
    }

    // Verificar si podemos leer el archivo
    if (!is_readable('config/config.php')) {
        return false;
    }

    try {
        // Intentar incluir el archivo de configuración
        include 'config/config.php';
        
        // Verificar si las constantes básicas están definidas
        if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER')) {
            return false;
        }

        // Intentar conectar a la base de datos
        $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4";
        $db = new PDO($dsn, DB_USER, DB_PASS);
        
        // Verificar si existe la tabla de usuarios
        $stmt = $db->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() == 0) {
            return false;
        }

        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Solo verificar si está instalado en el primer paso
if ($step === 1 && isInstalled()) {
    die('El sistema ya está instalado. Por seguridad, elimina el archivo install.php');
}

// Verificar requisitos del sistema
function checkSystemRequirements() {
    $requirements = [
        'php' => ['required' => '8.0.0', 'current' => PHP_VERSION],
        'extensions' => [
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'gd' => extension_loaded('gd'),
            'curl' => extension_loaded('curl'),
            'fileinfo' => extension_loaded('fileinfo'),
            'openssl' => extension_loaded('openssl')
        ],
        'writable_dirs' => [
            'cache' => is_writable('cache') || @mkdir('cache', 0777),
            'assets/uploads' => is_writable('assets/uploads') || @mkdir('assets/uploads', 0777, true),
            'logs' => is_writable('logs') || @mkdir('logs', 0777),
            'config' => is_writable('config') || @mkdir('config', 0777) // Agregado directorio config
        ]
    ];

    return $requirements;
}

// Probar conexión a base de datos
function testDatabaseConnection($host, $name, $user, $pass) {
    try {
        // Intentar conectar directamente a la base de datos existente
        $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
        $db = new PDO($dsn, $user, $pass);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Verificar si la base de datos está vacía
        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($tables)) {
            return "La base de datos no está vacía. Por favor:\n" .
                   "1. Usa una base de datos vacía\n" .
                   "2. O elimina las tablas existentes primero";
        }

        // Verificar permisos del usuario
        $permissions = [
            'SELECT' => false,
            'INSERT' => false,
            'UPDATE' => false,
            'DELETE' => false,
            'CREATE' => false,
            'ALTER' => false
        ];

        try {
            // Probar SELECT
            $db->query("SELECT 1");
            $permissions['SELECT'] = true;

            // Probar CREATE TABLE
            $db->query("CREATE TABLE test_permissions (id INT)");
            $permissions['CREATE'] = true;

            // Probar INSERT
            $db->query("INSERT INTO test_permissions VALUES (1)");
            $permissions['INSERT'] = true;

            // Probar UPDATE
            $db->query("UPDATE test_permissions SET id = 2");
            $permissions['UPDATE'] = true;

            // Probar DELETE
            $db->query("DELETE FROM test_permissions");
            $permissions['DELETE'] = true;

            // Probar ALTER
            $db->query("ALTER TABLE test_permissions ADD COLUMN test VARCHAR(10)");
            $permissions['ALTER'] = true;

            // Limpiar tabla de prueba
            $db->query("DROP TABLE IF EXISTS test_permissions");

        } catch (PDOException $e) {
            // Si alguna prueba falla, limpiar la tabla de prueba
            $db->query("DROP TABLE IF EXISTS test_permissions");
        }

        // Verificar si faltan permisos
        $missingPermissions = array_filter($permissions, function($hasPermission) {
            return !$hasPermission;
        });

        if (!empty($missingPermissions)) {
            return "El usuario no tiene todos los privilegios necesarios. Faltan:\n" .
                   implode(", ", array_keys($missingPermissions)) . "\n\n" .
                   "Por favor, asigna TODOS los privilegios al usuario en cPanel > MySQL Databases > Add User To Database";
        }

        return true;
    } catch (PDOException $e) {
        $error = $e->getMessage();
        
        if (strpos($error, 'Access denied') !== false) {
            return "Error de acceso. Por favor:\n" .
                   "1. Ve a cPanel > MySQL Databases\n" .
                   "2. Verifica que la base de datos exista\n" .
                   "3. Asegúrate de usar el nombre completo (incluyendo el prefijo del cPanel)\n" .
                   "4. Asigna TODOS los privilegios al usuario\n\n" .
                   "Ejemplo:\n" .
                   "- Base de datos: usuario_nombrebd\n" .
                   "- Usuario: usuario_nombreusuario";
        }
        
        if (strpos($error, "Unknown database") !== false) {
            return "La base de datos no existe. Por favor:\n" .
                   "1. Ve a cPanel > MySQL Databases\n" .
                   "2. Crea una nueva base de datos\n" .
                   "3. Recuerda usar el nombre completo con el prefijo";
        }

        return "Error de conexión: $error";
    }
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 2:
            $db_host = trim($_POST['db_host'] ?? '');
            $db_name = trim($_POST['db_name'] ?? '');
            $db_user = trim($_POST['db_user'] ?? '');
            $db_pass = $_POST['db_pass'] ?? '';

            if (empty($db_host) || empty($db_name) || empty($db_user)) {
                $error = "Todos los campos son requeridos";
            } else {
                // Validar formato de los datos
                if (!filter_var($db_host, FILTER_VALIDATE_DOMAIN) && $db_host !== 'localhost') {
                    $error = "El host no tiene un formato válido";
                } else if (!preg_match('/^[a-zA-Z0-9_]+$/', $db_name)) {
                    $error = "El nombre de la base de datos solo puede contener letras, números y guiones bajos";
                } else {
                    $db_test = testDatabaseConnection($db_host, $db_name, $db_user, $db_pass);
                    if ($db_test === true) {
                        $_SESSION['db_config'] = [
                            'host' => $db_host,
                            'name' => $db_name,
                            'user' => $db_user,
                            'pass' => $db_pass
                        ];
                        header('Location: install.php?step=3');
                        exit;
                    } else {
                        $error = $db_test;
                    }
                }
            }
            break;

        case 3:
            $site_name = trim($_POST['site_name'] ?? '');
            $site_url = trim($_POST['site_url'] ?? '');
            $admin_email = trim($_POST['admin_email'] ?? '');
            $admin_password = $_POST['admin_password'] ?? '';

            if (empty($site_name) || empty($site_url) || empty($admin_email) || empty($admin_password)) {
                $error = "Todos los campos son requeridos";
            } else {
                $_SESSION['site_config'] = [
                    'name' => $site_name,
                    'url' => rtrim($site_url, '/'),
                    'email' => $admin_email,
                    'password' => password_hash($admin_password, PASSWORD_DEFAULT)
                ];
                header('Location: install.php?step=4');
                exit;
            }
            break;

        case 4:
            try {
                if (!isset($_SESSION['db_config']) || !isset($_SESSION['site_config'])) {
                    throw new Exception("Información de configuración incompleta");
                }

                // Crear directorio config si no existe
                if (!file_exists('config')) {
                    mkdir('config', 0755, true);
                }
                
                // Configuración principal
                $config_content = "<?php
// Configuración de Base de Datos
define('DB_HOST', '{$_SESSION['db_config']['host']}');
define('DB_NAME', '{$_SESSION['db_config']['name']}');
define('DB_USER', '{$_SESSION['db_config']['user']}');
define('DB_PASS', '{$_SESSION['db_config']['pass']}');

// Configuración del Sitio
define('SITE_NAME', '{$_SESSION['site_config']['name']}');
define('BASE_URL', '{$_SESSION['site_config']['url']}');
define('ADMIN_EMAIL', '{$_SESSION['site_config']['email']}');

// Configuración de Correo
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', '587');
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM', '{$_SESSION['site_config']['email']}');

// Configuración de Pagos
define('PAYPAL_CLIENT_ID', '');
define('PAYPAL_SECRET', '');
define('STRIPE_PUBLIC_KEY', '');
define('STRIPE_SECRET_KEY', '');

// Configuración de Seguridad
define('CSRF_TOKEN_SECRET', '".bin2hex(random_bytes(32))."');
define('SESSION_LIFETIME', 7200);
define('COOKIE_LIFETIME', 604800);

// Configuración de Archivos
define('MAX_UPLOAD_SIZE', 5242880); // 5MB
define('ALLOWED_FILE_TYPES', 'jpg,jpeg,png,gif,pdf,doc,docx');
define('UPLOAD_PATH', 'assets/uploads/');

// Configuración de Cache
define('CACHE_ENABLED', true);
define('CACHE_PATH', 'cache/');
define('CACHE_LIFETIME', 3600);

// Zona horaria
date_default_timezone_set('America/Mexico_City');

// Modo debug
define('DEBUG_MODE', false);
";
                
                if (!file_put_contents('config/config.php', $config_content)) {
                    throw new Exception("No se pudo escribir el archivo de configuración principal");
                }

                // Crear archivo database.php
                $database_content = "<?php
return [
    'host' => '{$_SESSION['db_config']['host']}',
    'name' => '{$_SESSION['db_config']['name']}',
    'user' => '{$_SESSION['db_config']['user']}',
    'pass' => '{$_SESSION['db_config']['pass']}',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
";
                if (!file_put_contents('config/database.php', $database_content)) {
                    throw new Exception("No se pudo escribir la configuración de la base de datos");
                }

                // Crear archivo mail.php
                $mail_content = "<?php
return [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'username' => '',
    'password' => '',
    'from' => '{$_SESSION['site_config']['email']}',
    'from_name' => '{$_SESSION['site_config']['name']}',
    'encryption' => 'tls'
];
";
                if (!file_put_contents('config/mail.php', $mail_content)) {
                    throw new Exception("No se pudo escribir la configuración del correo");
                }

                // Crear archivo app.php
                $app_content = "<?php
return [
    'name' => '{$_SESSION['site_config']['name']}',
    'url' => '{$_SESSION['site_config']['url']}',
    'admin_email' => '{$_SESSION['site_config']['email']}',
    'timezone' => 'America/Mexico_City',
    'locale' => 'es',
    'debug' => false,
    'maintenance_mode' => false,
    'maintenance_message' => 'El sitio está en mantenimiento. Volveremos pronto.',
    'session_lifetime' => 7200,
    'cookie_lifetime' => 604800,
    'max_upload_size' => 5242880,
    'allowed_file_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
    'upload_path' => 'assets/uploads/',
    'cache_enabled' => true,
    'cache_path' => 'cache/',
    'cache_lifetime' => 3600
];
";
                if (!file_put_contents('config/app.php', $app_content)) {
                    throw new Exception("No se pudo escribir la configuración de la aplicación");
                }

                // Importar base de datos
                try {
                    $db = new PDO(
                        "mysql:host={$_SESSION['db_config']['host']};dbname={$_SESSION['db_config']['name']};charset=utf8mb4",
                        $_SESSION['db_config']['user'],
                        $_SESSION['db_config']['pass']
                    );
                    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // Leer el archivo SQL
                    $sql = file_get_contents('database/schema.sql');
                    
                    // Eliminar cualquier sentencia CREATE DATABASE o USE
                    $sql = preg_replace('/CREATE\s+DATABASE.*?;/is', '', $sql);
                    $sql = preg_replace('/USE\s+`?\w+`?;/i', '', $sql);
                    
                    // Dividir en consultas individuales
                    $queries = array_filter(
                        array_map(
                            'trim',
                            explode(';', $sql)
                        )
                    );

                    // Ejecutar cada consulta
                    foreach ($queries as $query) {
                        if (!empty($query)) {
                            try {
                                $db->exec($query);
                            } catch (PDOException $e) {
                                // Ignorar errores de "tabla ya existe"
                                if ($e->getCode() != '42S01') {
                                    throw new Exception(
                                        "Error en consulta: " . substr($query, 0, 100) . "...\n" . 
                                        "Código: " . $e->getCode() . "\n" . 
                                        "Error: " . $e->getMessage()
                                    );
                                }
                            }
                        }
                    }

                    // Crear usuario administrador
                    $stmt = $db->prepare("
                        INSERT INTO users (name, email, password, role, created_at) 
                        VALUES ('Administrator', ?, ?, 'admin', NOW())
                    ");
                    $stmt->execute([
                        $_SESSION['site_config']['email'],
                        $_SESSION['site_config']['password']
                    ]);

                } catch (PDOException $e) {
                    throw new Exception(
                        "Error en la base de datos. Verifica que:\n" .
                        "1. El usuario tenga TODOS los privilegios en la base de datos\n" .
                        "2. La base de datos esté vacía\n" .
                        "3. Las credenciales sean correctas\n\n" .
                        "Error específico: " . $e->getMessage()
                    );
                }

                // Limpiar sesión
                session_destroy();
                
                $success = "¡Instalación completada con éxito!";
            } catch (Exception $e) {
                $error = "Error durante la instalación: " . $e->getMessage();
            }
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación - Sistema de Cursos</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #FF4B4B;
            --secondary-color: #2D3958;
            --text-color: #6E7485;
            --border-color: #E5E8ED;
            --success-color: #4CAF50;
            --error-color: #ff3333;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            background: #f7f8fb;
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 10px;
            padding: 40px;
            width: 100%;
            max-width: 800px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }

        .steps::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--border-color);
            transform: translateY(-50%);
            z-index: 1;
        }

        .step {
            background: white;
            padding: 10px 20px;
            border-radius: 20px;
            color: var(--text-color);
            position: relative;
            z-index: 2;
            border: 2px solid var(--border-color);
        }

        .step.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        h2 {
            color: var(--secondary-color);
            margin-bottom: 30px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--secondary-color);
            font-weight: 500;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .btn {
            background: var(--primary-color);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: transform 0.3s;
            display: inline-block;
            text-decoration: none;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .error {
            color: var(--error-color);
            margin-bottom: 20px;
            padding: 10px;
            border-radius: 5px;
            background: rgba(255,51,51,0.1);
        }

        .success {
            color: var(--success-color);
            margin-bottom: 20px;
            padding: 10px;
            border-radius: 5px;
            background: rgba(76,175,80,0.1);
        }

        .requirements {
            list-style: none;
            padding: 0;
        }

        .requirements li {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .requirements .status {
            font-weight: 500;
        }

        .requirements .status.success {
            color: var(--success-color);
            background: none;
            padding: 0;
        }

        .requirements .status.error {
            color: var(--error-color);
            background: none;
            padding: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="steps">
            <div class="step <?php echo $step === 1 ? 'active' : ''; ?>">1. Requisitos</div>
            <div class="step <?php echo $step === 2 ? 'active' : ''; ?>">2. Base de Datos</div>
            <div class="step <?php echo $step === 3 ? 'active' : ''; ?>">3. Configuración</div>
            <div class="step <?php echo $step === 4 ? 'active' : ''; ?>">4. Finalizar</div>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php switch ($step):
            case 1:
                $requirements = checkSystemRequirements();
                $can_continue = true;
                ?>
                <h2>Verificación de Requisitos</h2>
                <ul class="requirements">
                    <li>
                        PHP Version (>= 8.0.0)
                        <span class="status <?php echo version_compare(PHP_VERSION, '8.0.0', '>=') ? 'success' : 'error'; ?>">
                            <?php echo PHP_VERSION; ?>
                        </span>
                    </li>
                    <?php foreach ($requirements['extensions'] as $ext => $loaded): 
                        if (!$loaded) $can_continue = false;
                    ?>
                        <li>
                            Extensión <?php echo strtoupper($ext); ?>
                            <span class="status <?php echo $loaded ? 'success' : 'error'; ?>">
                                <?php echo $loaded ? '✓' : '✗'; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                    <?php foreach ($requirements['writable_dirs'] as $dir => $writable): 
                        if (!$writable) $can_continue = false;
                    ?>
                        <li>
                            Directorio <?php echo $dir; ?>
                            <span class="status <?php echo $writable ? 'success' : 'error'; ?>">
                                <?php echo $writable ? '✓' : '✗'; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($can_continue): ?>
                    <form method="get" style="text-align: center; margin-top: 30px;">
                        <input type="hidden" name="step" value="2">
                        <button type="submit" class="btn">Continuar</button>
                    </form>
                <?php endif; ?>
                <?php break;

            case 2: ?>
                <h2>Configuración de Base de Datos</h2>
                <form method="post">
                    <div class="form-group">
                        <label>Host</label>
                        <input type="text" name="db_host" value="localhost" required>
                    </div>
                    <div class="form-group">
                        <label>Nombre de Base de Datos</label>
                        <input type="text" name="db_name" required>
                    </div>
                    <div class="form-group">
                        <label>Usuario</label>
                        <input type="text" name="db_user" required>
                    </div>
                    <div class="form-group">
                        <label>Contraseña</label>
                        <input type="password" name="db_pass" required>
                    </div>
                    <div style="text-align: center;">
                        <button type="submit" class="btn">Probar Conexión y Continuar</button>
                    </div>
                </form>
                <?php break;

            case 3: ?>
                <h2>Configuración del Sitio</h2>
                <form method="post">
                    <div class="form-group">
                        <label>Nombre del Sitio</label>
                        <input type="text" name="site_name" required>
                    </div>
                    <div class="form-group">
                        <label>URL del Sitio</label>
                        <input type="text" name="site_url" value="<?php echo 'http://'.$_SERVER['HTTP_HOST']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email del Administrador</label>
                        <input type="email" name="admin_email" required>
                    </div>
                    <div class="form-group">
                        <label>Contraseña del Administrador</label>
                        <input type="password" name="admin_password" required>
                    </div>
                    <div style="text-align: center;">
                        <button type="submit" class="btn">Continuar</button>
                    </div>
                </form>
                <?php break;

            case 4: ?>
                <h2>Instalación Final</h2>
                <?php if (!$success): ?>
                    <form method="post" style="text-align: center;">
                        <p style="margin-bottom: 30px;">Todo está listo para la instalación final. Haz clic en el botón para comenzar.</p>
                        <button type="submit" class="btn">Instalar</button>
                    </form>
                <?php else: ?>
                    <div style="text-align: center;">
                        <p style="margin-bottom: 30px;">La instalación se ha completado con éxito. Por favor:</p>
                        <ol style="text-align: left; margin-bottom: 30px;">
                            <li>Elimina el archivo install.php</li>
                            <li>Accede al panel de administración</li>
                            <li>Configura los métodos de pago y correo</li>
                        </ol>
                        <?php
                        $base_url = rtrim($_SESSION['site_config']['url'], '/');
                        // Determinar la ubicación correcta del archivo login.php
                        $login_path = file_exists('auth/login.php') ? 'auth/login.php' : 'login.php';
                        ?>
                        <a href="<?php echo $base_url . '/' . $login_path; ?>" class="btn">Ir al Login</a>
                    </div>
                <?php endif; ?>
                <?php break;
        endswitch; ?>
    </div>
</body>
</html> 
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
                break;
            }

            // Probar la conexión
            $test_result = testDatabaseConnection($db_host, $db_name, $db_user, $db_pass);
            if ($test_result !== true) {
                $error = $test_result;
                break;
            }

            // Guardar la configuración en la sesión
            $_SESSION['db_config'] = [
                'host' => $db_host,
                'name' => $db_name,
                'user' => $db_user,
                'pass' => $db_pass
            ];

            // Avanzar al siguiente paso
            header('Location: install.php?step=3');
            exit;

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
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    // Crear directorio config si no existe
                    if (!file_exists('config')) {
                        if (!mkdir('config', 0755, true)) {
                            throw new Exception("No se pudo crear el directorio config");
                        }
                    }

                    // Verificar permisos del directorio config
                    if (!is_writable('config')) {
                        chmod('config', 0755);
                        if (!is_writable('config')) {
                            throw new Exception("El directorio config no tiene permisos de escritura");
                        }
                    }

                    // Verificar que tenemos los datos necesarios
                    if (!isset($_SESSION['db_config']) || 
                        !isset($_SESSION['site_config'])) {
                        throw new Exception("Faltan datos de configuración. Por favor, vuelve a empezar la instalación.");
                    }

                    // Configuración principal (config.php)
                    $config_content = "<?php
// Configuración de Base de Datos
define('DB_HOST', '{$_SESSION['db_config']['host']}');
define('DB_NAME', '{$_SESSION['db_config']['name']}');
define('DB_USER', '{$_SESSION['db_config']['user']}');
define('DB_PASS', '{$_SESSION['db_config']['pass']}');

// Configuración del Sitio
define('SITE_NAME', '{$_SESSION['site_config']['name']}');
define('BASE_URL', '{$_SESSION['site_config']['url']}');

// Configuración de correo
define('MAIL_HOST', 'smtp.example.com');
define('MAIL_PORT', '587');
define('MAIL_USERNAME', 'your_email@example.com');
define('MAIL_PASSWORD', 'your_password');
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_FROM_ADDRESS', '{$_SESSION['site_config']['email']}');
define('MAIL_FROM_NAME', '{$_SESSION['site_config']['name']}');

// Configuración de pagos
define('STRIPE_PUBLIC_KEY', 'your_public_key');
define('STRIPE_SECRET_KEY', 'your_secret_key');
define('STRIPE_WEBHOOK_SECRET', 'your_webhook_secret');

// Directorios
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('CACHE_DIR', __DIR__ . '/cache');
define('LOG_DIR', __DIR__ . '/logs');
";
                    // Escribir config.php
                    if (file_put_contents('config/config.php', $config_content) === false) {
                        throw new Exception("No se pudo escribir el archivo config.php");
                    }

                    // Verificar que el archivo se creó correctamente
                    if (!file_exists('config/config.php')) {
                        throw new Exception("El archivo config.php no se creó");
                    }

                    // Verificar que el archivo es legible
                    if (!is_readable('config/config.php')) {
                        throw new Exception("El archivo config.php no es legible");
                    }

                    // Verificar el contenido del archivo
                    $written_content = file_get_contents('config/config.php');
                    if (empty($written_content)) {
                        throw new Exception("El archivo config.php está vacío");
                    }

                    // Intentar incluir el archivo para verificar la sintaxis
                    try {
                        require 'config/config.php';
                    } catch (Error $e) {
                        throw new Exception("Error en la sintaxis de config.php: " . $e->getMessage());
                    }

                    // Verificar que las constantes se definieron
                    if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
                        throw new Exception("Las constantes de base de datos no se definieron correctamente");
                    }

                    // Intentar conectar a la base de datos
                    try {
                        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                        $db = new PDO($dsn, DB_USER, DB_PASS);
                        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    } catch (PDOException $e) {
                        throw new Exception("Error al conectar con la base de datos: " . $e->getMessage());
                    }

                    // Después de verificar la conexión a la base de datos, crear las tablas
                    $tables_sql = "
                        CREATE TABLE IF NOT EXISTS users (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            name VARCHAR(100) NOT NULL,
                            email VARCHAR(100) NOT NULL UNIQUE,
                            password VARCHAR(255) NOT NULL,
                            role ENUM('admin', 'instructor', 'student') DEFAULT 'student',
                            status ENUM('active', 'inactive', 'banned') DEFAULT 'active',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                        );

                        CREATE TABLE IF NOT EXISTS courses (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            title VARCHAR(200) NOT NULL,
                            description TEXT,
                            instructor_id INT,
                            price DECIMAL(10,2) DEFAULT 0.00,
                            status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            FOREIGN KEY (instructor_id) REFERENCES users(id)
                        );

                        CREATE TABLE IF NOT EXISTS enrollments (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            user_id INT,
                            course_id INT,
                            status ENUM('pending', 'active', 'completed', 'cancelled') DEFAULT 'pending',
                            enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            completion_date TIMESTAMP NULL,
                            FOREIGN KEY (user_id) REFERENCES users(id),
                            FOREIGN KEY (course_id) REFERENCES courses(id)
                        );

                        CREATE TABLE IF NOT EXISTS lessons (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            course_id INT,
                            title VARCHAR(200) NOT NULL,
                            content TEXT,
                            order_number INT DEFAULT 0,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            FOREIGN KEY (course_id) REFERENCES courses(id)
                        );

                        CREATE TABLE IF NOT EXISTS payments (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            user_id INT,
                            course_id INT,
                            amount DECIMAL(10,2) NOT NULL,
                            status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
                            payment_method VARCHAR(50),
                            transaction_id VARCHAR(100),
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (user_id) REFERENCES users(id),
                            FOREIGN KEY (course_id) REFERENCES courses(id)
                        );

                        CREATE TABLE IF NOT EXISTS progress (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            user_id INT,
                            lesson_id INT,
                            status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
                            last_accessed TIMESTAMP NULL,
                            completed_at TIMESTAMP NULL,
                            FOREIGN KEY (user_id) REFERENCES users(id),
                            FOREIGN KEY (lesson_id) REFERENCES lessons(id)
                        );

                        CREATE TABLE IF NOT EXISTS categories (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            name VARCHAR(100) NOT NULL,
                            slug VARCHAR(100) NOT NULL UNIQUE,
                            description TEXT,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        );

                        CREATE TABLE IF NOT EXISTS course_categories (
                            course_id INT,
                            category_id INT,
                            PRIMARY KEY (course_id, category_id),
                            FOREIGN KEY (course_id) REFERENCES courses(id),
                            FOREIGN KEY (category_id) REFERENCES categories(id)
                        );

                        CREATE TABLE IF NOT EXISTS settings (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            `key` VARCHAR(100) NOT NULL UNIQUE,
                            value TEXT,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                        );
                    ";

                    // Ejecutar las consultas SQL para crear las tablas
                    $queries = array_filter(array_map('trim', explode(';', $tables_sql)));
                    foreach ($queries as $query) {
                        if (!empty($query)) {
                            $db->exec($query);
                        }
                    }

                    // Crear usuario administrador
                    $admin_sql = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')";
                    $stmt = $db->prepare($admin_sql);
                    $stmt->execute([
                        'Administrator',
                        $_SESSION['site_config']['email'],
                        $_SESSION['site_config']['password']
                    ]);

                    // Insertar configuraciones básicas
                    $settings_sql = "INSERT INTO settings (`key`, value) VALUES 
                        ('site_name', ?),
                        ('site_url', ?),
                        ('admin_email', ?),
                        ('maintenance_mode', 'false'),
                        ('timezone', 'America/Mexico_City')";
                    $stmt = $db->prepare($settings_sql);
                    $stmt->execute([
                        $_SESSION['site_config']['name'],
                        $_SESSION['site_config']['url'],
                        $_SESSION['site_config']['email']
                    ]);

                    $success = true;
                    session_destroy(); // Limpiar la sesión después de una instalación exitosa

                } catch (Exception $e) {
                    $error = "Error durante la instalación: " . $e->getMessage();
                    
                    // Limpiar archivos si hubo error
                    if (file_exists('config/config.php')) {
                        unlink('config/config.php');
                    }
                }
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
                        <input type="text" name="site_url" 
                            value="<?php echo 'http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'/public'; ?>" 
                            required>
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
                        $login_path = 'public/login.php';
                        ?>
                        <a href="<?php echo $base_url . '/' . $login_path; ?>" class="btn">Ir al Login</a>
                    </div>
                <?php endif; ?>
                <?php break;
        endswitch; ?>
    </div>
</body>
</html> 
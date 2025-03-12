<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// Verificar si ya está instalado - CORREGIDO
if (file_exists('config/config.php') && is_readable('config/config.php')) {
    $config_content = file_get_contents('config/config.php');
    if (strpos($config_content, 'DB_HOST') !== false && $step === 1) {
        die('El sistema ya está instalado. Por seguridad, elimina el archivo install.php');
    }
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
        $dsn = "mysql:host=$host;charset=utf8mb4";
        $db = new PDO($dsn, $user, $pass);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Intentar crear la base de datos si no existe
        $db->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $db->exec("USE `$name`");
        
        return true;
    } catch (PDOException $e) {
        return $e->getMessage();
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
                    $error = "Error de conexión: " . $db_test;
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

                // Crear archivo de configuración
                if (!file_exists('config')) {
                    mkdir('config', 0755, true);
                }
                
                $config_content = "<?php
define('DB_HOST', '{$_SESSION['db_config']['host']}');
define('DB_NAME', '{$_SESSION['db_config']['name']}');
define('DB_USER', '{$_SESSION['db_config']['user']}');
define('DB_PASS', '{$_SESSION['db_config']['pass']}');
define('SITE_NAME', '{$_SESSION['site_config']['name']}');
define('BASE_URL', '{$_SESSION['site_config']['url']}');
";
                
                if (!file_put_contents('config/config.php', $config_content)) {
                    throw new Exception("No se pudo escribir el archivo de configuración");
                }

                // Importar base de datos
                $db = new PDO(
                    "mysql:host={$_SESSION['db_config']['host']};dbname={$_SESSION['db_config']['name']};charset=utf8mb4",
                    $_SESSION['db_config']['user'],
                    $_SESSION['db_config']['pass']
                );
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $sql = file_get_contents('database/schema.sql');
                $db->exec($sql);

                // Crear usuario administrador
                $stmt = $db->prepare("
                    INSERT INTO users (name, email, password, role) 
                    VALUES ('Administrator', ?, ?, 'admin')
                ");
                $stmt->execute([$_SESSION['site_config']['email'], $_SESSION['site_config']['password']]);

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
                        <a href="login.php" class="btn">Ir al Login</a>
                    </div>
                <?php endif; ?>
                <?php break;
        endswitch; ?>
    </div>
</body>
</html> 
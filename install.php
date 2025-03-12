<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

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
            'cache' => is_writable('cache'),
            'assets/uploads' => is_writable('assets/uploads'),
            'logs' => is_writable('logs')
        ]
    ];

    return $requirements;
}

// Probar conexión a base de datos
function testDatabaseConnection($host, $name, $user, $pass) {
    try {
        $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
        $db = new PDO($dsn, $user, $pass);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return true;
    } catch (PDOException $e) {
        return $e->getMessage();
    }
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 2: // Configuración de base de datos
            $db_host = $_POST['db_host'] ?? '';
            $db_name = $_POST['db_name'] ?? '';
            $db_user = $_POST['db_user'] ?? '';
            $db_pass = $_POST['db_pass'] ?? '';

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
            break;

        case 3: // Configuración del sitio
            $site_name = $_POST['site_name'] ?? '';
            $site_url = $_POST['site_url'] ?? '';
            $admin_email = $_POST['admin_email'] ?? '';
            $admin_password = $_POST['admin_password'] ?? '';

            if (empty($site_name) || empty($site_url) || empty($admin_email) || empty($admin_password)) {
                $error = "Todos los campos son requeridos";
            } else {
                $_SESSION['site_config'] = [
                    'name' => $site_name,
                    'url' => $site_url,
                    'email' => $admin_email,
                    'password' => password_hash($admin_password, PASSWORD_DEFAULT)
                ];
                header('Location: install.php?step=4');
                exit;
            }
            break;

        case 4: // Instalación final
            try {
                // Crear archivo de configuración
                $config_template = file_get_contents('config/config.example.php');
                $config = str_replace(
                    ['your_host', 'your_database', 'your_username', 'your_password', 'your_site_url'],
                    [
                        $_SESSION['db_config']['host'],
                        $_SESSION['db_config']['name'],
                        $_SESSION['db_config']['user'],
                        $_SESSION['db_config']['pass'],
                        $_SESSION['site_config']['url']
                    ],
                    $config_template
                );
                file_put_contents('config/config.php', $config);

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

// Verificar si ya está instalado
if (file_exists('config/config.php') && $step === 1) {
    die('El sistema ya está instalado. Por seguridad, elimina el archivo install.php');
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
        /* Estilos CSS aquí */
        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background: #f7f8fb;
        }
        .container {
            max-width: 800px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .step {
            text-align: center;
            color: #666;
        }
        .step.active {
            color: #FF4B4B;
            font-weight: 500;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #2D3958;
        }
        input[type="text"],
        input[type="password"],
        input[type="email"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn {
            background: #FF4B4B;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn:hover {
            background: #ff3333;
        }
        .error {
            color: #ff3333;
            margin-bottom: 20px;
        }
        .success {
            color: #4CAF50;
            margin-bottom: 20px;
        }
        .requirements {
            list-style: none;
            padding: 0;
        }
        .requirements li {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .requirements .status {
            float: right;
            color: #4CAF50;
        }
        .requirements .status.error {
            color: #ff3333;
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
            case 1: // Verificación de requisitos
                $requirements = checkSystemRequirements();
                $can_continue = true;
                ?>
                <h2>Verificación de Requisitos del Sistema</h2>
                <ul class="requirements">
                    <li>
                        PHP Version (>= 8.0.0)
                        <span class="status <?php echo version_compare(PHP_VERSION, '8.0.0', '>=') ? '' : 'error'; ?>">
                            <?php echo PHP_VERSION; ?>
                        </span>
                    </li>
                    <?php foreach ($requirements['extensions'] as $ext => $loaded): 
                        if (!$loaded) $can_continue = false;
                    ?>
                        <li>
                            Extensión <?php echo strtoupper($ext); ?>
                            <span class="status <?php echo $loaded ? '' : 'error'; ?>">
                                <?php echo $loaded ? '✓' : '✗'; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                    <?php foreach ($requirements['writable_dirs'] as $dir => $writable): 
                        if (!$writable) $can_continue = false;
                    ?>
                        <li>
                            Directorio <?php echo $dir; ?> con permisos de escritura
                            <span class="status <?php echo $writable ? '' : 'error'; ?>">
                                <?php echo $writable ? '✓' : '✗'; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($can_continue): ?>
                    <form method="get">
                        <input type="hidden" name="step" value="2">
                        <button type="submit" class="btn">Continuar</button>
                    </form>
                <?php endif; ?>
                <?php break;

            case 2: // Configuración de base de datos ?>
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
                    <button type="submit" class="btn">Probar Conexión y Continuar</button>
                </form>
                <?php break;

            case 3: // Configuración del sitio ?>
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
                    <button type="submit" class="btn">Continuar</button>
                </form>
                <?php break;

            case 4: // Instalación final ?>
                <h2>Instalación Final</h2>
                <?php if (!$success): ?>
                    <form method="post">
                        <p>Todo está listo para la instalación final. Haz clic en el botón para comenzar.</p>
                        <button type="submit" class="btn">Instalar</button>
                    </form>
                <?php else: ?>
                    <p>La instalación se ha completado con éxito. Por favor:</p>
                    <ol>
                        <li>Elimina el archivo install.php</li>
                        <li>Accede al panel de administración</li>
                        <li>Configura los métodos de pago y correo</li>
                    </ol>
                    <a href="login.php" class="btn">Ir al Login</a>
                <?php endif; ?>
                <?php break;
        endswitch; ?>
    </div>
</body>
</html> 
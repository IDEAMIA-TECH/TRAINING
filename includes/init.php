<?php
session_start();

// Definir la ruta base del proyecto
define('BASE_PATH', realpath(__DIR__ . '/..'));

// Verificar si el sistema está instalado
if (!file_exists(BASE_PATH . '/config/config.php') || 
    !file_exists(BASE_PATH . '/config/database.php') || 
    !file_exists(BASE_PATH . '/config/app.php')) {
    
    // Redirigir al instalador si no está instalado
    $installFile = '/install.php';
    if (!file_exists(BASE_PATH . $installFile)) {
        die('El sistema no está instalado y no se encuentra el instalador.');
    }
    
    // Solo redirigir si no estamos ya en el instalador
    if (strpos($_SERVER['PHP_SELF'], 'install.php') === false) {
        header('Location: ' . $installFile);
        exit;
    }
}

// Si estamos en el instalador, no necesitamos cargar más
if (strpos($_SERVER['PHP_SELF'], 'install.php') !== false) {
    return;
}

// Cargar configuraciones
require_once BASE_PATH . '/config/config.php';
$db_config = require BASE_PATH . '/config/database.php';
$app_config = require BASE_PATH . '/config/app.php';
$mail_config = file_exists(BASE_PATH . '/config/mail.php') ? 
    require BASE_PATH . '/config/mail.php' : [];

// Cargar clases y funciones
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/pagination.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/maintenance.php';
require_once __DIR__ . '/security/SecurityMiddleware.php';
require_once __DIR__ . '/cache/CacheManager.php';
require_once __DIR__ . '/database/QueryOptimizer.php';

// Inicializar conexión a base de datos usando la configuración del archivo database.php
try {
    $dsn = "mysql:host={$db_config['host']};dbname={$db_config['name']};charset={$db_config['charset']}";
    $db = new PDO($dsn, $db_config['user'], $db_config['pass'], $db_config['options']);
} catch (PDOException $e) {
    if (strpos($_SERVER['PHP_SELF'], 'install.php') === false) {
        die('Error de conexión: ' . $e->getMessage());
    }
}

// Inicializar componentes con la conexión a la base de datos
$logger = new Logger($db);
$settings = new Settings($db);
$cache = new CacheManager();
$query_optimizer = new QueryOptimizer($db, $cache, $logger);
$security = new SecurityMiddleware($db, $logger, $settings);

// Configurar zona horaria desde app.php
date_default_timezone_set($app_config['timezone'] ?? 'America/Mexico_City');

// Definir constantes globales desde app.php
define('APP_NAME', $app_config['name'] ?? 'Sistema de Cursos');
define('APP_URL', $app_config['url'] ?? '');
define('APP_ENV', $app_config['env'] ?? 'production');
define('DEBUG_MODE', $app_config['debug'] ?? false);

// Verificar si el usuario está autenticado
$user_authenticated = is_authenticated();
$is_admin = is_admin();

// Verificar modo mantenimiento
if (isset($app_config['maintenance_mode']) && $app_config['maintenance_mode']) {
    check_maintenance_mode();
}

// Aplicar validaciones de seguridad
try {
    $security->validateRequest();
} catch (Exception $e) {
    if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    } else {
        $_SESSION['error'] = $e->getMessage();
        redirect('/error.php');
    }
    exit;
} 
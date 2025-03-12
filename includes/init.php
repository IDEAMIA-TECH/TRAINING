<?php
session_start();

// Definir constantes de rutas
define('BASE_PATH', realpath(__DIR__ . '/..'));
define('PUBLIC_PATH', '/public');

// Verificar si estamos en el instalador
$isInstaller = strpos($_SERVER['PHP_SELF'], 'install.php') !== false;

// Si estamos en el instalador, no necesitamos hacer nada más
if ($isInstaller) {
    return;
}

// Verificar si el sistema está instalado
if (!file_exists(BASE_PATH . '/config/config.php')) {
    header('Location: /install.php');
    exit;
}

// Cargar configuración principal
require_once BASE_PATH . '/config/config.php';

// Función helper para redirecciones
function redirect($path) {
    $base_url = rtrim(BASE_URL, '/');
    $public_path = '/public';
    
    // Si la ruta no comienza con /, añadirlo
    if (strpos($path, '/') !== 0) {
        $path = '/' . $path;
    }
    
    // Si la ruta no incluye /public/ y no es una ruta especial, añadir /public/
    if (strpos($path, '/public/') !== 0 && 
        strpos($path, '/api/') !== 0 && 
        strpos($path, '/assets/') !== 0) {
        $path = $public_path . $path;
    }
    
    header('Location: ' . $base_url . $path);
    exit;
}

// Inicializar conexión a base de datos
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $db = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die('Error de conexión: ' . $e->getMessage());
}

// Cargar clases y funciones
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/pagination.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/maintenance.php';
require_once __DIR__ . '/security/SecurityMiddleware.php';
require_once __DIR__ . '/cache/CacheManager.php';
require_once __DIR__ . '/database/QueryOptimizer.php';

// Inicializar componentes
$logger = new Logger($db);
$settings = new Settings($db);
$cache = new CacheManager();
$query_optimizer = new QueryOptimizer($db, $cache, $logger);
$security = new SecurityMiddleware($db, $logger, $settings);

// Configurar zona horaria
date_default_timezone_set('America/Mexico_City');

// Verificar si el usuario está autenticado
$user_authenticated = is_authenticated();
$is_admin = is_admin();

// Verificar modo mantenimiento
check_maintenance_mode();

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
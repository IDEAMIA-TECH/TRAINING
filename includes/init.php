<?php
session_start();

// Cargar configuración
require_once __DIR__ . '/../config/config.php';

// Cargar funciones de utilidad
require_once __DIR__ . '/functions.php';

// Configurar zona horaria
date_default_timezone_set('America/Mexico_City');

// Configurar manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Conexión a la base de datos
try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Variables globales
$user_authenticated = is_authenticated();
$user_is_admin = is_admin();

// Función de redirección
function redirect($path) {
    header("Location: " . BASE_URL . $path);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/pagination.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/maintenance.php';
require_once __DIR__ . '/security/SecurityMiddleware.php';
require_once __DIR__ . '/cache/CacheManager.php';
require_once __DIR__ . '/database/QueryOptimizer.php';

// Inicializar conexión a la base de datos
$database = new Database();
$db = $database->getConnection();

// Inicializar logger
$logger = new Logger($db);

// Inicializar settings
$settings = new Settings($db);

// Inicializar cache
$cache = new CacheManager();

// Inicializar optimizador de consultas
$query_optimizer = new QueryOptimizer($db, $cache, $logger);

// Inicializar middleware de seguridad
$security = new SecurityMiddleware($db, $logger, $settings);

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

// Verificar modo mantenimiento
check_maintenance_mode(); 
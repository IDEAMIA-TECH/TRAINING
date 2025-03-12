<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
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

// Configurar zona horaria
date_default_timezone_set('America/Mexico_City');

// Verificar si el usuario está autenticado
$user_authenticated = is_authenticated();
$is_admin = is_admin();

// Verificar modo mantenimiento
check_maintenance_mode(); 
<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Inicializar conexión a la base de datos
$database = new Database();
$db = $database->getConnection();

// Configurar zona horaria
date_default_timezone_set('America/Mexico_City');

// Verificar si el usuario está autenticado
$user_authenticated = is_authenticated();
$is_admin = is_admin(); 
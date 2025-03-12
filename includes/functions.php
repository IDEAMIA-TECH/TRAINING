<?php
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function is_authenticated() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

if (!function_exists('redirect')) {
    /**
     * Redirecciona a una URL específica
     * @param string $path Ruta a la que redireccionar
     * @return void
     */
    function redirect($path) {
        $base_url = rtrim(BASE_URL, '/');  // ejemplo: https://devgdlhost.com/training
        
        // Si la ruta no comienza con /, añadirlo
        if (strpos($path, '/') !== 0) {
            $path = '/' . $path;
        }
        
        // Si la ruta no incluye /public/ y no es una ruta especial, añadir /public/
        if (strpos($path, '/public/') !== 0 && 
            strpos($path, '/api/') !== 0 && 
            strpos($path, '/assets/') !== 0) {
            $path = '/public' . $path;
        }
        
        // Construir la URL completa
        $url = $base_url . $path;  // ejemplo: https://devgdlhost.com/training/public/login.php
        
        header('Location: ' . $url);
        exit;
    }
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
} 
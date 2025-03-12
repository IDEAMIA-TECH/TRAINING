<?php
// Funciones de utilidad
function is_authenticated() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function sanitize_input($data) {
    return htmlspecialchars(trim($data));
}

function format_date($date, $format = 'd/m/Y') {
    return date($format, strtotime($date));
}

function format_currency($amount) {
    return '$' . number_format($amount, 2);
}

function redirect($path) {
    header("Location: " . BASE_URL . $path);
    exit();
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
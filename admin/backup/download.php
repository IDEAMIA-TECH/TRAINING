<?php
require_once '../../includes/init.php';

if (!$is_admin) {
    redirect('/login.php');
}

$filename = $_GET['file'] ?? '';
if (!$filename || !preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $filename)) {
    die('Archivo no válido');
}

$file_path = __DIR__ . '/../../backups/' . $filename;
if (!file_exists($file_path)) {
    die('Archivo no encontrado');
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($file_path));
readfile($file_path); 
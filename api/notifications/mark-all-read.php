<?php
require_once '../../includes/header.php';
require_once '../../includes/NotificationManager.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $notification_manager = new NotificationManager($conn);
    $success = $notification_manager->markAllAsRead($_SESSION['user_id']);
    
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
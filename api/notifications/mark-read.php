<?php
require_once '../../includes/header.php';
require_once '../../includes/NotificationManager.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        throw new Exception("ID de notificación no proporcionado");
    }
    
    $notification_manager = new NotificationManager($conn);
    $success = $notification_manager->markAsRead($data['id'], $_SESSION['user_id']);
    
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
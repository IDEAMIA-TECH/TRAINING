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
    
    if (!isset($data['email_notifications']) || !isset($data['browser_notifications'])) {
        throw new Exception("Datos incompletos");
    }
    
    $notification_manager = new NotificationManager($conn);
    $success = $notification_manager->updatePreferences(
        $_SESSION['user_id'],
        $data['email_notifications'],
        $data['browser_notifications']
    );
    
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
<?php
require_once '../../includes/header.php';
require_once '../../includes/ChatManager.php';

if (!has_permission('manage_support')) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['session_id'])) {
        throw new Exception("ID de sesión requerido");
    }
    
    $chat_manager = new ChatManager($conn);
    $chat_manager->closeSession($data['session_id']);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
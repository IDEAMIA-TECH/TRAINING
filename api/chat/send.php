<?php
require_once '../../includes/header.php';
require_once '../../includes/ChatManager.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    if (empty($_POST['session_id']) || empty($_POST['message'])) {
        throw new Exception("Faltan datos requeridos");
    }
    
    $chat_manager = new ChatManager($conn);
    $chat_manager->sendMessage(
        $_POST['session_id'],
        $_SESSION['user_id'],
        $_POST['message']
    );
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
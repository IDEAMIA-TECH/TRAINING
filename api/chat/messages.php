<?php
require_once '../../includes/header.php';
require_once '../../includes/ChatManager.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    if (empty($_GET['session_id'])) {
        throw new Exception("ID de sesión requerido");
    }
    
    $chat_manager = new ChatManager($conn);
    $last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
    
    $messages = $chat_manager->getMessages($_GET['session_id'], $last_id);
    $chat_manager->markMessagesAsRead($_GET['session_id'], $_SESSION['user_id']);
    
    echo json_encode(['success' => true, 'messages' => $messages]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
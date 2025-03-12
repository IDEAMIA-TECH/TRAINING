<?php
require_once '../../includes/header.php';
require_once '../../includes/ChatManager.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $chat_manager = new ChatManager($conn);
    $session_id = $chat_manager->createSession($_SESSION['user_id']);
    
    // Buscar un operador disponible
    $operator = $chat_manager->getAvailableOperator();
    
    if ($operator) {
        // Enviar mensaje de bienvenida
        $chat_manager->sendMessage(
            $session_id,
            $operator['id'],
            "¡Hola! ¿En qué puedo ayudarte?"
        );
    }
    
    echo json_encode(['success' => true, 'session_id' => $session_id]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
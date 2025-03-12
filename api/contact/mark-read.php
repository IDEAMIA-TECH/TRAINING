<?php
require_once '../../includes/header.php';

if (!is_admin()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        throw new Exception("ID de mensaje no proporcionado");
    }
    
    $stmt = $conn->prepare("
        UPDATE contact_messages 
        SET status = 'read' 
        WHERE id = ? AND status = 'new'
    ");
    
    $success = $stmt->execute([$data['id']]);
    
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
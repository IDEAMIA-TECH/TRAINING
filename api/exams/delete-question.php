<?php
require_once '../../includes/header.php';

if (!has_permission('manage_exams')) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        throw new Exception("ID no especificado");
    }
    
    // Verificar si la pregunta tiene respuestas
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM user_answers WHERE question_id = ?
    ");
    $stmt->execute([$data['id']]);
    
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("No se puede eliminar una pregunta que ya tiene respuestas");
    }
    
    // Eliminar pregunta y sus opciones (CASCADE)
    $stmt = $conn->prepare("DELETE FROM questions WHERE id = ?");
    $stmt->execute([$data['id']]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
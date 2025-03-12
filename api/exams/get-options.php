<?php
require_once '../../includes/header.php';

if (!has_permission('manage_exams')) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    if (empty($_GET['question_id'])) {
        throw new Exception("ID de pregunta no especificado");
    }
    
    $stmt = $conn->prepare("
        SELECT id, option_text, is_correct, order_index
        FROM answer_options
        WHERE question_id = ?
        ORDER BY order_index
    ");
    $stmt->execute([$_GET['question_id']]);
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'options' => $options]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
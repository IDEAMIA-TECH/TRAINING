<?php
require_once '../../includes/header.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validar datos
    if (empty($data['attempt_id']) || empty($data['question_id'])) {
        throw new Exception("Datos incompletos");
    }
    
    // Verificar que el intento pertenezca al usuario
    $stmt = $conn->prepare("
        SELECT status, user_id 
        FROM exam_attempts 
        WHERE id = ?
    ");
    $stmt->execute([$data['attempt_id']]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$attempt || $attempt['user_id'] != $_SESSION['user_id']) {
        throw new Exception("Intento no válido");
    }
    
    if ($attempt['status'] !== 'in_progress') {
        throw new Exception("El examen ya ha finalizado");
    }
    
    // Obtener información de la pregunta
    $stmt = $conn->prepare("
        SELECT question_type, correct_answer 
        FROM questions 
        WHERE id = ?
    ");
    $stmt->execute([$data['question_id']]);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar si la respuesta es correcta
    $is_correct = null;
    if ($question['question_type'] === 'multiple_choice') {
        $stmt = $conn->prepare("
            SELECT is_correct 
            FROM answer_options 
            WHERE id = ?
        ");
        $stmt->execute([$data['answer']]);
        $is_correct = $stmt->fetchColumn();
    } elseif ($question['question_type'] === 'true_false') {
        $is_correct = $data['answer'] === $question['correct_answer'];
    } elseif ($question['question_type'] === 'short_answer') {
        $is_correct = strtolower(trim($data['answer'])) === strtolower(trim($question['correct_answer']));
    }
    
    // Guardar o actualizar respuesta
    $stmt = $conn->prepare("
        INSERT INTO user_answers (
            attempt_id, question_id, answer_text, is_correct
        ) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            answer_text = VALUES(answer_text),
            is_correct = VALUES(is_correct)
    ");
    
    $stmt->execute([
        $data['attempt_id'],
        $data['question_id'],
        $data['answer'],
        $is_correct
    ]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
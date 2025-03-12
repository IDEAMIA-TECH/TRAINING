<?php
require_once '../../includes/header.php';

if (!has_permission('manage_exams')) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validar datos
    if (empty($data['question_text']) || empty($data['question_type'])) {
        throw new Exception("Datos incompletos");
    }
    
    $conn->beginTransaction();
    
    if (empty($data['id'])) {
        // Obtener el último order_index
        $stmt = $conn->prepare("
            SELECT MAX(order_index) as max_order 
            FROM questions 
            WHERE exam_id = ?
        ");
        $stmt->execute([$data['exam_id']]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC)['max_order'] ?? 0;
        
        // Crear nueva pregunta
        $stmt = $conn->prepare("
            INSERT INTO questions (
                exam_id, question_type, question_text,
                points, correct_answer, explanation, order_index
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['exam_id'],
            $data['question_type'],
            $data['question_text'],
            $data['points'],
            $data['correct_answer'] ?? null,
            $data['explanation'] ?? null,
            $order + 1
        ]);
        
        $questionId = $conn->lastInsertId();
        
    } else {
        // Actualizar pregunta existente
        $stmt = $conn->prepare("
            UPDATE questions 
            SET question_type = ?,
                question_text = ?,
                points = ?,
                correct_answer = ?,
                explanation = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['question_type'],
            $data['question_text'],
            $data['points'],
            $data['correct_answer'] ?? null,
            $data['explanation'] ?? null,
            $data['id']
        ]);
        
        $questionId = $data['id'];
        
        // Eliminar opciones existentes si es opción múltiple
        if ($data['question_type'] === 'multiple_choice') {
            $stmt = $conn->prepare("DELETE FROM answer_options WHERE question_id = ?");
            $stmt->execute([$questionId]);
        }
    }
    
    // Insertar opciones si es opción múltiple
    if ($data['question_type'] === 'multiple_choice' && !empty($data['options'])) {
        $stmt = $conn->prepare("
            INSERT INTO answer_options (
                question_id, option_text, is_correct, order_index
            ) VALUES (?, ?, ?, ?)
        ");
        
        foreach ($data['options'] as $index => $option) {
            $stmt->execute([
                $questionId,
                $option['text'],
                $option['is_correct'],
                $index + 1
            ]);
        }
    }
    
    $conn->commit();
    echo json_encode(['success' => true, 'question_id' => $questionId]);
    
} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
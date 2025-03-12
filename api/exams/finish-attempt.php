<?php
require_once '../../includes/header.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['attempt_id'])) {
        throw new Exception("ID de intento no especificado");
    }
    
    // Verificar que el intento pertenezca al usuario
    $stmt = $conn->prepare("
        SELECT ea.*, e.passing_score
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        WHERE ea.id = ? AND ea.user_id = ?
    ");
    $stmt->execute([$data['attempt_id'], $_SESSION['user_id']]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$attempt) {
        throw new Exception("Intento no válido");
    }
    
    if ($attempt['status'] !== 'in_progress') {
        throw new Exception("El examen ya ha finalizado");
    }
    
    $conn->beginTransaction();
    
    // Calcular puntuación
    $stmt = $conn->prepare("
        SELECT 
            SUM(q.points) as total_points,
            SUM(CASE WHEN ua.is_correct THEN q.points ELSE 0 END) as earned_points
        FROM questions q
        LEFT JOIN user_answers ua ON ua.question_id = q.id 
            AND ua.attempt_id = ?
        WHERE q.exam_id = ?
    ");
    $stmt->execute([$data['attempt_id'], $attempt['exam_id']]);
    $points = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calcular porcentaje
    $score = $points['total_points'] > 0 
        ? round(($points['earned_points'] / $points['total_points']) * 100) 
        : 0;
    
    // Actualizar intento
    $stmt = $conn->prepare("
        UPDATE exam_attempts 
        SET status = 'completed',
            score = ?,
            end_time = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$score, $data['attempt_id']]);
    
    // Si aprobó, generar certificado
    if ($score >= $attempt['passing_score']) {
        $stmt = $conn->prepare("
            INSERT INTO certificates (
                user_id, course_id, exam_id, score, issued_date
            ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                score = VALUES(score),
                issued_date = VALUES(issued_date)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $attempt['course_id'],
            $attempt['exam_id'],
            $score
        ]);
    }
    
    $conn->commit();
    echo json_encode(['success' => true, 'score' => $score]);
    
} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
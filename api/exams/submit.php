<?php
require_once '../../includes/header.php';
require_once '../../includes/ExamManager.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $exam_id = $_POST['exam_id'];
    $exam_manager = new ExamManager($conn);
    
    // Iniciar intento
    $attempt_id = $exam_manager->startExam($exam_id, $_SESSION['user_id']);
    
    // Procesar respuestas
    foreach ($_POST['answers'] as $question_id => $answer) {
        $exam_manager->submitAnswer($attempt_id, $question_id, $answer);
    }
    
    // Finalizar intento
    $exam_manager->finishExam($attempt_id);
    
    echo json_encode([
        'success' => true,
        'attempt_id' => $attempt_id
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
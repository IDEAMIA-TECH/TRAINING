<?php
require_once '../includes/header.php';
require_once '../includes/ExamManager.php';

if (!is_logged_in()) {
    header("Location: ../login.php");
    exit();
}

if (empty($_GET['attempt_id'])) {
    header("Location: ../courses.php");
    exit();
}

$exam_manager = new ExamManager($conn);

try {
    $results = $exam_manager->getExamResults($_GET['attempt_id']);
    
    // Verificar que el usuario tenga acceso a estos resultados
    if ($results['user_id'] != $_SESSION['user_id'] && !has_permission('manage_exams')) {
        throw new Exception("No tienes permiso para ver estos resultados");
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<div class="results-container">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
            <a href="../courses.php" class="btn btn-primary mt-3">Volver a Cursos</a>
        </div>
    <?php else: ?>
        <div class="results-header">
            <div>
                <h2><?php echo htmlspecialchars($results['exam_title']); ?></h2>
                <p class="text-muted">
                    <?php echo htmlspecialchars($results['user_name']); ?> - 
                    <?php echo date('d/m/Y H:i', strtotime($results['end_time'])); ?>
                </p>
            </div>
            
            <div class="score-display">
                <div class="score <?php echo $results['score'] >= $results['passing_score'] ? 'passing' : 'failing'; ?>">
                    <?php echo $results['score']; ?>%
                </div>
                <div class="score-label">
                    <?php echo $results['score'] >= $results['passing_score'] ? 'Aprobado' : 'No Aprobado'; ?>
                </div>
            </div>
        </div>
        
        <div class="results-summary">
            <div class="summary-item">
                <div class="summary-label">Preguntas Totales</div>
                <div class="summary-value"><?php echo $results['total_questions']; ?></div>
            </div>
            
            <div class="summary-item">
                <div class="summary-label">Respuestas Correctas</div>
                <div class="summary-value"><?php echo $results['correct_answers']; ?></div>
            </div>
            
            <div class="summary-item">
                <div class="summary-label">Tiempo Empleado</div>
                <div class="summary-value">
                    <?php 
                    $duration = strtotime($results['end_time']) - strtotime($results['start_time']);
                    echo floor($duration / 60) . ' min ' . ($duration % 60) . ' seg';
                    ?>
                </div>
            </div>
        </div>
        
        <div class="results-details">
            <h3>Detalle de Respuestas</h3>
            
            <?php foreach ($results['answers'] as $index => $answer): ?>
                <div class="answer-card">
                    <div class="answer-header">
                        <span class="question-number"><?php echo $index + 1; ?></span>
                        <div class="points-info">
                            <?php echo $answer['points_earned']; ?> / <?php echo $answer['points']; ?> puntos
                        </div>
                    </div>
                    
                    <div class="answer-content">
                        <p class="question-text">
                            <?php echo htmlspecialchars($answer['question_text']); ?>
                        </p>
                        
                        <div class="user-answer <?php echo $answer['is_correct'] ? 'correct' : 'incorrect'; ?>">
                            <strong>Tu respuesta:</strong>
                            <?php echo htmlspecialchars($answer['answer_text']); ?>
                        </div>
                        
                        <?php if ($answer['feedback']): ?>
                            <div class="feedback">
                                <strong>Retroalimentación:</strong>
                                <?php echo htmlspecialchars($answer['feedback']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="results-actions">
            <a href="../courses.php" class="btn btn-primary">Volver a Cursos</a>
            <?php if ($results['score'] >= $results['passing_score']): ?>
                <a href="../certificates/generate.php?exam_id=<?php echo $results['exam_id']; ?>" 
                   class="btn btn-success">
                    Obtener Certificado
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/exam-results.css">

<?php require_once '../includes/footer.php'; ?> 
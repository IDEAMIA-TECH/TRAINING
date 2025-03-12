<?php
require_once '../includes/header.php';
require_once '../includes/ExamManager.php';

if (!is_logged_in()) {
    header("Location: ../login.php");
    exit();
}

if (empty($_GET['exam_id'])) {
    header("Location: ../courses.php");
    exit();
}

$exam_manager = new ExamManager($conn);

try {
    // Verificar si el usuario puede tomar el examen
    $stmt = $conn->prepare("
        SELECT e.*, c.title as course_title,
               COUNT(DISTINCT ea.id) as attempts_made
        FROM exams e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN exam_attempts ea ON ea.exam_id = e.id 
            AND ea.user_id = ? AND ea.status = 'completed'
        WHERE e.id = ? AND e.is_active = TRUE
        GROUP BY e.id
    ");
    $stmt->execute([$_SESSION['user_id'], $_GET['exam_id']]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exam) {
        throw new Exception("Examen no encontrado o no disponible");
    }
    
    if ($exam['attempts_made'] >= $exam['attempts_allowed']) {
        throw new Exception("Has alcanzado el número máximo de intentos permitidos");
    }
    
    // Verificar si hay un intento en progreso
    $stmt = $conn->prepare("
        SELECT id, TIMESTAMPDIFF(MINUTE, start_time, NOW()) as elapsed_time
        FROM exam_attempts 
        WHERE user_id = ? AND exam_id = ? AND status = 'in_progress'
        ORDER BY start_time DESC LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id'], $_GET['exam_id']]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Si no hay intento o el tiempo expiró, crear uno nuevo
    if (!$attempt || $attempt['elapsed_time'] >= $exam['duration']) {
        if ($attempt) {
            // Marcar el intento anterior como abandonado
            $stmt = $conn->prepare("
                UPDATE exam_attempts SET status = 'abandoned'
                WHERE id = ?
            ");
            $stmt->execute([$attempt['id']]);
        }
        
        $attemptId = $exam_manager->startExam($_SESSION['user_id'], $_GET['exam_id']);
    } else {
        $attemptId = $attempt['id'];
    }
    
    // Obtener preguntas del examen
    $stmt = $conn->prepare("
        SELECT q.*, 
               ua.answer_text as user_answer,
               ua.is_correct,
               ua.feedback
        FROM questions q
        LEFT JOIN user_answers ua ON ua.question_id = q.id 
            AND ua.attempt_id = ?
        WHERE q.exam_id = ?
        ORDER BY q.order_index
    ");
    $stmt->execute([$attemptId, $_GET['exam_id']]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Para preguntas de opción múltiple, obtener sus opciones
    foreach ($questions as &$question) {
        if ($question['question_type'] === 'multiple_choice') {
            $stmt = $conn->prepare("
                SELECT * FROM answer_options 
                WHERE question_id = ?
                ORDER BY order_index
            ");
            $stmt->execute([$question['id']]);
            $question['options'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<div class="exam-container">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
            <a href="../courses.php" class="btn btn-primary mt-3">Volver a Cursos</a>
        </div>
    <?php else: ?>
        <div class="exam-header">
            <div>
                <h2><?php echo htmlspecialchars($exam['title']); ?></h2>
                <p class="text-muted">
                    <?php echo htmlspecialchars($exam['course_title']); ?>
                </p>
            </div>
            
            <div class="exam-info">
                <div class="timer" data-duration="<?php echo $exam['duration']; ?>">
                    <i class="fas fa-clock"></i>
                    <span class="time-remaining"></span>
                </div>
                
                <div class="progress-info">
                    Pregunta <span class="current-question">1</span> de <?php echo count($questions); ?>
                </div>
            </div>
        </div>
        
        <form id="examForm" data-attempt="<?php echo $attemptId; ?>">
            <div class="questions-container">
                <?php foreach ($questions as $index => $question): ?>
                    <div class="question-slide" data-index="<?php echo $index; ?>">
                        <div class="question-content">
                            <h4 class="question-text">
                                <?php echo htmlspecialchars($question['question_text']); ?>
                            </h4>
                            
                            <div class="answer-container">
                                <?php if ($question['question_type'] === 'multiple_choice'): ?>
                                    <?php foreach ($question['options'] as $option): ?>
                                        <div class="custom-control custom-radio">
                                            <input type="radio" 
                                                   id="option_<?php echo $option['id']; ?>" 
                                                   name="answer_<?php echo $question['id']; ?>" 
                                                   value="<?php echo $option['id']; ?>"
                                                   class="custom-control-input"
                                                   <?php echo $question['user_answer'] == $option['id'] ? 'checked' : ''; ?>>
                                            <label class="custom-control-label" for="option_<?php echo $option['id']; ?>">
                                                <?php echo htmlspecialchars($option['option_text']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                <?php elseif ($question['question_type'] === 'true_false'): ?>
                                    <div class="btn-group btn-group-toggle" data-toggle="buttons">
                                        <label class="btn btn-outline-primary">
                                            <input type="radio" 
                                                   name="answer_<?php echo $question['id']; ?>" 
                                                   value="true"
                                                   <?php echo $question['user_answer'] === 'true' ? 'checked' : ''; ?>>
                                            Verdadero
                                        </label>
                                        <label class="btn btn-outline-primary">
                                            <input type="radio" 
                                                   name="answer_<?php echo $question['id']; ?>" 
                                                   value="false"
                                                   <?php echo $question['user_answer'] === 'false' ? 'checked' : ''; ?>>
                                            Falso
                                        </label>
                                    </div>
                                    
                                <?php else: ?>
                                    <textarea name="answer_<?php echo $question['id']; ?>" 
                                              class="form-control" 
                                              rows="3"><?php echo $question['user_answer']; ?></textarea>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($question['feedback']): ?>
                                <div class="feedback alert alert-<?php echo $question['is_correct'] ? 'success' : 'danger'; ?>">
                                    <?php echo htmlspecialchars($question['feedback']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="question-footer">
                            <?php if ($index > 0): ?>
                                <button type="button" class="btn btn-secondary prev-question">
                                    <i class="fas fa-arrow-left"></i> Anterior
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($index < count($questions) - 1): ?>
                                <button type="button" class="btn btn-primary next-question">
                                    Siguiente <i class="fas fa-arrow-right"></i>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-success finish-exam">
                                    Finalizar Examen
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </form>
    <?php endif; ?>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/exam-take.js"></script>

<?php require_once '../includes/footer.php'; ?> 
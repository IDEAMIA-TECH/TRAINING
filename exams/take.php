<?php
require_once '../includes/header.php';
require_once '../includes/ExamManager.php';

if (!is_logged_in()) {
    header("Location: ../login.php");
    exit();
}

$exam_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$exam_manager = new ExamManager($conn);

try {
    // Verificar acceso al examen
    $stmt = $conn->prepare("
        SELECT e.*, c.title as course_title
        FROM exams e
        JOIN courses c ON e.course_id = c.id
        JOIN enrollments en ON c.id = en.course_id
        WHERE e.id = ? AND en.user_id = ? AND e.is_active = TRUE
    ");
    $stmt->execute([$exam_id, $_SESSION['user_id']]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exam) {
        throw new Exception("No tienes acceso a este examen");
    }
    
    // Verificar intentos previos
    $stmt = $conn->prepare("
        SELECT COUNT(*) as attempts
        FROM exam_attempts
        WHERE exam_id = ? AND user_id = ?
    ");
    $stmt->execute([$exam_id, $_SESSION['user_id']]);
    $attempts = $stmt->fetch(PDO::FETCH_ASSOC)['attempts'];
    
    if ($attempts >= $exam['attempts_allowed']) {
        throw new Exception("Has alcanzado el número máximo de intentos permitidos");
    }
    
    // Obtener preguntas
    $stmt = $conn->prepare("
        SELECT q.*, 
               GROUP_CONCAT(
                   CONCAT(o.id, ':', o.option_text)
                   ORDER BY o.order_index
                   SEPARATOR '|'
               ) as options
        FROM exam_questions q
        LEFT JOIN question_options o ON q.id = o.question_id
        WHERE q.exam_id = ?
        GROUP BY q.id
        ORDER BY q.order_index
    ");
    $stmt->execute([$exam_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<div class="exam-container">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
    <?php else: ?>
        <div class="exam-header">
            <h1><?php echo htmlspecialchars($exam['title']); ?></h1>
            <div class="exam-meta">
                <span>Curso: <?php echo htmlspecialchars($exam['course_title']); ?></span>
                <span>Duración: <?php echo $exam['duration']; ?> minutos</span>
                <span>Puntaje mínimo: <?php echo $exam['passing_score']; ?> puntos</span>
            </div>
            <p class="exam-description">
                <?php echo nl2br(htmlspecialchars($exam['description'])); ?>
            </p>
        </div>
        
        <form id="examForm" class="exam-form" data-duration="<?php echo $exam['duration']; ?>">
            <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
            
            <div class="exam-timer">
                Tiempo restante: <span id="timer"></span>
            </div>
            
            <div class="questions-list">
                <?php foreach ($questions as $index => $question): ?>
                    <div class="question-item">
                        <h3>Pregunta <?php echo $index + 1; ?></h3>
                        <p class="question-text">
                            <?php echo htmlspecialchars($question['question']); ?>
                        </p>
                        
                        <?php if ($question['type'] === 'multiple_choice'): ?>
                            <div class="options-list">
                                <?php
                                $options = array_map(function($opt) {
                                    list($id, $text) = explode(':', $opt);
                                    return ['id' => $id, 'text' => $text];
                                }, explode('|', $question['options']));
                                
                                foreach ($options as $option):
                                ?>
                                    <label class="option-label">
                                        <input type="radio" 
                                               name="answers[<?php echo $question['id']; ?>]" 
                                               value="<?php echo $option['id']; ?>" 
                                               required>
                                        <?php echo htmlspecialchars($option['text']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($question['type'] === 'true_false'): ?>
                            <div class="options-list">
                                <label class="option-label">
                                    <input type="radio" 
                                           name="answers[<?php echo $question['id']; ?>]" 
                                           value="true" 
                                           required>
                                    Verdadero
                                </label>
                                <label class="option-label">
                                    <input type="radio" 
                                           name="answers[<?php echo $question['id']; ?>]" 
                                           value="false" 
                                           required>
                                    Falso
                                </label>
                            </div>
                        <?php else: ?>
                            <div class="form-group">
                                <textarea name="answers[<?php echo $question['id']; ?>]" 
                                          rows="3" 
                                          required></textarea>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    Enviar Respuestas
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/exam-timer.js"></script>

<?php require_once '../includes/footer.php'; ?> 
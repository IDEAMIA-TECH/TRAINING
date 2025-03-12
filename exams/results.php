<?php
require_once '../includes/header.php';
require_once '../includes/ExamManager.php';

if (!is_logged_in()) {
    header("Location: ../login.php");
    exit();
}

$attempt_id = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;
$exam_manager = new ExamManager($conn);

try {
    // Obtener información del intento
    $stmt = $conn->prepare("
        SELECT ea.*, e.title as exam_title, e.passing_score, c.title as course_title,
               (SELECT SUM(points) FROM exam_questions WHERE exam_id = e.id) as total_points
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        JOIN courses c ON e.course_id = c.id
        WHERE ea.id = ? AND ea.user_id = ?
    ");
    $stmt->execute([$attempt_id, $_SESSION['user_id']]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$attempt) {
        throw new Exception("Intento de examen no encontrado");
    }
    
    // Obtener respuestas y preguntas
    $stmt = $conn->prepare("
        SELECT ua.*, eq.question, eq.type, eq.points,
               GROUP_CONCAT(
                   CONCAT(qo.id, ':', qo.option_text, ':', qo.is_correct)
                   ORDER BY qo.order_index
                   SEPARATOR '|'
               ) as options
        FROM user_answers ua
        JOIN exam_questions eq ON ua.question_id = eq.id
        LEFT JOIN question_options qo ON eq.id = qo.question_id
        WHERE ua.attempt_id = ?
        GROUP BY ua.id
        ORDER BY eq.order_index
    ");
    $stmt->execute([$attempt_id]);
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $passed = $attempt['score'] >= $attempt['passing_score'];
    $percentage = round(($attempt['score'] / $attempt['total_points']) * 100);
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<div class="results-container">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
    <?php else: ?>
        <div class="results-header">
            <h1>Resultados del Examen</h1>
            <div class="exam-info">
                <h2><?php echo htmlspecialchars($attempt['exam_title']); ?></h2>
                <p class="course-title">
                    Curso: <?php echo htmlspecialchars($attempt['course_title']); ?>
                </p>
            </div>
            
            <div class="score-summary">
                <div class="score-card <?php echo $passed ? 'passed' : 'failed'; ?>">
                    <div class="score-value">
                        <?php echo $percentage; ?>%
                    </div>
                    <div class="score-label">
                        <?php echo $passed ? 'Aprobado' : 'No Aprobado'; ?>
                    </div>
                    <div class="score-details">
                        Puntaje: <?php echo $attempt['score']; ?> / <?php echo $attempt['total_points']; ?>
                    </div>
                </div>
                
                <div class="attempt-info">
                    <div class="info-item">
                        <span class="label">Fecha:</span>
                        <span class="value">
                            <?php echo date('d/m/Y H:i', strtotime($attempt['end_time'])); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="label">Duración:</span>
                        <span class="value">
                            <?php 
                            $duration = strtotime($attempt['end_time']) - strtotime($attempt['start_time']);
                            echo floor($duration / 60) . ' minutos';
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="answers-review">
            <h3>Revisión de Respuestas</h3>
            
            <?php foreach ($answers as $index => $answer): ?>
                <div class="answer-item <?php echo $answer['is_correct'] ? 'correct' : 'incorrect'; ?>">
                    <div class="question-header">
                        <h4>Pregunta <?php echo $index + 1; ?></h4>
                        <div class="points">
                            <?php echo $answer['points_earned']; ?> / <?php echo $answer['points']; ?> puntos
                        </div>
                    </div>
                    
                    <p class="question-text">
                        <?php echo htmlspecialchars($answer['question']); ?>
                    </p>
                    
                    <?php if ($answer['type'] === 'multiple_choice'): ?>
                        <div class="options-list">
                            <?php
                            $options = array_map(function($opt) {
                                list($id, $text, $is_correct) = explode(':', $opt);
                                return [
                                    'id' => $id,
                                    'text' => $text,
                                    'is_correct' => $is_correct === '1'
                                ];
                            }, explode('|', $answer['options']));
                            
                            foreach ($options as $option):
                                $class = '';
                                if ($option['id'] === $answer['answer']) {
                                    $class = $answer['is_correct'] ? 'selected correct' : 'selected incorrect';
                                } elseif ($option['is_correct']) {
                                    $class = 'correct';
                                }
                            ?>
                                <div class="option <?php echo $class; ?>">
                                    <?php echo htmlspecialchars($option['text']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="answer-text">
                            <strong>Tu respuesta:</strong>
                            <?php echo htmlspecialchars($answer['answer']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$answer['is_correct']): ?>
                        <div class="feedback">
                            <i class="fas fa-info-circle"></i>
                            La respuesta correcta está marcada en verde
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="results-actions">
            <a href="../courses/view.php?id=<?php echo $attempt['course_id']; ?>" class="btn btn-primary">
                Volver al Curso
            </a>
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Imprimir Resultados
            </button>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?> 
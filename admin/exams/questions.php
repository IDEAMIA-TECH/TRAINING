<?php
require_once '../../includes/header.php';
require_once '../../includes/ExamManager.php';

if (!has_permission('manage_exams')) {
    header("Location: ../../login.php");
    exit();
}

if (empty($_GET['exam_id'])) {
    header("Location: index.php");
    exit();
}

$exam_manager = new ExamManager($conn);

// Obtener información del examen
$stmt = $conn->prepare("
    SELECT e.*, c.title as course_title
    FROM exams e
    JOIN courses c ON e.course_id = c.id
    WHERE e.id = ?
");
$stmt->execute([$_GET['exam_id']]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    header("Location: index.php");
    exit();
}

// Obtener preguntas del examen
$stmt = $conn->prepare("
    SELECT q.*, 
           COUNT(DISTINCT ao.id) as options_count,
           COUNT(DISTINCT ua.id) as answers_count
    FROM questions q
    LEFT JOIN answer_options ao ON ao.question_id = q.id
    LEFT JOIN user_answers ua ON ua.question_id = q.id
    WHERE q.exam_id = ?
    GROUP BY q.id
    ORDER BY q.order_index
");
$stmt->execute([$_GET['exam_id']]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-container">
    <?php require_once '../sidebar.php'; ?>
    
    <div class="admin-content">
        <div class="dashboard-header">
            <div>
                <h2>Preguntas del Examen</h2>
                <p class="text-muted">
                    <?php echo htmlspecialchars($exam['title']); ?> - 
                    <?php echo htmlspecialchars($exam['course_title']); ?>
                </p>
            </div>
            
            <div class="header-actions">
                <button class="btn btn-primary" data-toggle="modal" data-target="#questionModal">
                    <i class="fas fa-plus"></i> Nueva Pregunta
                </button>
            </div>
        </div>
        
        <div class="content-grid">
            <!-- Lista de Preguntas -->
            <div class="questions-list">
                <?php foreach ($questions as $index => $question): ?>
                    <div class="question-card" data-id="<?php echo $question['id']; ?>">
                        <div class="question-header">
                            <span class="question-number"><?php echo $index + 1; ?></span>
                            <div class="question-type">
                                <?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?>
                            </div>
                            <div class="question-points">
                                <?php echo $question['points']; ?> puntos
                            </div>
                        </div>
                        
                        <div class="question-content">
                            <p class="question-text">
                                <?php echo htmlspecialchars($question['question_text']); ?>
                            </p>
                            
                            <?php if ($question['question_type'] === 'multiple_choice'): ?>
                                <div class="options-count">
                                    <?php echo $question['options_count']; ?> opciones
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($question['explanation']): ?>
                                <div class="explanation">
                                    <i class="fas fa-info-circle"></i> Tiene explicación
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="question-footer">
                            <div class="stats">
                                <span title="Respuestas recibidas">
                                    <i class="fas fa-pencil-alt"></i> <?php echo $question['answers_count']; ?>
                                </span>
                            </div>
                            
                            <div class="actions">
                                <button class="btn btn-sm btn-info edit-question" 
                                        data-question='<?php echo htmlspecialchars(json_encode($question)); ?>'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($question['answers_count'] == 0): ?>
                                    <button class="btn btn-sm btn-danger delete-question" 
                                            data-id="<?php echo $question['id']; ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Pregunta -->
<div class="modal" id="questionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Pregunta</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="questionForm">
                    <input type="hidden" name="id">
                    <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                    
                    <div class="form-group">
                        <label>Tipo de Pregunta</label>
                        <select name="question_type" class="form-control" required>
                            <option value="multiple_choice">Opción Múltiple</option>
                            <option value="true_false">Verdadero/Falso</option>
                            <option value="short_answer">Respuesta Corta</option>
                            <option value="essay">Ensayo</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Pregunta</label>
                        <textarea name="question_text" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Puntos</label>
                        <input type="number" name="points" class="form-control" required min="1" value="1">
                    </div>
                    
                    <!-- Opciones para preguntas de opción múltiple -->
                    <div id="optionsContainer" style="display: none;">
                        <div class="options-list"></div>
                        <button type="button" class="btn btn-sm btn-secondary mt-2" id="addOption">
                            <i class="fas fa-plus"></i> Agregar Opción
                        </button>
                    </div>
                    
                    <!-- Respuesta correcta para preguntas de respuesta corta -->
                    <div id="correctAnswerContainer" style="display: none;">
                        <div class="form-group">
                            <label>Respuesta Correcta</label>
                            <input type="text" name="correct_answer" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Explicación/Retroalimentación</label>
                        <textarea name="explanation" class="form-control" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="saveQuestion">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/question-admin.js"></script>

<?php require_once '../../includes/footer.php'; ?> 
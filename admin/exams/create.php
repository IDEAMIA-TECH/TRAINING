<?php
require_once '../../includes/header.php';
require_once '../../includes/ExamManager.php';

if (!is_instructor()) {
    header("Location: ../../login.php");
    exit();
}

$exam_manager = new ExamManager($conn);
$error = null;
$success = false;

// Obtener cursos del instructor
$stmt = $conn->prepare("
    SELECT id, title 
    FROM courses 
    WHERE instructor_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $exam_data = [
            'course_id' => $_POST['course_id'],
            'title' => $_POST['title'],
            'description' => $_POST['description'],
            'duration' => $_POST['duration'],
            'passing_score' => $_POST['passing_score'],
            'attempts_allowed' => $_POST['attempts_allowed']
        ];
        
        $exam_id = $exam_manager->createExam($exam_data);
        
        // Procesar preguntas
        foreach ($_POST['questions'] as $index => $question) {
            $question_data = [
                'question' => $question['text'],
                'type' => $question['type'],
                'points' => $question['points'],
                'order_index' => $index,
                'options' => []
            ];
            
            if ($question['type'] === 'multiple_choice') {
                foreach ($question['options'] as $opt_index => $option) {
                    $question_data['options'][] = [
                        'text' => $option['text'],
                        'is_correct' => isset($option['is_correct'])
                    ];
                }
            }
            
            $exam_manager->addQuestion($exam_id, $question_data);
        }
        
        $success = true;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="admin-container">
    <?php require_once '../sidebar.php'; ?>
    
    <div class="admin-content">
        <div class="exam-form-container">
            <h2>Crear Nuevo Examen</h2>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    Examen creado exitosamente
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="examForm" class="exam-form">
                <div class="form-group">
                    <label for="course_id">Curso *</label>
                    <select id="course_id" name="course_id" required>
                        <option value="">Seleccionar curso</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>">
                                <?php echo htmlspecialchars($course['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="title">Título del Examen *</label>
                    <input type="text" id="title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Descripción</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="duration">Duración (minutos) *</label>
                        <input type="number" id="duration" name="duration" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="passing_score">Puntaje para aprobar *</label>
                        <input type="number" id="passing_score" name="passing_score" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="attempts_allowed">Intentos permitidos *</label>
                        <input type="number" id="attempts_allowed" name="attempts_allowed" min="1" value="1" required>
                    </div>
                </div>
                
                <div class="questions-container">
                    <h3>Preguntas</h3>
                    <div id="questionsList"></div>
                    
                    <button type="button" class="btn btn-secondary" onclick="addQuestion()">
                        <i class="fas fa-plus"></i> Agregar Pregunta
                    </button>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        Crear Examen
                    </button>
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/exam-form.js"></script>

<?php require_once '../../includes/footer.php'; ?> 
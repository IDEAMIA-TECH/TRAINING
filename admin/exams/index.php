<?php
require_once '../../includes/header.php';
require_once '../../includes/ExamManager.php';

if (!has_permission('manage_exams')) {
    header("Location: ../../login.php");
    exit();
}

$exam_manager = new ExamManager($conn);

// Obtener cursos para el selector
$stmt = $conn->prepare("SELECT id, title FROM courses ORDER BY title");
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener exámenes con estadísticas
$stmt = $conn->prepare("
    SELECT 
        e.*,
        c.title as course_title,
        COUNT(DISTINCT q.id) as total_questions,
        COUNT(DISTINCT ea.id) as total_attempts,
        AVG(ea.score) as average_score
    FROM exams e
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN questions q ON q.exam_id = e.id
    LEFT JOIN exam_attempts ea ON ea.exam_id = e.id AND ea.status = 'completed'
    GROUP BY e.id
    ORDER BY e.created_at DESC
");
$stmt->execute();
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-container">
    <?php require_once '../sidebar.php'; ?>
    
    <div class="admin-content">
        <div class="dashboard-header">
            <h2>Gestión de Exámenes</h2>
            
            <div class="header-actions">
                <button class="btn btn-primary" data-toggle="modal" data-target="#examModal">
                    <i class="fas fa-plus"></i> Nuevo Examen
                </button>
            </div>
        </div>
        
        <div class="content-grid">
            <!-- Lista de Exámenes -->
            <div class="content-card">
                <div class="card-header">
                    <h3>Exámenes</h3>
                </div>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Curso</th>
                                <th>Preguntas</th>
                                <th>Intentos</th>
                                <th>Promedio</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exams as $exam): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                    <td><?php echo htmlspecialchars($exam['course_title']); ?></td>
                                    <td><?php echo $exam['total_questions']; ?></td>
                                    <td><?php echo $exam['total_attempts']; ?></td>
                                    <td>
                                        <?php 
                                        if ($exam['average_score']) {
                                            echo round($exam['average_score'], 1) . '%';
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $exam['is_active'] ? 'success' : 'danger'; ?>">
                                            <?php echo $exam['is_active'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="edit.php?id=<?php echo $exam['id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="questions.php?exam_id=<?php echo $exam['id']; ?>" 
                                               class="btn btn-sm btn-info">
                                                <i class="fas fa-list"></i>
                                            </a>
                                            <a href="results.php?exam_id=<?php echo $exam['id']; ?>" 
                                               class="btn btn-sm btn-success">
                                                <i class="fas fa-chart-bar"></i>
                                            </a>
                                            <button class="btn btn-sm btn-danger delete-exam" 
                                                    data-id="<?php echo $exam['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Examen -->
<div class="modal" id="examModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo Examen</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="examForm">
                    <div class="form-group">
                        <label>Curso</label>
                        <select name="course_id" class="form-control" required>
                            <option value="">Seleccionar curso...</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Título</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Duración (minutos)</label>
                        <input type="number" name="duration" class="form-control" required min="1">
                    </div>
                    
                    <div class="form-group">
                        <label>Puntaje para aprobar (0-100)</label>
                        <input type="number" name="passing_score" class="form-control" required min="0" max="100">
                    </div>
                    
                    <div class="form-group">
                        <label>Intentos permitidos</label>
                        <input type="number" name="attempts_allowed" class="form-control" required min="1">
                    </div>
                    
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" checked>
                            <label class="custom-control-label" for="is_active">Examen activo</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="saveExam">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/exam-admin.js"></script>

<?php require_once '../../includes/footer.php'; ?> 
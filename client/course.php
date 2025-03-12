<?php
require_once '../includes/header.php';

if (!is_logged_in() || is_admin()) {
    header("Location: ../login.php");
    exit();
}

$error = '';
$course = null;
$enrollment = null;
$user_id = $_SESSION['user_id'];

// Obtener el curso y la inscripción
if (isset($_GET['id'])) {
    $course_id = (int)$_GET['id'];
    
    // Verificar que el usuario esté inscrito y haya pagado
    $enrollment_query = "
        SELECT e.*, c.*
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.course_id = ? 
        AND e.user_id = ? 
        AND e.payment_status = 'completed'
    ";
    
    $stmt = $conn->prepare($enrollment_query);
    $stmt->execute([$course_id, $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        header("Location: dashboard.php");
        exit();
    }
    
    $course = $result;
    $enrollment = $result;
}

// Obtener materiales del curso
$materials_query = "
    SELECT * FROM course_materials 
    WHERE course_id = ? 
    ORDER BY order_index ASC
";

$stmt = $conn->prepare($materials_query);
$stmt->execute([$course_id]);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="client-container">
    <div class="client-sidebar">
        <div class="user-info">
            <h3>Bienvenido,</h3>
            <p><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
        </div>
        <nav>
            <a href="dashboard.php">Mi Panel</a>
            <a href="profile.php">Mi Perfil</a>
            <a href="../courses/">Ver Cursos</a>
        </nav>
    </div>

    <div class="client-content">
        <div class="course-header">
            <h2><?php echo htmlspecialchars($course['title']); ?></h2>
            <div class="course-meta">
                <span class="course-dates">
                    <i class="far fa-calendar"></i>
                    <?php echo date('d/m/Y H:i', strtotime($course['start_date'])); ?> - 
                    <?php echo date('d/m/Y H:i', strtotime($course['end_date'])); ?>
                </span>
                
                <?php if (strtotime($course['end_date']) < time()): ?>
                    <span class="status-badge completed">Completado</span>
                <?php else: ?>
                    <span class="status-badge active">En Curso</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($course['image_url']): ?>
            <div class="course-image">
                <img src="<?php echo UPLOADS_URL . '/courses/' . $course['image_url']; ?>" 
                     alt="<?php echo htmlspecialchars($course['title']); ?>">
            </div>
        <?php endif; ?>

        <div class="course-content">
            <div class="course-section">
                <h3>Descripción del Curso</h3>
                <div class="course-description">
                    <?php echo nl2br(htmlspecialchars($course['description'])); ?>
                </div>
            </div>

            <?php if ($course['syllabus']): ?>
                <div class="course-section">
                    <h3>Temario</h3>
                    <div class="course-syllabus">
                        <?php echo nl2br(htmlspecialchars($course['syllabus'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($materials)): ?>
                <div class="course-section">
                    <h3>Materiales del Curso</h3>
                    <div class="materials-list">
                        <?php foreach ($materials as $material): ?>
                            <div class="material-item">
                                <div class="material-info">
                                    <h4><?php echo htmlspecialchars($material['title']); ?></h4>
                                    <?php if ($material['description']): ?>
                                        <p><?php echo htmlspecialchars($material['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php if ($material['file_url']): ?>
                                    <a href="<?php echo UPLOADS_URL . '/materials/' . $material['file_url']; ?>" 
                                       class="btn btn-secondary btn-sm" target="_blank">
                                        <i class="fas fa-download"></i> Descargar
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 
<?php
require_once '../includes/header.php';
require_once '../includes/CourseMaterial.php';

if (!is_logged_in()) {
    header("Location: ../login.php");
    exit();
}

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Verificar inscripción
$stmt = $conn->prepare("
    SELECT e.* FROM enrollments e
    WHERE e.user_id = ? AND e.course_id = ? AND e.payment_status = 'completed'
");
$stmt->execute([$_SESSION['user_id'], $course_id]);

if (!$stmt->fetch()) {
    header("Location: dashboard.php");
    exit();
}

// Obtener información del curso
$stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener materiales
$material_manager = new CourseMaterial($conn);
$materials = $material_manager->getMaterials($course_id);
?>

<div class="client-container">
    <?php require_once 'sidebar.php'; ?>
    
    <div class="client-content">
        <div class="materials-container">
            <div class="materials-header">
                <h2>Materiales del Curso: <?php echo htmlspecialchars($course['title']); ?></h2>
            </div>
            
            <?php if (empty($materials)): ?>
                <div class="empty-state">
                    <p>Aún no hay materiales disponibles para este curso.</p>
                </div>
            <?php else: ?>
                <div class="materials-grid">
                    <?php foreach ($materials as $material): ?>
                        <div class="material-card">
                            <div class="material-icon">
                                <?php echo getMaterialIcon($material['file_type']); ?>
                            </div>
                            
                            <div class="material-info">
                                <h3><?php echo htmlspecialchars($material['title']); ?></h3>
                                <?php if ($material['description']): ?>
                                    <p class="material-description">
                                        <?php echo htmlspecialchars($material['description']); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="material-meta">
                                    <span class="material-type <?php echo $material['file_type']; ?>">
                                        <?php echo ucfirst($material['file_type']); ?>
                                    </span>
                                    <span class="material-size">
                                        <?php echo formatFileSize($material['file_size']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="material-actions">
                                <a href="<?php echo BASE_URL . '/' . $material['file_url']; ?>" 
                                   class="btn btn-primary btn-sm" target="_blank">
                                    <i class="fas fa-download"></i> Descargar
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 
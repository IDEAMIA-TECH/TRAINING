<?php
require_once '../includes/header.php';

if (!is_logged_in() || is_admin()) {
    header("Location: ../login.php");
    exit();
}

// Obtener información del usuario
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener cursos inscritos
$enrollments_query = "
    SELECT e.*, c.title, c.start_date, c.end_date, c.image_url
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE e.user_id = ?
    ORDER BY c.start_date DESC
";

$stmt = $conn->prepare($enrollments_query);
$stmt->execute([$user_id]);
$enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular estadísticas
$stats = [
    'total_courses' => count($enrollments),
    'completed_courses' => 0,
    'upcoming_courses' => 0,
    'total_spent' => 0
];

foreach ($enrollments as $enrollment) {
    if ($enrollment['payment_status'] === 'completed') {
        $stats['total_spent'] += $enrollment['payment_amount'];
        
        if (strtotime($enrollment['end_date']) < time()) {
            $stats['completed_courses']++;
        } else {
            $stats['upcoming_courses']++;
        }
    }
}
?>

<div class="client-container">
    <div class="client-sidebar">
        <div class="user-info">
            <h3>Bienvenido,</h3>
            <p><?php echo htmlspecialchars($user['name']); ?></p>
        </div>
        <nav>
            <a href="dashboard.php" class="active">Mi Panel</a>
            <a href="profile.php">Mi Perfil</a>
            <a href="../courses/">Ver Cursos</a>
        </nav>
    </div>

    <div class="client-content">
        <h2>Mi Panel</h2>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Cursos Inscritos</h3>
                <p class="stat-number"><?php echo $stats['total_courses']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Cursos Completados</h3>
                <p class="stat-number"><?php echo $stats['completed_courses']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Próximos Cursos</h3>
                <p class="stat-number"><?php echo $stats['upcoming_courses']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Total Invertido</h3>
                <p class="stat-number">$<?php echo number_format($stats['total_spent'], 2); ?> MXN</p>
            </div>
        </div>

        <div class="my-courses">
            <h3>Mis Cursos</h3>
            
            <?php if (empty($enrollments)): ?>
                <div class="empty-state">
                    <p>Aún no te has inscrito a ningún curso.</p>
                    <a href="../courses/" class="btn btn-primary">Ver Cursos Disponibles</a>
                </div>
            <?php else: ?>
                <div class="courses-grid">
                    <?php foreach ($enrollments as $enrollment): ?>
                        <div class="course-card">
                            <?php if ($enrollment['image_url']): ?>
                                <img src="<?php echo UPLOADS_URL . '/courses/' . $enrollment['image_url']; ?>" 
                                     alt="<?php echo htmlspecialchars($enrollment['title']); ?>">
                            <?php endif; ?>
                            
                            <div class="course-info">
                                <h4><?php echo htmlspecialchars($enrollment['title']); ?></h4>
                                
                                <p class="course-date">
                                    <i class="far fa-calendar"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($enrollment['start_date'])); ?>
                                </p>
                                
                                <div class="course-status">
                                    <?php if ($enrollment['payment_status'] !== 'completed'): ?>
                                        <span class="status-badge <?php echo $enrollment['payment_status']; ?>">
                                            <?php echo ucfirst($enrollment['payment_status']); ?>
                                        </span>
                                    <?php elseif (strtotime($enrollment['end_date']) < time()): ?>
                                        <span class="status-badge completed">Completado</span>
                                    <?php else: ?>
                                        <span class="status-badge active">En Curso</span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($enrollment['payment_status'] === 'completed'): ?>
                                    <a href="course.php?id=<?php echo $enrollment['course_id']; ?>" 
                                       class="btn btn-primary btn-block">
                                        Ver Detalles
                                    </a>
                                <?php elseif ($enrollment['payment_status'] === 'pending'): ?>
                                    <a href="payment.php?enrollment_id=<?php echo $enrollment['id']; ?>" 
                                       class="btn btn-primary btn-block">
                                        Completar Pago
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 
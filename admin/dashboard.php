<?php
require_once '../includes/header.php';

// Verificar si el usuario es administrador
if (!is_admin()) {
    header("Location: ../login.php");
    exit();
}

// Estadísticas generales
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM courses) as total_courses,
        (SELECT COUNT(*) FROM users WHERE role = 'client') as total_users,
        (SELECT COUNT(*) FROM enrollments) as total_enrollments,
        (SELECT SUM(payment_amount) FROM enrollments WHERE payment_status = 'completed') as total_revenue
";

$stats = $conn->query($stats_query)->fetch(PDO::FETCH_ASSOC);

// Actividad reciente
$activity_query = "
    SELECT 
        'enrollment' as type,
        e.created_at as date,
        u.name as user_name,
        c.title as course_title,
        e.payment_status as status,
        e.payment_amount as amount
    FROM enrollments e
    JOIN users u ON e.user_id = u.id
    JOIN courses c ON e.course_id = c.id
    ORDER BY e.created_at DESC
    LIMIT 10
";

$recent_activity = $conn->query($activity_query)->fetchAll(PDO::FETCH_ASSOC);

// Próximos cursos
$upcoming_courses_query = "
    SELECT c.*, 
           COUNT(e.id) as total_enrollments,
           COUNT(CASE WHEN e.payment_status = 'completed' THEN 1 END) as paid_enrollments
    FROM courses c
    LEFT JOIN enrollments e ON c.id = e.course_id
    WHERE c.start_date > NOW()
    GROUP BY c.id
    ORDER BY c.start_date ASC
    LIMIT 5
";

$upcoming_courses = $conn->query($upcoming_courses_query)->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-container">
    <div class="admin-sidebar">
        <h3>Panel de Administración</h3>
        <nav>
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="courses/">Cursos</a>
            <a href="payments/">Pagos</a>
            <a href="users/">Usuarios</a>
            <a href="reports/">Reportes</a>
        </nav>
    </div>

    <div class="admin-content">
        <h2>Dashboard</h2>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total de Cursos</h3>
                <p class="stat-number"><?php echo $stats['total_courses']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Usuarios Registrados</h3>
                <p class="stat-number"><?php echo $stats['total_users']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Total de Inscripciones</h3>
                <p class="stat-number"><?php echo $stats['total_enrollments']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Ingresos Totales</h3>
                <p class="stat-number">$<?php echo number_format($stats['total_revenue'], 2); ?> MXN</p>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="recent-activity">
                <h3>Actividad Reciente</h3>
                <div class="activity-list">
                    <?php foreach ($recent_activity as $activity): ?>
                        <div class="activity-item">
                            <span class="activity-date">
                                <?php echo date('d/m/Y H:i', strtotime($activity['date'])); ?>
                            </span>
                            <p>
                                <?php echo htmlspecialchars($activity['user_name']); ?> 
                                se inscribió al curso 
                                <?php echo htmlspecialchars($activity['course_title']); ?>
                            </p>
                            <span class="activity-status <?php echo $activity['status']; ?>">
                                <?php echo ucfirst($activity['status']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="upcoming-courses">
                <h3>Próximos Cursos</h3>
                <?php if (empty($upcoming_courses)): ?>
                    <div class="empty-state">
                        <p>No hay cursos próximos programados</p>
                    </div>
                <?php else: ?>
                    <div class="courses-list">
                        <?php foreach ($upcoming_courses as $course): ?>
                            <div class="course-item">
                                <div class="course-info">
                                    <h4><?php echo htmlspecialchars($course['title']); ?></h4>
                                    <p class="course-date">
                                        <i class="far fa-calendar"></i>
                                        <?php echo date('d/m/Y H:i', strtotime($course['start_date'])); ?>
                                    </p>
                                </div>
                                <div class="course-stats">
                                    <div class="stat">
                                        <span class="stat-label">Inscritos:</span>
                                        <span class="stat-value"><?php echo $course['total_enrollments']; ?></span>
                                    </div>
                                    <div class="stat">
                                        <span class="stat-label">Pagados:</span>
                                        <span class="stat-value"><?php echo $course['paid_enrollments']; ?></span>
                                    </div>
                                </div>
                                <a href="courses/edit.php?id=<?php echo $course['id']; ?>" 
                                   class="btn btn-secondary btn-sm">
                                    Ver Detalles
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 
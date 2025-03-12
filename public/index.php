<?php
require_once '../includes/init.php';

try {
    // Obtener cursos destacados (los más recientes y activos)
    $stmt = $db->prepare("
        SELECT c.*, 
               COUNT(DISTINCT cr.id) as registered_students,
               (SELECT image_url FROM course_images WHERE course_id = c.id AND is_main = 1 LIMIT 1) as main_image
        FROM courses c
        LEFT JOIN course_registrations cr ON c.id = cr.course_id
        WHERE c.status = 'active' AND c.start_date > CURRENT_TIMESTAMP
        GROUP BY c.id
        ORDER BY c.created_at DESC
        LIMIT 6
    ");
    $stmt->execute();
    $featured_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener estadísticas generales
    $stats = [
        'total_courses' => $db->query("SELECT COUNT(*) FROM courses WHERE status = 'active'")->fetchColumn(),
        'total_students' => $db->query("SELECT COUNT(DISTINCT user_id) FROM course_registrations")->fetchColumn(),
        'upcoming_courses' => $db->query("SELECT COUNT(*) FROM courses WHERE status = 'active' AND start_date > CURRENT_TIMESTAMP")->fetchColumn()
    ];
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<?php require_once '../templates/header.php'; ?>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <h1>Modern Education<br>FOR EVERYONE</h1>
        <p>Descubre nuestros cursos especializados y mejora tus habilidades profesionales.</p>
        <a href="<?php echo BASE_URL; ?>/courses" class="btn btn-primary">Ver Cursos</a>
    </div>
</section>

<!-- Estadísticas -->
<div class="bg-light py-5">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <i class="bi bi-book display-4 text-primary"></i>
                        <h3 class="card-title mt-3"><?php echo $stats['total_courses']; ?></h3>
                        <p class="card-text">Cursos Activos</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <i class="bi bi-people display-4 text-primary"></i>
                        <h3 class="card-title mt-3"><?php echo $stats['total_students']; ?></h3>
                        <p class="card-text">Estudiantes Registrados</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <i class="bi bi-calendar-check display-4 text-primary"></i>
                        <h3 class="card-title mt-3"><?php echo $stats['upcoming_courses']; ?></h3>
                        <p class="card-text">Próximos Cursos</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Características Clave -->
<section class="features">
    <div class="container">
        <div class="feature-grid">
            <div class="feature-card">
                <i class="fas fa-graduation-cap"></i>
                <h3>Online Courses</h3>
                <p>Accede a contenido de calidad desde cualquier lugar.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-users"></i>
                <h3>Expert Teachers</h3>
                <p>Aprende con los mejores profesionales del sector.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-certificate"></i>
                <h3>Certification</h3>
                <p>Obtén certificados reconocidos en la industria.</p>
            </div>
        </div>
    </div>
</section>

<!-- Cursos Destacados -->
<section class="courses">
    <div class="container">
        <h2>Cursos Destacados</h2>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php else: ?>
            <div class="course-grid">
                <?php foreach ($featured_courses as $course): ?>
                    <div class="course-card">
                        <img src="<?php echo BASE_URL; ?>/assets/uploads/courses/<?php echo htmlspecialchars($course['main_image']); ?>" 
                             alt="<?php echo htmlspecialchars($course['title']); ?>"
                             class="course-image">
                        <div class="course-content">
                            <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                            <p><?php echo htmlspecialchars(substr($course['description'], 0, 100)) . '...'; ?></p>
                            <div class="course-meta">
                                <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($course['start_date'])); ?></span>
                                <span><i class="fas fa-users"></i> <?php echo $course['capacity'] - $course['registered_students']; ?> lugares</span>
                            </div>
                            <div class="course-price">$<?php echo number_format($course['price'], 2); ?></div>
                            <a href="/courses/<?php echo $course['id']; ?>" class="btn btn-primary">Ver Detalles</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Contador -->
<section class="countdown">
    <div class="container">
        <h2>FREE INTRODUCTORY SEMINAR</h2>
        <div class="countdown-grid">
            <div class="countdown-item">
                <span class="number">26</span>
                <span class="label">Days</span>
            </div>
            <div class="countdown-item">
                <span class="number">07</span>
                <span class="label">Hours</span>
            </div>
            <div class="countdown-item">
                <span class="number">29</span>
                <span class="label">Minutes</span>
            </div>
            <div class="countdown-item">
                <span class="number">34</span>
                <span class="label">Seconds</span>
            </div>
        </div>
        <a href="<?php echo BASE_URL; ?>/register" class="btn btn-primary">Register Now</a>
    </div>
</section>

<?php require_once '../templates/footer.php'; ?> 
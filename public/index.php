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
<div class="bg-primary text-white py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="display-4 fw-bold">Aprende con los Mejores</h1>
                <p class="lead">Descubre nuestros cursos especializados y mejora tus habilidades profesionales.</p>
                <a href="<?php echo BASE_URL; ?>/courses.php" class="btn btn-light btn-lg">
                    Ver Cursos Disponibles
                </a>
            </div>
            <div class="col-md-6">
                <img src="<?php echo BASE_URL; ?>/assets/img/hero-image.jpg" alt="Educación Online" class="img-fluid rounded">
            </div>
        </div>
    </div>
</div>

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

<!-- Cursos Destacados -->
<div class="py-5">
    <div class="container">
        <h2 class="text-center mb-5">Cursos Destacados</h2>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-3 g-4">
                <?php foreach ($featured_courses as $course): ?>
                    <div class="col">
                        <div class="card h-100">
                            <img src="<?php echo BASE_URL; ?>/assets/uploads/courses/<?php echo $course['main_image']; ?>" 
                                 class="card-img-top" alt="<?php echo htmlspecialchars($course['title']); ?>">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars(substr($course['description'], 0, 100)) . '...'; ?></p>
                                <ul class="list-unstyled">
                                    <li><i class="bi bi-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($course['start_date'])); ?></li>
                                    <li><i class="bi bi-people"></i> <?php echo $course['capacity'] - $course['registered_students']; ?> lugares disponibles</li>
                                    <li><i class="bi bi-tag"></i> $<?php echo number_format($course['price'], 2); ?></li>
                                </ul>
                            </div>
                            <div class="card-footer">
                                <a href="<?php echo BASE_URL; ?>/courses.php?id=<?php echo $course['id']; ?>" class="btn btn-primary w-100">
                                    Ver Detalles
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-5">
                <a href="<?php echo BASE_URL; ?>/courses.php" class="btn btn-outline-primary btn-lg">
                    Ver Todos los Cursos
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Características -->
<div class="bg-light py-5">
    <div class="container">
        <h2 class="text-center mb-5">¿Por qué Elegirnos?</h2>
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="text-center">
                    <i class="bi bi-award display-4 text-primary"></i>
                    <h4 class="mt-3">Instructores Calificados</h4>
                    <p>Aprende de profesionales con amplia experiencia en la industria.</p>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="text-center">
                    <i class="bi bi-laptop display-4 text-primary"></i>
                    <h4 class="mt-3">Cursos Actualizados</h4>
                    <p>Contenido actualizado y relevante para el mercado actual.</p>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="text-center">
                    <i class="bi bi-graph-up display-4 text-primary"></i>
                    <h4 class="mt-3">Desarrollo Profesional</h4>
                    <p>Mejora tus habilidades y avanza en tu carrera profesional.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?> 
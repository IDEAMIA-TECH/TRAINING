<?php
require_once '../includes/init.php';

if (!$user_authenticated) {
    redirect('/login.php');
}

$payment_id = (int)($_GET['id'] ?? 0);
if (!$payment_id) {
    redirect('/courses.php');
}

try {
    // Actualizar estado del pago a fallido
    $stmt = $db->prepare("
        UPDATE payments 
        SET status = 'failed', 
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ? AND user_id = ? AND status = 'pending'
    ");
    $stmt->execute([$payment_id, $_SESSION['user_id']]);

    // Actualizar estado de la inscripción a cancelada
    $stmt = $db->prepare("
        UPDATE course_registrations 
        SET status = 'cancelled'
        WHERE payment_id = ?
    ");
    $stmt->execute([$payment_id]);

    // Obtener información del curso
    $stmt = $db->prepare("
        SELECT c.title, c.id
        FROM payments p
        JOIN courses c ON p.course_id = c.id
        WHERE p.id = ?
    ");
    $stmt->execute([$payment_id]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<?php require_once '../templates/header.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 text-center">
            <div class="card">
                <div class="card-body">
                    <i class="bi bi-x-circle text-danger" style="font-size: 4rem;"></i>
                    <h2 class="mt-3">Pago Cancelado</h2>
                    <p class="lead">La transacción no pudo completarse.</p>
                    
                    <?php if (isset($course)): ?>
                        <div class="mt-4">
                            <p>No se pudo procesar tu inscripción al curso:</p>
                            <h5><?php echo htmlspecialchars($course['title']); ?></h5>
                        </div>
                        
                        <div class="mt-4">
                            <a href="<?php echo BASE_URL; ?>/courses.php?id=<?php echo $course['id']; ?>" class="btn btn-primary">
                                Intentar Nuevamente
                            </a>
                            <a href="<?php echo BASE_URL; ?>/courses.php" class="btn btn-outline-primary ms-2">
                                Ver otros Cursos
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="mt-4">
                            <a href="<?php echo BASE_URL; ?>/courses.php" class="btn btn-primary">Ver Cursos Disponibles</a>
                        </div>
                    <?php endif; ?>

                    <div class="mt-4">
                        <p class="text-muted">
                            Si tienes problemas con el pago, por favor contacta a nuestro equipo de soporte.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?> 
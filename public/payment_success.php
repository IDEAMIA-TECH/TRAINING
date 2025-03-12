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
    // Obtener información del pago y curso
    $stmt = $db->prepare("
        SELECT p.*, c.title as course_title, c.start_date, c.end_date
        FROM payments p
        JOIN courses c ON p.course_id = c.id
        WHERE p.id = ? AND p.user_id = ? AND p.status = 'completed'
    ");
    $stmt->execute([$payment_id, $_SESSION['user_id']]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        redirect('/courses.php');
    }
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
                    <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
                    <h2 class="mt-3">¡Pago Exitoso!</h2>
                    <p class="lead">Tu inscripción al curso ha sido confirmada.</p>
                    
                    <div class="mt-4">
                        <h5>Detalles de la Compra</h5>
                        <p><strong>Curso:</strong> <?php echo htmlspecialchars($payment['course_title']); ?></p>
                        <p><strong>Monto:</strong> $<?php echo number_format($payment['amount'], 2); ?></p>
                        <p><strong>Fecha de Inicio:</strong> <?php echo date('d/m/Y H:i', strtotime($payment['start_date'])); ?></p>
                        <p><strong>ID de Transacción:</strong> <?php echo $payment['transaction_id']; ?></p>
                    </div>

                    <div class="mt-4">
                        <p>Hemos enviado un correo electrónico con los detalles de tu inscripción.</p>
                        <div class="mt-4">
                            <a href="<?php echo BASE_URL; ?>/profile.php" class="btn btn-primary">Ver Mis Cursos</a>
                            <a href="<?php echo BASE_URL; ?>/courses.php" class="btn btn-outline-primary ms-2">Explorar más Cursos</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?> 
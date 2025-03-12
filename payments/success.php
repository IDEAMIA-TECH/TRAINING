<?php
require_once '../includes/header.php';
require_once '../includes/PaymentManager.php';

if (!is_logged_in()) {
    header("Location: ../login.php");
    exit();
}

if (empty($_GET['session_id'])) {
    header("Location: plans.php");
    exit();
}

try {
    $payment_manager = new PaymentManager($conn);
    $payment_manager->processPayment($_GET['session_id']);
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: plans.php");
    exit();
}
?>

<div class="success-container">
    <div class="success-card">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        
        <h1>¡Pago Exitoso!</h1>
        <p>Tu suscripción ha sido activada correctamente.</p>
        
        <div class="success-actions">
            <a href="../courses.php" class="btn btn-primary">
                <i class="fas fa-graduation-cap"></i> Ir a mis Cursos
            </a>
            <a href="../account/billing.php" class="btn btn-secondary">
                <i class="fas fa-file-invoice"></i> Ver Factura
            </a>
        </div>
    </div>
</div>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/payment-result.css"> 
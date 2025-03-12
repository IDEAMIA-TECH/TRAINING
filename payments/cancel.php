<?php
require_once '../includes/header.php';

if (!is_logged_in()) {
    header("Location: ../login.php");
    exit();
}
?>

<div class="cancel-container">
    <div class="cancel-card">
        <div class="cancel-icon">
            <i class="fas fa-times-circle"></i>
        </div>
        
        <h1>Pago Cancelado</h1>
        <p>El proceso de pago ha sido cancelado. No se ha realizado ningún cargo.</p>
        
        <div class="cancel-actions">
            <a href="plans.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Volver a Planes
            </a>
            <a href="../contact.php" class="btn btn-secondary">
                <i class="fas fa-question-circle"></i> Necesito Ayuda
            </a>
        </div>
    </div>
</div>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/payment-result.css"> 
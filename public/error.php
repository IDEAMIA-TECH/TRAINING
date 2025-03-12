<?php 
require_once '../includes/init.php';

// Obtener el código de error y mensaje
$error_code = $_GET['code'] ?? '404';
$error_message = $_GET['message'] ?? 'Página no encontrada';

// Establecer el código de estado HTTP correcto
http_response_code(intval($error_code));
?>

<?php require_once '../templates/header.php'; ?>

<div class="error-container">
    <div class="error-content">
        <h1><?php echo $error_code; ?></h1>
        <h2><?php echo $error_message; ?></h2>
        <p>Lo sentimos, la página que buscas no está disponible.</p>
        <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-primary">Volver al Inicio</a>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?> 
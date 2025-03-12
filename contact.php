<?php
require_once 'includes/header.php';

$error = '';
$success = '';

// Obtener configuración de contacto
$settings_query = "SELECT * FROM settings WHERE setting_key IN ('contact_email', 'contact_phone')";
$settings = $conn->query($settings_query)->fetchAll(PDO::FETCH_KEY_PAIR);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $subject = sanitize_input($_POST['subject']);
    $message = sanitize_input($_POST['message']);
    
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = "Por favor completa todos los campos";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Por favor ingresa un email válido";
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO contact_messages (name, email, subject, message, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            if ($stmt->execute([$name, $email, $subject, $message])) {
                // Enviar email de notificación
                $to = $settings['contact_email'];
                $email_subject = "Nuevo mensaje de contacto: " . $subject;
                $email_body = "Nombre: $name\n";
                $email_body .= "Email: $email\n\n";
                $email_body .= "Mensaje:\n$message";
                
                $headers = "From: $email\r\n";
                $headers .= "Reply-To: $email\r\n";
                
                mail($to, $email_subject, $email_body, $headers);
                
                $success = "Mensaje enviado exitosamente. Te contactaremos pronto.";
                
                // Limpiar formulario
                $name = $email = $subject = $message = '';
            }
        } catch (PDOException $e) {
            $error = "Error al enviar el mensaje. Por favor intenta nuevamente.";
        }
    }
}
?>

<div class="contact-container">
    <div class="contact-info">
        <h2>Contáctanos</h2>
        <p>¿Tienes alguna pregunta? No dudes en contactarnos.</p>
        
        <div class="info-items">
            <?php if (!empty($settings['contact_email'])): ?>
                <div class="info-item">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <h3>Email</h3>
                        <p><?php echo htmlspecialchars($settings['contact_email']); ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($settings['contact_phone'])): ?>
                <div class="info-item">
                    <i class="fas fa-phone"></i>
                    <div>
                        <h3>Teléfono</h3>
                        <p><?php echo htmlspecialchars($settings['contact_phone']); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="contact-form-container">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" action="" class="contact-form">
            <div class="form-group">
                <label for="name">Nombre *</label>
                <input type="text" id="name" name="name" required
                       value="<?php echo htmlspecialchars($name ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required
                       value="<?php echo htmlspecialchars($email ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="subject">Asunto *</label>
                <input type="text" id="subject" name="subject" required
                       value="<?php echo htmlspecialchars($subject ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="message">Mensaje *</label>
                <textarea id="message" name="message" rows="5" required><?php 
                    echo htmlspecialchars($message ?? ''); 
                ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enviar Mensaje</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 
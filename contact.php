<?php
require_once 'includes/header.php';

$success = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar campos
        if (empty($_POST['name']) || empty($_POST['email']) || empty($_POST['message'])) {
            throw new Exception('Todos los campos son requeridos');
        }
        
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email inválido');
        }
        
        // Preparar datos
        $data = [
            'name' => trim($_POST['name']),
            'email' => trim($_POST['email']),
            'subject' => trim($_POST['subject'] ?? ''),
            'message' => trim($_POST['message']),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Guardar en base de datos
        $stmt = $conn->prepare("
            INSERT INTO contact_messages (name, email, subject, message, created_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['name'],
            $data['email'],
            $data['subject'],
            $data['message'],
            $data['created_at']
        ]);
        
        // Enviar email de notificación
        $to = ADMIN_EMAIL;
        $subject = "Nuevo mensaje de contacto: " . $data['subject'];
        $message = "Nombre: {$data['name']}\n";
        $message .= "Email: {$data['email']}\n\n";
        $message .= "Mensaje:\n{$data['message']}";
        $headers = "From: {$data['email']}\r\n";
        $headers .= "Reply-To: {$data['email']}\r\n";
        
        mail($to, $subject, $message, $headers);
        
        $success = true;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="contact-container">
    <div class="contact-info">
        <h1>Contáctanos</h1>
        <p>¿Tienes alguna pregunta o comentario? No dudes en contactarnos.</p>
        
        <div class="contact-methods">
            <div class="contact-method">
                <i class="fas fa-envelope"></i>
                <h3>Email</h3>
                <p><?php echo CONTACT_EMAIL; ?></p>
            </div>
            
            <div class="contact-method">
                <i class="fas fa-phone"></i>
                <h3>Teléfono</h3>
                <p><?php echo CONTACT_PHONE; ?></p>
            </div>
            
            <div class="contact-method">
                <i class="fas fa-map-marker-alt"></i>
                <h3>Dirección</h3>
                <p><?php echo CONTACT_ADDRESS; ?></p>
            </div>
        </div>
    </div>
    
    <div class="contact-form-container">
        <?php if ($success): ?>
            <div class="alert alert-success">
                Tu mensaje ha sido enviado correctamente. Te contactaremos pronto.
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="contact-form">
            <div class="form-group">
                <label for="name">Nombre *</label>
                <input type="text" id="name" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="subject">Asunto</label>
                <input type="text" id="subject" name="subject">
            </div>
            
            <div class="form-group">
                <label for="message">Mensaje *</label>
                <textarea id="message" name="message" rows="5" required></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">
                Enviar Mensaje
            </button>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 
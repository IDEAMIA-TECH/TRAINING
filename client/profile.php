<?php
require_once '../includes/header.php';

if (!is_logged_in() || is_admin()) {
    header("Location: ../login.php");
    exit();
}

$error = '';
$success = '';
$user_id = $_SESSION['user_id'];

// Obtener información del usuario
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Procesar actualización de perfil
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    try {
        // Validar email único
        if ($email !== $user['email']) {
            $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_email->execute([$email, $user_id]);
            if ($check_email->fetch()) {
                throw new Exception("El email ya está registrado");
            }
        }
        
        // Iniciar transacción
        $conn->beginTransaction();
        
        // Actualizar información básica
        $update_query = "UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->execute([$name, $email, $phone, $user_id]);
        
        // Actualizar contraseña si se proporcionó
        if (!empty($current_password)) {
            if (!password_verify($current_password, $user['password'])) {
                throw new Exception("La contraseña actual es incorrecta");
            }
            
            if (empty($new_password) || empty($confirm_password)) {
                throw new Exception("Debes proporcionar la nueva contraseña y su confirmación");
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception("Las contraseñas no coinciden");
            }
            
            if (strlen($new_password) < 6) {
                throw new Exception("La contraseña debe tener al menos 6 caracteres");
            }
            
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_password = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $conn->prepare($update_password);
            $stmt->execute([$hashed_password, $user_id]);
        }
        
        $conn->commit();
        $success = "Perfil actualizado exitosamente";
        
        // Actualizar información en sesión
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        
        // Recargar información del usuario
        $stmt = $conn->prepare($user_query);
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $conn->rollBack();
        $error = $e->getMessage();
    }
}
?>

<div class="client-container">
    <?php require_once '../includes/client_sidebar.php'; ?>

    <div class="client-content">
        <div class="content-header">
            <h2>Mi Perfil</h2>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="profile-container">
            <form method="POST" action="" class="profile-form">
                <div class="form-section">
                    <h3>Información Personal</h3>
                    
                    <div class="form-group">
                        <label for="name">Nombre Completo *</label>
                        <input type="text" id="name" name="name" required
                               value="<?php echo htmlspecialchars($user['name']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required
                               value="<?php echo htmlspecialchars($user['email']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="phone">Teléfono</label>
                        <input type="tel" id="phone" name="phone"
                               value="<?php echo htmlspecialchars($user['phone']); ?>">
                    </div>
                </div>

                <div class="form-section">
                    <h3>Cambiar Contraseña</h3>
                    <p class="form-text">Deja estos campos en blanco si no deseas cambiar tu contraseña</p>
                    
                    <div class="form-group">
                        <label for="current_password">Contraseña Actual</label>
                        <input type="password" id="current_password" name="current_password">
                    </div>

                    <div class="form-group">
                        <label for="new_password">Nueva Contraseña</label>
                        <input type="password" id="new_password" name="new_password"
                               minlength="6">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirmar Nueva Contraseña</label>
                        <input type="password" id="confirm_password" name="confirm_password"
                               minlength="6">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 
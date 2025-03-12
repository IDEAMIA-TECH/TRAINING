<?php
require_once '../includes/init.php';

if (!$user_authenticated) {
    redirect('/login.php');
}

$errors = [];
$success = false;

try {
    // Obtener información actual del usuario
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        redirect('/login.php');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validaciones
        if (empty($name)) {
            $errors['name'] = 'El nombre es requerido';
        }

        if (empty($email)) {
            $errors['email'] = 'El email es requerido';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email inválido';
        } elseif ($email !== $user['email']) {
            // Verificar si el nuevo email ya está en uso
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $errors['email'] = 'Este email ya está registrado';
            }
        }

        // Validar contraseña solo si se está intentando cambiar
        if (!empty($new_password)) {
            if (empty($current_password)) {
                $errors['current_password'] = 'Debes ingresar tu contraseña actual';
            } elseif (!password_verify($current_password, $user['password'])) {
                $errors['current_password'] = 'La contraseña actual es incorrecta';
            }

            if (strlen($new_password) < 6) {
                $errors['new_password'] = 'La nueva contraseña debe tener al menos 6 caracteres';
            } elseif ($new_password !== $confirm_password) {
                $errors['confirm_password'] = 'Las contraseñas no coinciden';
            }
        }

        // Si no hay errores, actualizar perfil
        if (empty($errors)) {
            $db->beginTransaction();

            try {
                // Actualizar información básica
                $stmt = $db->prepare("
                    UPDATE users 
                    SET name = ?, 
                        email = ?, 
                        phone = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$name, $email, $phone, $_SESSION['user_id']]);

                // Actualizar contraseña si se proporcionó una nueva
                if (!empty($new_password)) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                }

                $db->commit();
                $success = true;
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = "Error al actualizar el perfil: " . $e->getMessage();
            }
        }
    }
} catch (Exception $e) {
    $errors[] = $e->getMessage();
}
?>

<?php require_once '../templates/header.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Editar Perfil</h4>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            Perfil actualizado exitosamente
                            <script>
                                setTimeout(function() {
                                    window.location.href = '<?php echo BASE_URL; ?>/profile.php';
                                }, 2000);
                            </script>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $key => $error): ?>
                                    <li><?php echo is_array($error) ? implode(', ', $error) : $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone']); ?>">
                        </div>

                        <hr>

                        <h5>Cambiar Contraseña</h5>
                        <p class="text-muted small">Deja estos campos en blanco si no deseas cambiar tu contraseña</p>

                        <div class="mb-3">
                            <label for="current_password" class="form-label">Contraseña Actual</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">Nueva Contraseña</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirmar Nueva Contraseña</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="<?php echo BASE_URL; ?>/profile.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?> 
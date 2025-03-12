<?php
require_once '../includes/init.php';

if ($user_authenticated) {
    redirect('index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize_input($_POST['name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Debug log
    error_log("Iniciando proceso de registro para email: " . $email);

    // Validaciones
    if (empty($name)) {
        $errors[] = "El nombre es requerido";
    }

    if (empty($email)) {
        $errors[] = "El correo electrónico es requerido";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "El correo electrónico no es válido";
    }

    if (empty($password)) {
        $errors[] = "La contraseña es requerida";
    } elseif (strlen($password) < 6) {
        $errors[] = "La contraseña debe tener al menos 6 caracteres";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Las contraseñas no coinciden";
    }

    // Si no hay errores, proceder con el registro
    if (empty($errors)) {
        try {
            // Verificar si el correo ya existe
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "Este correo electrónico ya está registrado";
            } else {
                // Debug log
                error_log("Insertando nuevo usuario en la base de datos");

                // Insertar nuevo usuario
                $stmt = $db->prepare("
                    INSERT INTO users (name, email, password, role, status, created_at) 
                    VALUES (?, ?, ?, 'client', 'active', CURRENT_TIMESTAMP)
                ");
                
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt->execute([
                    $name,
                    $email,
                    $hashed_password
                ]);

                $user_id = $db->lastInsertId();
                
                // Debug log
                error_log("Usuario creado con ID: " . $user_id);

                // Iniciar sesión automáticamente
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $name;
                $_SESSION['role'] = 'client';

                // Debug log
                error_log("Sesión iniciada, redirigiendo a dashboard");

                // Redireccionar al dashboard
                redirect('dashboard.php');
                exit();
            }
        } catch (PDOException $e) {
            error_log("Error en registro - SQL Error: " . $e->getMessage());
            error_log("SQL State: " . $e->errorInfo[0]);
            error_log("Error Code: " . $e->errorInfo[1]);
            error_log("Message: " . $e->errorInfo[2]);
            $errors[] = "Error al registrar usuario. Por favor intente más tarde.";
        } catch (Exception $e) {
            error_log("Error general en registro: " . $e->getMessage());
            $errors[] = "Error inesperado. Por favor intente más tarde.";
        }
    } else {
        error_log("Errores de validación: " . print_r($errors, true));
    }
}
?>

<?php require_once '../templates/header.php'; ?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <img src="<?php echo ASSETS_URL; ?>/images/logo.png" alt="Academee">
            <h1>Crear Cuenta</h1>
            <p>Completa el formulario para registrarte</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form class="auth-form" method="POST" action="">
            <div class="form-group">
                <label for="name">Nombre Completo</label>
                <input type="text" id="name" name="name" required 
                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="email">Correo Electrónico</label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required 
                       minlength="6">
                <small class="password-requirements">La contraseña debe tener al menos 6 caracteres</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirmar Contraseña</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <button type="submit" class="btn btn-primary">Registrarse</button>
        </form>

        <div class="auth-footer">
            <p>¿Ya tienes una cuenta? <a href="login.php">Iniciar Sesión</a></p>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?> 
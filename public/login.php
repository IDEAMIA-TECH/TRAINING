<?php
require_once '../includes/init.php';

if ($user_authenticated) {
    redirect('/');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $errors[] = "Todos los campos son requeridos";
    } else {
        try {
            $stmt = $db->prepare("SELECT id, password, role, name FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                
                if ($user['role'] === 'admin') {
                    redirect('/admin');
                } else {
                    redirect('/profile.php');
                }
            } else {
                $errors[] = "Credenciales inválidas";
            }
        } catch (PDOException $e) {
            $errors[] = "Error al iniciar sesión: " . $e->getMessage();
        }
    }
}
?>

<?php require_once '../templates/header.php'; ?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <img src="<?php echo ASSETS_URL; ?>/images/logo.png" alt="Academee">
            <h1>Iniciar Sesión</h1>
            <p>Ingresa tus credenciales para continuar</p>
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
                <label for="email">Correo Electrónico</label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-primary">Iniciar Sesión</button>
        </form>

        <div class="auth-footer">
            <p>¿No tienes una cuenta? <a href="register.php">Regístrate</a></p>
            <p><a href="forgot-password.php">¿Olvidaste tu contraseña?</a></p>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?> 
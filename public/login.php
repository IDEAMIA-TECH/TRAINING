<?php
require_once __DIR__ . '/../includes/init.php';

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

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Iniciar Sesión</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Iniciar Sesión</button>
                    </form>
                    <hr>
                    <p class="mb-0">¿No tienes cuenta? <a href="<?php echo BASE_URL; ?>/register.php">Regístrate aquí</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?> 
<?php
require_once '../../includes/init.php';

if (!$is_admin) {
    redirect('/login.php');
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    redirect('/admin/categories');
}

$errors = [];

try {
    // Obtener información de la categoría
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        redirect('/admin/categories');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        // Validaciones
        if (empty($name)) {
            $errors['name'] = 'El nombre es requerido';
        }

        if (empty($errors)) {
            $stmt = $db->prepare("
                UPDATE categories 
                SET name = ?, description = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $id]);
            
            redirect('/admin/categories');
        }
    }
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'idx_name') !== false) {
        $errors['name'] = 'Ya existe una categoría con este nombre';
    } else {
        $errors[] = "Error al actualizar la categoría: " . $e->getMessage();
    }
}
?>

<?php require_once '../../templates/admin/header.php'; ?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Editar Categoría</h4>
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
                            <label for="name" class="form-label">Nombre</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($category['name']); ?>" 
                                   required>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Descripción</label>
                            <textarea class="form-control" id="description" name="description" rows="3"
                                    ><?php echo htmlspecialchars($category['description']); ?></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="<?php echo BASE_URL; ?>/admin/categories" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../templates/admin/footer.php'; ?> 
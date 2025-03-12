<?php
require_once '../../includes/init.php';

if (!$is_admin) {
    redirect('/login.php');
}

try {
    // Obtener lista de categorías
    $stmt = $db->prepare("
        SELECT c.*, 
               COUNT(co.id) as courses_count
        FROM categories c
        LEFT JOIN courses co ON c.id = co.category_id
        GROUP BY c.id
        ORDER BY c.name
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<?php require_once '../../templates/admin/header.php'; ?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Gestión de Categorías</h2>
        <a href="<?php echo BASE_URL; ?>/admin/categories/create.php" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nueva Categoría
        </a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Cursos</th>
                            <th>Fecha Creación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No hay categorías registradas</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($category['description'], 0, 100)) . '...'; ?></td>
                                    <td><?php echo $category['courses_count']; ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($category['created_at'])); ?></td>
                                    <td>
                                        <a href="edit.php?id=<?php echo $category['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($category['courses_count'] == 0): ?>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    onclick="deleteCategory(<?php echo $category['id']; ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function deleteCategory(id) {
    if (confirm('¿Estás seguro de eliminar esta categoría?')) {
        fetch('<?php echo BASE_URL; ?>/admin/categories/delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + id
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al eliminar la categoría');
        });
    }
}
</script>

<?php require_once '../../templates/admin/footer.php'; ?> 
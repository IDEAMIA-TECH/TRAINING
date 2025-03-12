<?php
require_once '../../includes/init.php';

// Verificar que el usuario sea administrador
if (!$is_admin) {
    redirect('/login.php');
}

try {
    // Obtener todos los cursos
    $stmt = $db->prepare("
        SELECT c.*, COUNT(DISTINCT cr.id) as registered_students 
        FROM courses c 
        LEFT JOIN course_registrations cr ON c.id = cr.course_id 
        GROUP BY c.id 
        ORDER BY c.start_date DESC
    ");
    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al cargar los cursos: " . $e->getMessage();
}
?>

<?php require_once '../../templates/admin/header.php'; ?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Gestión de Cursos</h2>
        <a href="<?php echo BASE_URL; ?>/admin/courses/create.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nuevo Curso
        </a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Título</th>
                                <th>Fecha</th>
                                <th>Capacidad</th>
                                <th>Inscritos</th>
                                <th>Precio</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td><?php echo $course['id']; ?></td>
                                    <td><?php echo htmlspecialchars($course['title']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($course['start_date'])); ?></td>
                                    <td><?php echo $course['capacity']; ?></td>
                                    <td><?php echo $course['registered_students']; ?></td>
                                    <td>$<?php echo number_format($course['price'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $course['status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo $course['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="edit.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="students.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="bi bi-people"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                onclick="deleteCourse(<?php echo $course['id']; ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function deleteCourse(id) {
    if (confirm('¿Estás seguro de que deseas eliminar este curso?')) {
        fetch('<?php echo BASE_URL; ?>/admin/courses/delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error al eliminar el curso: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al procesar la solicitud');
        });
    }
}
</script>

<?php require_once '../../templates/admin/footer.php'; ?> 
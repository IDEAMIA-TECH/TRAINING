<?php
require_once '../../includes/header.php';

if (!is_admin()) {
    header("Location: ../../login.php");
    exit();
}

$error = '';
$success = '';

// Procesar acciones de usuario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
    $action = sanitize_input($_POST['action']);
    
    try {
        if ($action === 'delete') {
            // Verificar si el usuario tiene inscripciones
            $stmt = $conn->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ?");
            $stmt->execute([$user_id]);
            if ($stmt->fetchColumn() > 0) {
                $error = "No se puede eliminar el usuario porque tiene inscripciones activas";
            } else {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                if ($stmt->execute([$user_id])) {
                    $success = "Usuario eliminado exitosamente";
                }
            }
        } elseif ($action === 'toggle_status') {
            $stmt = $conn->prepare("UPDATE users SET status = NOT status WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                $success = "Estado del usuario actualizado exitosamente";
            }
        }
    } catch(PDOException $e) {
        $error = "Error al procesar la acción: " . $e->getMessage();
    }
}

// Paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Búsqueda
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$where_clause = "WHERE role = 'client'";
if ($search) {
    $where_clause .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
}

// Obtener total de usuarios
$count_query = "SELECT COUNT(*) FROM users " . $where_clause;
$stmt = $conn->prepare($count_query);
if ($search) {
    $search_param = "%$search%";
    $stmt->execute([$search_param, $search_param, $search_param]);
} else {
    $stmt->execute();
}
$total_users = $stmt->fetchColumn();
$total_pages = ceil($total_users / $per_page);

// Obtener usuarios
$users_query = "
    SELECT u.*, 
           COUNT(e.id) as total_enrollments,
           SUM(CASE WHEN e.payment_status = 'completed' THEN 1 ELSE 0 END) as active_enrollments
    FROM users u
    LEFT JOIN enrollments e ON u.id = e.user_id
    {$where_clause}
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($users_query);
$params = $search ? 
    [$search_param, $search_param, $search_param, $per_page, $offset] : 
    [$per_page, $offset];
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-container">
    <div class="admin-sidebar">
        <h3>Panel de Administración</h3>
        <nav>
            <a href="../dashboard.php">Dashboard</a>
            <a href="../courses/">Cursos</a>
            <a href="../payments/">Pagos</a>
            <a href="index.php" class="active">Usuarios</a>
            <a href="../reports/">Reportes</a>
        </nav>
    </div>

    <div class="admin-content">
        <div class="content-header">
            <h2>Gestión de Usuarios</h2>
            <form action="" method="GET" class="search-form">
                <input type="text" name="search" placeholder="Buscar usuarios..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">Buscar</button>
            </form>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="users-table">
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Contacto</th>
                        <th>Inscripciones</th>
                        <th>Registro</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td>
                                <div><?php echo htmlspecialchars($user['email']); ?></div>
                                <?php if ($user['phone']): ?>
                                    <div class="text-muted">
                                        <?php echo htmlspecialchars($user['phone']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div>Total: <?php echo $user['total_enrollments']; ?></div>
                                <div>Activas: <?php echo $user['active_enrollments']; ?></div>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <span class="status-badge <?php echo $user['status'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $user['status'] ? 'Activo' : 'Inactivo'; ?>
                                </span>
                            </td>
                            <td class="actions">
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <button type="submit" class="btn btn-secondary btn-sm">
                                        <?php echo $user['status'] ? 'Desactivar' : 'Activar'; ?>
                                    </button>
                                </form>
                                
                                <?php if ($user['total_enrollments'] == 0): ?>
                                    <form method="POST" action="" class="d-inline">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-danger btn-sm"
                                                onclick="return confirm('¿Estás seguro de eliminar este usuario?')">
                                            Eliminar
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <a href="view.php?id=<?php echo $user['id']; ?>" 
                                   class="btn btn-info btn-sm">
                                    Ver Detalles
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                       class="<?php echo $page === $i ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?> 
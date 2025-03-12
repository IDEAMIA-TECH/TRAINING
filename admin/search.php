<?php
require_once '../includes/init.php';

if (!$is_admin) {
    redirect('/login.php');
}

try {
    $search = trim($_GET['q'] ?? '');
    $type = $_GET['type'] ?? 'all';
    $status = $_GET['status'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';

    // Configuración de paginación
    $records_per_page = 10;
    $current_page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($current_page - 1) * $records_per_page;

    $params = [];
    $conditions = [];

    // Construir consulta base según el tipo
    switch ($type) {
        case 'users':
            $query = "
                SELECT 'user' as type, 
                       u.id,
                       u.name,
                       u.email,
                       u.status,
                       u.created_at,
                       COUNT(cr.id) as registrations
                FROM users u
                LEFT JOIN course_registrations cr ON u.id = cr.user_id
                WHERE u.role = 'client'
            ";
            if ($search) {
                $conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
                $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
            }
            if ($status) {
                $conditions[] = "u.status = ?";
                $params[] = $status;
            }
            $query .= $conditions ? " AND " . implode(" AND ", $conditions) : "";
            $query .= " GROUP BY u.id ORDER BY u.created_at DESC";
            $query .= " LIMIT ? OFFSET ?";
            $params[] = $records_per_page;
            $params[] = $offset;

            // Consulta para el total de registros
            $count_query = str_replace("SELECT 'user' as type,", "SELECT COUNT(DISTINCT u.id) as total", $query);
            $count_query = preg_replace("/LIMIT.*$/", "", $count_query);
            break;

        case 'courses':
            $query = "
                SELECT 'course' as type,
                       c.id,
                       c.title as name,
                       c.status,
                       c.start_date as date,
                       c.price,
                       COUNT(cr.id) as registrations
                FROM courses c
                LEFT JOIN course_registrations cr ON c.id = cr.course_id
            ";
            if ($search) {
                $conditions[] = "(c.title LIKE ? OR c.description LIKE ?)";
                $params = array_merge($params, ["%$search%", "%$search%"]);
            }
            if ($status) {
                $conditions[] = "c.status = ?";
                $params[] = $status;
            }
            if ($date_from) {
                $conditions[] = "c.start_date >= ?";
                $params[] = $date_from;
            }
            if ($date_to) {
                $conditions[] = "c.start_date <= ?";
                $params[] = $date_to;
            }
            $query .= $conditions ? " WHERE " . implode(" AND ", $conditions) : "";
            $query .= " GROUP BY c.id ORDER BY c.start_date DESC";
            break;

        case 'payments':
            $query = "
                SELECT 'payment' as type,
                       p.id,
                       CONCAT(u.name, ' - ', c.title) as name,
                       p.status,
                       p.created_at as date,
                       p.amount,
                       p.payment_method
                FROM payments p
                JOIN users u ON p.user_id = u.id
                JOIN courses c ON p.course_id = c.id
            ";
            if ($search) {
                $conditions[] = "(u.name LIKE ? OR c.title LIKE ? OR p.transaction_id LIKE ?)";
                $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
            }
            if ($status) {
                $conditions[] = "p.status = ?";
                $params[] = $status;
            }
            if ($date_from) {
                $conditions[] = "p.created_at >= ?";
                $params[] = $date_from;
            }
            if ($date_to) {
                $conditions[] = "p.created_at <= ?";
                $params[] = $date_to;
            }
            $query .= $conditions ? " WHERE " . implode(" AND ", $conditions) : "";
            $query .= " ORDER BY p.created_at DESC";
            break;

        default: // all
            // Combinar resultados de todas las búsquedas
            $queries = [];
            
            // Usuarios
            if (!$status || $status === 'active' || $status === 'inactive') {
                $queries[] = "
                    SELECT 'user' as type, 
                           id,
                           name,
                           email as description,
                           status,
                           created_at as date
                    FROM users
                    WHERE role = 'client'
                    AND (name LIKE ? OR email LIKE ?)
                    " . ($status ? "AND status = '$status'" : "");
                $params = array_merge($params, ["%$search%", "%$search%"]);
            }
            
            // Cursos
            if (!$status || $status === 'active' || $status === 'inactive') {
                $queries[] = "
                    SELECT 'course' as type,
                           id,
                           title as name,
                           description,
                           status,
                           start_date as date
                    FROM courses
                    WHERE (title LIKE ? OR description LIKE ?)
                    " . ($status ? "AND status = '$status'" : "");
                $params = array_merge($params, ["%$search%", "%$search%"]);
            }
            
            // Pagos
            if (!$status || $status === 'pending' || $status === 'completed' || $status === 'failed') {
                $queries[] = "
                    SELECT 'payment' as type,
                           p.id,
                           CONCAT(u.name, ' - ', c.title) as name,
                           p.transaction_id as description,
                           p.status,
                           p.created_at as date
                    FROM payments p
                    JOIN users u ON p.user_id = u.id
                    JOIN courses c ON p.course_id = c.id
                    WHERE (u.name LIKE ? OR c.title LIKE ? OR p.transaction_id LIKE ?)
                    " . ($status ? "AND p.status = '$status'" : "");
                $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
            }
            
            $query = implode(" UNION ALL ", $queries) . " ORDER BY date DESC";
    }

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener total de registros para la paginación
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total_records = $stmt->fetchColumn();

    // Crear instancia de paginación
    $pagination = new Pagination($total_records, $records_per_page);

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<?php require_once '../templates/admin/header.php'; ?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="q" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Buscar...">
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="type">
                                <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>Todo</option>
                                <option value="users" <?php echo $type === 'users' ? 'selected' : ''; ?>>Usuarios</option>
                                <option value="courses" <?php echo $type === 'courses' ? 'selected' : ''; ?>>Cursos</option>
                                <option value="payments" <?php echo $type === 'payments' ? 'selected' : ''; ?>>Pagos</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="status">
                                <option value="">Todos los estados</option>
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Activo</option>
                                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
                                <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completado</option>
                                <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Fallido</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Buscar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <?php if (empty($results)): ?>
                    <p class="text-center text-muted">No se encontraron resultados</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Nombre/Título</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $result): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $result['type'] === 'user' ? 'primary' : 
                                                    ($result['type'] === 'course' ? 'success' : 'info'); 
                                            ?>">
                                                <?php echo ucfirst($result['type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($result['name']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $result['status'] === 'active' || $result['status'] === 'completed' ? 'success' : 
                                                    ($result['status'] === 'pending' ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo ucfirst($result['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($result['date'])); ?></td>
                                        <td>
                                            <a href="<?php 
                                                echo BASE_URL . '/admin/' . 
                                                    ($result['type'] === 'user' ? 'users' : 
                                                        ($result['type'] === 'course' ? 'courses' : 'payments')) . 
                                                    '/view.php?id=' . $result['id']; 
                                                ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php echo $pagination->render($_SERVER['PHP_SELF']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../templates/admin/footer.php'; ?> 
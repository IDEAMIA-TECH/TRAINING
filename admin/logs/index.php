<?php
require_once '../../includes/init.php';

if (!$is_admin) {
    redirect('/login.php');
}

try {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $records_per_page = 50;
    $offset = ($page - 1) * $records_per_page;

    // Filtros
    $filters = [];
    $params = [];

    if (!empty($_GET['action'])) {
        $filters[] = "action = ?";
        $params[] = $_GET['action'];
    }

    if (!empty($_GET['entity_type'])) {
        $filters[] = "entity_type = ?";
        $params[] = $_GET['entity_type'];
    }

    if (!empty($_GET['date_from'])) {
        $filters[] = "created_at >= ?";
        $params[] = $_GET['date_from'] . ' 00:00:00';
    }

    if (!empty($_GET['date_to'])) {
        $filters[] = "created_at <= ?";
        $params[] = $_GET['date_to'] . ' 23:59:59';
    }

    // Construir consulta
    $where = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';

    // Obtener total de registros
    $count_query = "SELECT COUNT(*) FROM system_logs $where";
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total_records = $stmt->fetchColumn();

    // Obtener logs
    $query = "
        SELECT l.*, u.name as user_name
        FROM system_logs l
        LEFT JOIN users u ON l.user_id = u.id
        $where
        ORDER BY l.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $db->prepare($query);
    $stmt->execute(array_merge($params, [$records_per_page, $offset]));
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener acciones únicas para el filtro
    $actions = $db->query("SELECT DISTINCT action FROM system_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
    $entity_types = $db->query("SELECT DISTINCT entity_type FROM system_logs ORDER BY entity_type")->fetchAll(PDO::FETCH_COLUMN);

    // Crear paginación
    $pagination = new Pagination($total_records, $records_per_page);

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<?php require_once '../../templates/admin/header.php'; ?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Logs del Sistema</h5>
                </div>
                <div class="card-body">
                    <!-- Filtros -->
                    <form method="GET" class="row g-3 mb-4">
                        <div class="col-md-2">
                            <select name="action" class="form-select">
                                <option value="">Todas las acciones</option>
                                <?php foreach ($actions as $action): ?>
                                    <option value="<?php echo htmlspecialchars($action); ?>"
                                            <?php echo $_GET['action'] === $action ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($action); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="entity_type" class="form-select">
                                <option value="">Todos los tipos</option>
                                <?php foreach ($entity_types as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>"
                                            <?php echo $_GET['entity_type'] === $type ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="date_from" class="form-control" 
                                   value="<?php echo $_GET['date_from'] ?? ''; ?>"
                                   placeholder="Fecha desde">
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="date_to" class="form-control"
                                   value="<?php echo $_GET['date_to'] ?? ''; ?>"
                                   placeholder="Fecha hasta">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                        </div>
                    </form>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Usuario</th>
                                        <th>Acción</th>
                                        <th>Tipo</th>
                                        <th>ID</th>
                                        <th>Detalles</th>
                                        <th>IP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($log['user_name'] ?? 'Sistema'); ?></td>
                                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                                            <td><?php echo htmlspecialchars($log['entity_type']); ?></td>
                                            <td><?php echo $log['entity_id']; ?></td>
                                            <td>
                                                <?php if ($log['details']): ?>
                                                    <button type="button" class="btn btn-sm btn-info" 
                                                            onclick="showDetails('<?php echo htmlspecialchars(addslashes($log['details'])); ?>')">
                                                        Ver
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php echo $pagination->render($_SERVER['PHP_SELF']); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para detalles -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles del Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="detailsContent"></pre>
            </div>
        </div>
    </div>
</div>

<script>
function showDetails(details) {
    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    document.getElementById('detailsContent').textContent = JSON.stringify(JSON.parse(details), null, 2);
    modal.show();
}
</script>

<?php require_once '../../templates/admin/footer.php'; ?> 
<?php
require_once '../../includes/header.php';

if (!is_admin()) {
    header("Location: ../../login.php");
    exit();
}

$error = '';
$success = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message_id'])) {
    $message_id = (int)$_POST['message_id'];
    $action = sanitize_input($_POST['action']);
    
    try {
        if ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id = ?");
            if ($stmt->execute([$message_id])) {
                $success = "Mensaje eliminado exitosamente";
            }
        } elseif ($action === 'mark_read') {
            $stmt = $conn->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ?");
            if ($stmt->execute([$message_id])) {
                $success = "Mensaje marcado como leído";
            }
        }
    } catch (PDOException $e) {
        $error = "Error al procesar la acción: " . $e->getMessage();
    }
}

// Paginación y filtros
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$where_clause = "";
if ($status_filter) {
    $where_clause = "WHERE status = ?";
}

// Obtener total de mensajes
$count_query = "SELECT COUNT(*) FROM contact_messages " . $where_clause;
$stmt = $conn->prepare($count_query);
if ($status_filter) {
    $stmt->execute([$status_filter]);
} else {
    $stmt->execute();
}
$total_messages = $stmt->fetchColumn();
$total_pages = ceil($total_messages / $per_page);

// Obtener mensajes
$messages_query = "
    SELECT * FROM contact_messages 
    {$where_clause}
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($messages_query);
if ($status_filter) {
    $stmt->execute([$status_filter, $per_page, $offset]);
} else {
    $stmt->execute([$per_page, $offset]);
}
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-container">
    <div class="admin-sidebar">
        <h3>Panel de Administración</h3>
        <nav>
            <a href="../dashboard.php">Dashboard</a>
            <a href="../courses/">Cursos</a>
            <a href="../payments/">Pagos</a>
            <a href="../users/">Usuarios</a>
            <a href="../reports/">Reportes</a>
            <a href="index.php" class="active">Mensajes</a>
        </nav>
    </div>

    <div class="admin-content">
        <div class="content-header">
            <h2>Mensajes de Contacto</h2>
            <div class="filters">
                <select onchange="window.location.href='?status=' + this.value">
                    <option value="">Todos los mensajes</option>
                    <option value="unread" <?php echo $status_filter === 'unread' ? 'selected' : ''; ?>>
                        No leídos
                    </option>
                    <option value="read" <?php echo $status_filter === 'read' ? 'selected' : ''; ?>>
                        Leídos
                    </option>
                </select>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="messages-list">
            <?php if (empty($messages)): ?>
                <div class="empty-state">
                    <p>No hay mensajes para mostrar</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $message): ?>
                    <div class="message-card <?php echo $message['status']; ?>">
                        <div class="message-header">
                            <div class="sender-info">
                                <h3><?php echo htmlspecialchars($message['name']); ?></h3>
                                <span class="email"><?php echo htmlspecialchars($message['email']); ?></span>
                            </div>
                            <div class="message-meta">
                                <span class="date">
                                    <?php echo date('d/m/Y H:i', strtotime($message['created_at'])); ?>
                                </span>
                                <span class="status-badge <?php echo $message['status']; ?>">
                                    <?php echo $message['status'] === 'unread' ? 'No leído' : 'Leído'; ?>
                                </span>
                            </div>
                        </div>

                        <div class="message-content">
                            <h4><?php echo htmlspecialchars($message['subject']); ?></h4>
                            <p><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                        </div>

                        <div class="message-actions">
                            <?php if ($message['status'] === 'unread'): ?>
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                    <input type="hidden" name="action" value="mark_read">
                                    <button type="submit" class="btn btn-secondary btn-sm">
                                        Marcar como leído
                                    </button>
                                </form>
                            <?php endif; ?>

                            <a href="mailto:<?php echo htmlspecialchars($message['email']); ?>" 
                               class="btn btn-primary btn-sm">
                                Responder
                            </a>

                            <form method="POST" action="" class="d-inline">
                                <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-danger btn-sm"
                                        onclick="return confirm('¿Estás seguro de eliminar este mensaje?')">
                                    Eliminar
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?>" 
                               class="<?php echo $page === $i ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?> 
<?php
require_once '../../includes/header.php';

if (!is_admin()) {
    header("Location: ../../login.php");
    exit();
}

// Paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filtros
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Construir query
$query = "SELECT * FROM contact_messages WHERE 1=1";
$params = [];

if ($status) {
    $query .= " AND status = ?";
    $params[] = $status;
}

if ($search) {
    $query .= " AND (name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

// Total de registros
$count_stmt = $conn->prepare(str_replace("*", "COUNT(*)", $query));
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Obtener mensajes
$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-container">
    <?php require_once '../sidebar.php'; ?>
    
    <div class="admin-content">
        <div class="messages-container">
            <div class="messages-header">
                <h2>Mensajes de Contacto</h2>
                
                <div class="messages-filters">
                    <form method="GET" class="filter-form">
                        <div class="form-group">
                            <select name="status">
                                <option value="">Todos los estados</option>
                                <option value="new" <?php echo $status === 'new' ? 'selected' : ''; ?>>Nuevos</option>
                                <option value="read" <?php echo $status === 'read' ? 'selected' : ''; ?>>Leídos</option>
                                <option value="replied" <?php echo $status === 'replied' ? 'selected' : ''; ?>>Respondidos</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <input type="text" name="search" placeholder="Buscar..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                    </form>
                </div>
            </div>
            
            <div class="messages-list">
                <?php foreach ($messages as $message): ?>
                    <div class="message-card <?php echo $message['status']; ?>">
                        <div class="message-header">
                            <div class="message-info">
                                <h3><?php echo htmlspecialchars($message['subject'] ?: 'Sin asunto'); ?></h3>
                                <div class="message-meta">
                                    <span class="message-from">
                                        De: <?php echo htmlspecialchars($message['name']); ?> 
                                        (<?php echo htmlspecialchars($message['email']); ?>)
                                    </span>
                                    <span class="message-date">
                                        <?php echo date('d/m/Y H:i', strtotime($message['created_at'])); ?>
                                    </span>
                                    <span class="message-status">
                                        <?php echo ucfirst($message['status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="message-actions">
                                <button class="btn btn-sm btn-secondary" 
                                        onclick="markAsRead(<?php echo $message['id']; ?>)">
                                    <i class="fas fa-check"></i> Marcar como leído
                                </button>
                                <button class="btn btn-sm btn-primary" 
                                        onclick="replyMessage(<?php echo $message['id']; ?>)">
                                    <i class="fas fa-reply"></i> Responder
                                </button>
                                <button class="btn btn-sm btn-danger" 
                                        onclick="deleteMessage(<?php echo $message['id']; ?>)">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            </div>
                        </div>
                        
                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($messages)): ?>
                    <div class="empty-state">
                        <p>No hay mensajes para mostrar</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>" 
                           class="<?php echo $page === $i ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de Respuesta -->
<div class="modal" id="replyModal">
    <div class="modal-content">
        <h3>Responder Mensaje</h3>
        <form id="replyForm">
            <input type="hidden" id="messageId">
            
            <div class="form-group">
                <label for="replyMessage">Mensaje</label>
                <textarea id="replyMessage" name="message" rows="5" required></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enviar</button>
                <button type="button" class="btn btn-secondary" onclick="closeReplyModal()">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/contact-admin.js"></script>

<?php require_once '../../includes/footer.php'; ?> 
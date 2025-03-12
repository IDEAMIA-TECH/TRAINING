<?php
require_once '../../includes/header.php';
require_once '../../includes/ChatManager.php';

if (!has_permission('manage_support')) {
    header("Location: ../../login.php");
    exit();
}

$chat_manager = new ChatManager($conn);

// Actualizar estado del operador
if (isset($_POST['status'])) {
    $chat_manager->updateOperatorStatus(
        $_SESSION['user_id'],
        $_POST['status'] === 'available'
    );
}

// Obtener sesiones activas
$active_sessions = $chat_manager->getActiveSessions();

// Verificar si el usuario actual es operador
$stmt = $conn->prepare("
    SELECT is_available 
    FROM support_operators 
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$operator = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="admin-container">
    <?php require_once '../sidebar.php'; ?>
    
    <div class="admin-content">
        <div class="support-dashboard">
            <div class="dashboard-header">
                <h2>Panel de Soporte</h2>
                
                <div class="operator-status">
                    <form method="POST" class="status-form">
                        <label>
                            <input type="radio" name="status" value="available"
                                   <?php echo $operator['is_available'] ? 'checked' : ''; ?>>
                            Disponible
                        </label>
                        <label>
                            <input type="radio" name="status" value="unavailable"
                                   <?php echo !$operator['is_available'] ? 'checked' : ''; ?>>
                            No Disponible
                        </label>
                    </form>
                </div>
            </div>
            
            <div class="active-sessions">
                <h3>Sesiones Activas</h3>
                
                <?php if (empty($active_sessions)): ?>
                    <p class="no-sessions">No hay sesiones activas</p>
                <?php else: ?>
                    <div class="sessions-grid">
                        <?php foreach ($active_sessions as $session): ?>
                            <div class="session-card" data-session-id="<?php echo $session['id']; ?>">
                                <div class="session-header">
                                    <h4><?php echo htmlspecialchars($session['user_name']); ?></h4>
                                    <span class="unread-badge">
                                        <?php echo $session['unread_count']; ?>
                                    </span>
                                </div>
                                
                                <div class="session-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-clock"></i>
                                        <?php echo time_ago($session['created_at']); ?>
                                    </div>
                                </div>
                                
                                <div class="session-actions">
                                    <button class="btn btn-primary join-chat">
                                        <i class="fas fa-comments"></i> Unirse al Chat
                                    </button>
                                    <button class="btn btn-danger close-chat">
                                        <i class="fas fa-times"></i> Cerrar
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="chat-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Chat con <span id="chat-user-name"></span></h3>
            <button class="modal-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div id="modal-messages" class="chat-messages"></div>
        
        <form id="modal-chat-form" class="chat-form">
            <input type="hidden" name="session_id" id="modal-session-id">
            <div class="chat-input-container">
                <textarea name="message" placeholder="Escribe tu mensaje..." required></textarea>
                <button type="submit">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/support-dashboard.js"></script>

<?php require_once '../../includes/footer.php'; ?> 
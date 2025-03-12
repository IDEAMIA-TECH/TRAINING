<?php
require_once '../includes/header.php';
require_once '../includes/NotificationManager.php';

if (!is_logged_in()) {
    header("Location: ../login.php");
    exit();
}

$notification_manager = new NotificationManager($conn);
$preferences = $notification_manager->getUserPreferences($_SESSION['user_id']);

// Paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Obtener notificaciones
$notifications = $notification_manager->getNotifications($_SESSION['user_id'], $per_page, $offset);

// Total de notificaciones para paginación
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_notifications / $per_page);
?>

<div class="notifications-container">
    <div class="notifications-header">
        <h1>Centro de Notificaciones</h1>
        
        <div class="notification-actions">
            <button onclick="markAllAsRead()" class="btn btn-secondary">
                <i class="fas fa-check-double"></i> Marcar todas como leídas
            </button>
            <button onclick="showPreferences()" class="btn btn-primary">
                <i class="fas fa-cog"></i> Preferencias
            </button>
        </div>
    </div>
    
    <div class="notifications-list">
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <p>No tienes notificaciones</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
                <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>"
                     data-id="<?php echo $notification['id']; ?>">
                    <div class="notification-icon">
                        <?php echo getNotificationIcon($notification['type']); ?>
                    </div>
                    
                    <div class="notification-content">
                        <h3><?php echo htmlspecialchars($notification['title']); ?></h3>
                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                        <div class="notification-meta">
                            <span class="notification-time">
                                <?php echo timeAgo($notification['created_at']); ?>
                            </span>
                            <?php if ($notification['link']): ?>
                                <a href="<?php echo $notification['link']; ?>" class="notification-link">
                                    Ver más
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="notification-actions">
                        <?php if (!$notification['is_read']): ?>
                            <button onclick="markAsRead(<?php echo $notification['id']; ?>)" 
                                    class="btn btn-sm btn-secondary">
                                <i class="fas fa-check"></i>
                            </button>
                        <?php endif; ?>
                        <button onclick="deleteNotification(<?php echo $notification['id']; ?>)" 
                                class="btn btn-sm btn-danger">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" 
                           class="<?php echo $page === $i ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de Preferencias -->
<div class="modal" id="preferencesModal">
    <div class="modal-content">
        <h3>Preferencias de Notificación</h3>
        <form id="preferencesForm">
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="email_notifications" 
                           <?php echo $preferences['email_notifications'] ? 'checked' : ''; ?>>
                    Recibir notificaciones por email
                </label>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="browser_notifications"
                           <?php echo $preferences['browser_notifications'] ? 'checked' : ''; ?>>
                    Recibir notificaciones del navegador
                </label>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Guardar</button>
                <button type="button" class="btn btn-secondary" onclick="closePreferencesModal()">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<?php
function getNotificationIcon($type) {
    $icons = [
        'info' => '<i class="fas fa-info-circle text-info"></i>',
        'success' => '<i class="fas fa-check-circle text-success"></i>',
        'warning' => '<i class="fas fa-exclamation-triangle text-warning"></i>',
        'error' => '<i class="fas fa-times-circle text-danger"></i>'
    ];
    return $icons[$type] ?? $icons['info'];
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return "Hace un momento";
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return "Hace $mins minutos";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "Hace $hours horas";
    } else {
        $days = floor($diff / 86400);
        return "Hace $days días";
    }
}
?>

<script src="<?php echo BASE_URL; ?>/assets/js/notifications.js"></script>

<?php require_once '../includes/footer.php'; ?> 
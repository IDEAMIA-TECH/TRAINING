<?php
$notification_manager = new NotificationManager($conn);
$unread_count = $notification_manager->getUnreadCount($_SESSION['user_id']);
?>

<div class="notification-dropdown">
    <button class="notification-btn" onclick="toggleNotifications()">
        <i class="fas fa-bell"></i>
        <?php if ($unread_count > 0): ?>
            <span class="notification-counter"><?php echo $unread_count; ?></span>
        <?php endif; ?>
    </button>
    
    <div class="notification-menu" id="notificationMenu">
        <div class="notification-header">
            <h3>Notificaciones</h3>
            <?php if ($unread_count > 0): ?>
                <button onclick="markAllAsRead()" class="btn btn-sm btn-secondary">
                    Marcar todas como leídas
                </button>
            <?php endif; ?>
        </div>
        
        <div class="notification-list">
            <?php
            $notifications = $notification_manager->getNotifications($_SESSION['user_id'], 5);
            if (empty($notifications)):
            ?>
                <div class="empty-state">
                    <p>No hay notificaciones</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>"
                         data-id="<?php echo $notification['id']; ?>">
                        <div class="notification-content">
                            <h4><?php echo htmlspecialchars($notification['title']); ?></h4>
                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                            <div class="notification-meta">
                                <span class="notification-time">
                                    <?php echo timeAgo($notification['created_at']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <a href="/notifications" class="view-all">
                    Ver todas las notificaciones
                </a>
            <?php endif; ?>
        </div>
    </div>
</div> 
<?php
class NotificationManager {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function createNotification($user_id, $title, $message, $type = 'info', $link = null) {
        $stmt = $this->conn->prepare("
            INSERT INTO notifications (user_id, title, message, type, link)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([$user_id, $title, $message, $type, $link]);
    }
    
    public function createNotificationFromTemplate($user_id, $template_code, $replacements = [], $link = null) {
        $stmt = $this->conn->prepare("
            SELECT * FROM notification_templates WHERE code = ?
        ");
        $stmt->execute([$template_code]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$template) {
            throw new Exception("Plantilla no encontrada");
        }
        
        $title = $template['title'];
        $message = $template['message'];
        
        // Reemplazar variables en el título y mensaje
        foreach ($replacements as $key => $value) {
            $title = str_replace("{{$key}}", $value, $title);
            $message = str_replace("{{$key}}", $value, $message);
        }
        
        return $this->createNotification(
            $user_id,
            $title,
            $message,
            $template['type'],
            $link
        );
    }
    
    public function getUnreadCount($user_id) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM notifications 
            WHERE user_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    public function getNotifications($user_id, $limit = 20, $offset = 0) {
        $stmt = $this->conn->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$user_id, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function markAsRead($notification_id, $user_id) {
        $stmt = $this->conn->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE id = ? AND user_id = ?
        ");
        return $stmt->execute([$notification_id, $user_id]);
    }
    
    public function markAllAsRead($user_id) {
        $stmt = $this->conn->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE user_id = ?
        ");
        return $stmt->execute([$user_id]);
    }
    
    public function deleteNotification($notification_id, $user_id) {
        $stmt = $this->conn->prepare("
            DELETE FROM notifications 
            WHERE id = ? AND user_id = ?
        ");
        return $stmt->execute([$notification_id, $user_id]);
    }
    
    public function getUserPreferences($user_id) {
        $stmt = $this->conn->prepare("
            SELECT * FROM notification_preferences 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function updatePreferences($user_id, $email_notifications, $browser_notifications) {
        $stmt = $this->conn->prepare("
            INSERT INTO notification_preferences (user_id, email_notifications, browser_notifications)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
            email_notifications = VALUES(email_notifications),
            browser_notifications = VALUES(browser_notifications)
        ");
        return $stmt->execute([$user_id, $email_notifications, $browser_notifications]);
    }
    
    public function sendEmailNotification($user_id, $title, $message) {
        $stmt = $this->conn->prepare("
            SELECT u.email, np.email_notifications
            FROM users u
            LEFT JOIN notification_preferences np ON u.id = np.user_id
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !$user['email_notifications']) {
            return false;
        }
        
        $headers = "From: " . ADMIN_EMAIL . "\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        $email_body = $this->getEmailTemplate($title, $message);
        
        return mail($user['email'], $title, $email_body, $headers);
    }
    
    private function getEmailTemplate($title, $message) {
        return "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #f8f9fa; padding: 20px; text-align: center; }
                    .content { padding: 20px; }
                    .footer { text-align: center; padding: 20px; color: #6c757d; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>{$title}</h2>
                    </div>
                    <div class='content'>
                        {$message}
                    </div>
                    <div class='footer'>
                        Este es un mensaje automático, por favor no responder.
                    </div>
                </div>
            </body>
            </html>
        ";
    }
} 
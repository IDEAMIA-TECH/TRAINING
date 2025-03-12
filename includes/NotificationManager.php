<?php
class NotificationManager {
    private $conn;
    private $mailer;
    
    public function __construct($conn, $mailer = null) {
        $this->conn = $conn;
        $this->mailer = $mailer;
    }
    
    public function createNotification($data) {
        $this->conn->beginTransaction();
        
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO notifications (
                    user_id, type, title,
                    message, data
                ) VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['user_id'],
                $data['type'],
                $data['title'],
                $data['message'],
                json_encode($data['data'] ?? null)
            ]);
            
            $notificationId = $this->conn->lastInsertId();
            
            // Verificar preferencias de email
            $prefs = $this->getPreferences($data['user_id'], $data['type']);
            
            if ($prefs && $prefs['email_enabled'] && $this->mailer) {
                $this->sendEmailNotification($data);
                
                $stmt = $this->conn->prepare("
                    UPDATE notifications
                    SET is_email_sent = TRUE
                    WHERE id = ?
                ");
                $stmt->execute([$notificationId]);
            }
            
            $this->conn->commit();
            return $notificationId;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    public function getNotifications($userId, $onlyUnread = false, $limit = 50) {
        $sql = "
            SELECT *
            FROM notifications
            WHERE user_id = ?
        ";
        
        if ($onlyUnread) {
            $sql .= " AND is_read = FALSE";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function markAsRead($notificationId, $userId) {
        $stmt = $this->conn->prepare("
            UPDATE notifications
            SET is_read = TRUE,
                read_at = CURRENT_TIMESTAMP
            WHERE id = ?
            AND user_id = ?
        ");
        
        return $stmt->execute([$notificationId, $userId]);
    }
    
    public function markAllAsRead($userId) {
        $stmt = $this->conn->prepare("
            UPDATE notifications
            SET is_read = TRUE,
                read_at = CURRENT_TIMESTAMP
            WHERE user_id = ?
            AND is_read = FALSE
        ");
        
        return $stmt->execute([$userId]);
    }
    
    public function updatePreferences($userId, $preferences) {
        foreach ($preferences as $type => $enabled) {
            $stmt = $this->conn->prepare("
                INSERT INTO notification_preferences (
                    user_id, type,
                    email_enabled, web_enabled
                ) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    email_enabled = VALUES(email_enabled),
                    web_enabled = VALUES(web_enabled)
            ");
            
            $stmt->execute([
                $userId,
                $type,
                $enabled['email'] ?? true,
                $enabled['web'] ?? true
            ]);
        }
    }
    
    public function getPreferences($userId, $type = null) {
        $sql = "
            SELECT *
            FROM notification_preferences
            WHERE user_id = ?
        ";
        
        if ($type) {
            $sql .= " AND type = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$userId, $type]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function sendEmailNotification($data) {
        if (!$this->mailer) return false;
        
        // Obtener información del usuario
        $stmt = $this->conn->prepare("
            SELECT name, email
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$data['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) return false;
        
        return $this->mailer->send(
            $user['email'],
            $data['title'],
            $this->formatEmailMessage($data, $user)
        );
    }
    
    private function formatEmailMessage($data, $user) {
        // Implementar plantilla de email según el tipo de notificación
        $template = "Hola {$user['name']},\n\n{$data['message']}";
        
        if (!empty($data['data'])) {
            $template .= "\n\nDetalles adicionales:\n";
            foreach ($data['data'] as $key => $value) {
                $template .= "- $key: $value\n";
            }
        }
        
        return $template;
    }
    
    public function getUnreadCount($userId) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE user_id = ?
            AND is_read = FALSE
        ");
        
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }
} 
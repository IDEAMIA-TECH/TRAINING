<?php
class ContactManager {
    private $conn;
    private $mailer;
    
    public function __construct($conn, $mailer = null) {
        $this->conn = $conn;
        $this->mailer = $mailer;
    }
    
    public function submitMessage($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO contact_messages (
                name, email, subject,
                message, ip_address,
                user_agent, user_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $data['name'],
            $data['email'],
            $data['subject'],
            $data['message'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $data['user_id'] ?? null
        ]);
    }
    
    public function getMessages($filters = [], $page = 1, $limit = 50) {
        $sql = "
            SELECT 
                cm.*,
                u.name as user_name,
                u.email as user_email,
                (SELECT COUNT(*) FROM contact_replies WHERE message_id = cm.id) as replies_count
            FROM contact_messages cm
            LEFT JOIN users u ON cm.user_id = u.id
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND cm.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['email'])) {
            $sql .= " AND cm.email LIKE ?";
            $params[] = "%{$filters['email']}%";
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (cm.subject LIKE ? OR cm.message LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }
        
        $sql .= " ORDER BY cm.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = ($page - 1) * $limit;
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getMessage($id) {
        $stmt = $this->conn->prepare("
            SELECT 
                cm.*,
                u.name as user_name,
                u.email as user_email
            FROM contact_messages cm
            LEFT JOIN users u ON cm.user_id = u.id
            WHERE cm.id = ?
        ");
        
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getReplies($messageId) {
        $stmt = $this->conn->prepare("
            SELECT 
                cr.*,
                u.name as sent_by_name,
                u.email as sent_by_email
            FROM contact_replies cr
            JOIN users u ON cr.sent_by = u.id
            WHERE cr.message_id = ?
            ORDER BY cr.sent_at ASC
        ");
        
        $stmt->execute([$messageId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function reply($messageId, $data, $userId) {
        $this->conn->beginTransaction();
        
        try {
            // Insertar respuesta
            $stmt = $this->conn->prepare("
                INSERT INTO contact_replies (
                    message_id, subject,
                    content, sent_by
                ) VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $messageId,
                $data['subject'],
                $data['content'],
                $userId
            ]);
            
            // Actualizar estado del mensaje
            $stmt = $this->conn->prepare("
                UPDATE contact_messages
                SET status = 'replied'
                WHERE id = ?
            ");
            
            $stmt->execute([$messageId]);
            
            // Enviar email si hay mailer configurado
            if ($this->mailer) {
                $message = $this->getMessage($messageId);
                $this->mailer->send(
                    $message['email'],
                    $data['subject'],
                    $data['content']
                );
            }
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    public function updateStatus($messageId, $status) {
        $stmt = $this->conn->prepare("
            UPDATE contact_messages
            SET status = ?
            WHERE id = ?
        ");
        
        return $stmt->execute([$status, $messageId]);
    }
    
    public function getStats() {
        $stmt = $this->conn->prepare("
            SELECT 
                status,
                COUNT(*) as total
            FROM contact_messages
            GROUP BY status
        ");
        
        $stmt->execute();
        
        $stats = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stats[$row['status']] = $row['total'];
        }
        
        return $stats;
    }
} 
<?php
class MessageManager {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function getConversations($userId) {
        $stmt = $this->conn->prepare("
            SELECT 
                c.*,
                CASE 
                    WHEN c.type = 'direct' THEN (
                        SELECT u.name 
                        FROM conversation_participants cp
                        JOIN users u ON cp.user_id = u.id
                        WHERE cp.conversation_id = c.id 
                        AND cp.user_id != ?
                        LIMIT 1
                    )
                    ELSE c.title
                END as display_name,
                (
                    SELECT COUNT(*) 
                    FROM messages m
                    LEFT JOIN message_status ms ON m.id = ms.message_id AND ms.user_id = ?
                    WHERE m.conversation_id = c.id 
                    AND (ms.id IS NULL OR ms.status = 'delivered')
                ) as unread_count,
                (
                    SELECT m.created_at
                    FROM messages m
                    WHERE m.conversation_id = c.id
                    ORDER BY m.created_at DESC
                    LIMIT 1
                ) as last_message_at
            FROM conversations c
            JOIN conversation_participants cp ON c.id = cp.conversation_id
            WHERE cp.user_id = ?
            ORDER BY last_message_at DESC
        ");
        
        $stmt->execute([$userId, $userId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getMessages($conversationId, $userId, $limit = 50, $before = null) {
        // Verificar acceso
        if (!$this->hasAccess($conversationId, $userId)) {
            throw new Exception("No tienes acceso a esta conversación");
        }
        
        // Obtener mensajes
        $sql = "
            SELECT 
                m.*,
                u.name as sender_name,
                u.avatar as sender_avatar,
                ms.status as read_status
            FROM messages m
            JOIN users u ON m.user_id = u.id
            LEFT JOIN message_status ms ON m.id = ms.message_id AND ms.user_id = ?
            WHERE m.conversation_id = ?
            AND m.is_deleted = FALSE
        ";
        
        if ($before) {
            $sql .= " AND m.created_at < ?";
        }
        
        $sql .= " ORDER BY m.created_at DESC LIMIT ?";
        
        $params = [$userId, $conversationId];
        if ($before) $params[] = $before;
        $params[] = $limit;
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    public function sendMessage($conversationId, $userId, $content, $type = 'text', $fileUrl = null, $fileName = null) {
        // Verificar acceso
        if (!$this->hasAccess($conversationId, $userId)) {
            throw new Exception("No tienes acceso a esta conversación");
        }
        
        // Iniciar transacción
        $this->conn->beginTransaction();
        
        try {
            // Insertar mensaje
            $stmt = $this->conn->prepare("
                INSERT INTO messages (
                    conversation_id, user_id, content, 
                    type, file_url, file_name
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $conversationId, 
                $userId, 
                $content,
                $type,
                $fileUrl,
                $fileName
            ]);
            
            $messageId = $this->conn->lastInsertId();
            
            // Actualizar última actividad
            $stmt = $this->conn->prepare("
                UPDATE conversations 
                SET updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$conversationId]);
            
            // Crear estados de entrega para otros participantes
            $stmt = $this->conn->prepare("
                INSERT INTO message_status (message_id, user_id, status)
                SELECT ?, cp.user_id, 'delivered'
                FROM conversation_participants cp
                WHERE cp.conversation_id = ?
                AND cp.user_id != ?
            ");
            $stmt->execute([$messageId, $conversationId, $userId]);
            
            $this->conn->commit();
            return $messageId;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    public function markAsRead($conversationId, $userId) {
        if (!$this->hasAccess($conversationId, $userId)) {
            throw new Exception("No tienes acceso a esta conversación");
        }
        
        $stmt = $this->conn->prepare("
            UPDATE message_status ms
            JOIN messages m ON ms.message_id = m.id
            SET ms.status = 'read'
            WHERE m.conversation_id = ?
            AND ms.user_id = ?
            AND ms.status = 'delivered'
        ");
        
        $stmt->execute([$conversationId, $userId]);
        
        // Actualizar último tiempo de lectura
        $stmt = $this->conn->prepare("
            UPDATE conversation_participants
            SET last_read_at = CURRENT_TIMESTAMP
            WHERE conversation_id = ?
            AND user_id = ?
        ");
        
        $stmt->execute([$conversationId, $userId]);
    }
    
    private function hasAccess($conversationId, $userId) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) 
            FROM conversation_participants
            WHERE conversation_id = ? AND user_id = ?
        ");
        $stmt->execute([$conversationId, $userId]);
        return $stmt->fetchColumn() > 0;
    }
} 
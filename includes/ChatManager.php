<?php
class ChatManager {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function createSession($userId) {
        // Verificar si ya existe una sesión activa
        $stmt = $this->conn->prepare("
            SELECT id FROM chat_sessions 
            WHERE user_id = ? AND status = 'active'
        ");
        $stmt->execute([$userId]);
        
        if ($stmt->fetch()) {
            throw new Exception("Ya tienes una sesión de chat activa");
        }
        
        // Crear nueva sesión
        $stmt = $this->conn->prepare("
            INSERT INTO chat_sessions (user_id) VALUES (?)
        ");
        $stmt->execute([$userId]);
        
        return $this->conn->lastInsertId();
    }
    
    public function sendMessage($sessionId, $senderId, $message) {
        // Verificar que la sesión esté activa
        $stmt = $this->conn->prepare("
            SELECT status FROM chat_sessions WHERE id = ?
        ");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session || $session['status'] !== 'active') {
            throw new Exception("La sesión de chat no está activa");
        }
        
        // Enviar mensaje
        $stmt = $this->conn->prepare("
            INSERT INTO chat_messages (session_id, sender_id, message)
            VALUES (?, ?, ?)
        ");
        
        return $stmt->execute([$sessionId, $senderId, $message]);
    }
    
    public function getMessages($sessionId, $lastId = 0) {
        $stmt = $this->conn->prepare("
            SELECT m.*, u.name as sender_name
            FROM chat_messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.session_id = ? AND m.id > ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$sessionId, $lastId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function markMessagesAsRead($sessionId, $userId) {
        $stmt = $this->conn->prepare("
            UPDATE chat_messages
            SET is_read = TRUE
            WHERE session_id = ? AND sender_id != ? AND is_read = FALSE
        ");
        
        return $stmt->execute([$sessionId, $userId]);
    }
    
    public function closeSession($sessionId) {
        $stmt = $this->conn->prepare("
            UPDATE chat_sessions
            SET status = 'closed', closed_at = NOW()
            WHERE id = ?
        ");
        
        return $stmt->execute([$sessionId]);
    }
    
    public function getAvailableOperator() {
        $stmt = $this->conn->prepare("
            SELECT u.id, u.name
            FROM support_operators so
            JOIN users u ON so.user_id = u.id
            WHERE so.is_available = TRUE
            AND (so.last_active IS NULL OR so.last_active > DATE_SUB(NOW(), INTERVAL 5 MINUTE))
            ORDER BY RAND()
            LIMIT 1
        ");
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function updateOperatorStatus($userId, $isAvailable) {
        $stmt = $this->conn->prepare("
            UPDATE support_operators
            SET is_available = ?, last_active = NOW()
            WHERE user_id = ?
        ");
        
        return $stmt->execute([$isAvailable, $userId]);
    }
    
    public function getActiveSessions() {
        $stmt = $this->conn->prepare("
            SELECT cs.*, u.name as user_name,
                   (SELECT COUNT(*) FROM chat_messages 
                    WHERE session_id = cs.id AND is_read = FALSE) as unread_count
            FROM chat_sessions cs
            JOIN users u ON cs.user_id = u.id
            WHERE cs.status = 'active'
            ORDER BY cs.created_at DESC
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} 
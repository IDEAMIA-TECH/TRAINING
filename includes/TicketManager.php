<?php
class TicketManager {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function createTicket($userId, $data) {
        $this->conn->beginTransaction();
        
        try {
            // Crear ticket
            $stmt = $this->conn->prepare("
                INSERT INTO tickets (
                    user_id, department_id, subject,
                    priority, status
                ) VALUES (?, ?, ?, ?, 'open')
            ");
            
            $stmt->execute([
                $userId,
                $data['department_id'],
                $data['subject'],
                $data['priority'] ?? 'medium'
            ]);
            
            $ticketId = $this->conn->lastInsertId();
            
            // Agregar primer mensaje
            $stmt = $this->conn->prepare("
                INSERT INTO ticket_messages (
                    ticket_id, user_id, message, attachments
                ) VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $ticketId,
                $userId,
                $data['message'],
                json_encode($data['attachments'] ?? [])
            ]);
            
            // Agregar seguimiento automático
            $stmt = $this->conn->prepare("
                INSERT INTO ticket_followers (ticket_id, user_id)
                VALUES (?, ?)
            ");
            
            $stmt->execute([$ticketId, $userId]);
            
            $this->conn->commit();
            return $ticketId;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    public function getTicket($ticketId, $userId = null) {
        $sql = "
            SELECT 
                t.*,
                d.name as department_name,
                u.name as user_name,
                u.email as user_email,
                a.name as assigned_name
            FROM tickets t
            JOIN support_departments d ON t.department_id = d.id
            JOIN users u ON t.user_id = u.id
            LEFT JOIN users a ON t.assigned_to = a.id
            WHERE t.id = ?
        ";
        
        if ($userId !== null) {
            $sql .= " AND (t.user_id = ? OR t.assigned_to = ?)";
        }
        
        $stmt = $this->conn->prepare($sql);
        
        if ($userId !== null) {
            $stmt->execute([$ticketId, $userId, $userId]);
        } else {
            $stmt->execute([$ticketId]);
        }
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getTicketMessages($ticketId, $userId = null, $includeInternal = false) {
        $sql = "
            SELECT 
                m.*,
                u.name as user_name,
                u.avatar as user_avatar,
                u.role as user_role
            FROM ticket_messages m
            JOIN users u ON m.user_id = u.id
            WHERE m.ticket_id = ?
        ";
        
        if (!$includeInternal) {
            $sql .= " AND m.is_internal = FALSE";
        }
        
        $sql .= " ORDER BY m.created_at ASC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$ticketId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function addMessage($ticketId, $userId, $message, $isInternal = false, $attachments = []) {
        $this->conn->beginTransaction();
        
        try {
            // Agregar mensaje
            $stmt = $this->conn->prepare("
                INSERT INTO ticket_messages (
                    ticket_id, user_id, message,
                    is_internal, attachments
                ) VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $ticketId,
                $userId,
                $message,
                $isInternal,
                json_encode($attachments)
            ]);
            
            // Actualizar última respuesta
            $stmt = $this->conn->prepare("
                UPDATE tickets
                SET last_response_at = CURRENT_TIMESTAMP,
                    status = CASE 
                        WHEN status = 'closed' THEN 'open'
                        ELSE status
                    END
                WHERE id = ?
            ");
            
            $stmt->execute([$ticketId]);
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    public function updateTicketStatus($ticketId, $status, $userId) {
        $ticket = $this->getTicket($ticketId);
        
        if (!$ticket) {
            throw new Exception("Ticket no encontrado");
        }
        
        $stmt = $this->conn->prepare("
            UPDATE tickets
            SET status = ?,
                closed_at = CASE 
                    WHEN ? = 'closed' THEN CURRENT_TIMESTAMP
                    ELSE NULL
                END
            WHERE id = ?
        ");
        
        return $stmt->execute([$status, $status, $ticketId]);
    }
    
    public function assignTicket($ticketId, $assignedTo) {
        $stmt = $this->conn->prepare("
            UPDATE tickets
            SET assigned_to = ?,
                status = CASE 
                    WHEN status = 'open' THEN 'in_progress'
                    ELSE status
                END
            WHERE id = ?
        ");
        
        return $stmt->execute([$assignedTo, $ticketId]);
    }
} 
<?php
class LogManager {
    private $conn;
    private $defaultContext;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->defaultContext = [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ];
    }
    
    public function log($level, $message, $channel = 'app', $context = [], $userId = null) {
        $context = array_merge($this->defaultContext, $context);
        
        $stmt = $this->conn->prepare("
            INSERT INTO system_logs (
                level, channel, message, context,
                ip_address, user_agent, user_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $level,
            $channel,
            $message,
            json_encode($context),
            $context['ip_address'],
            $context['user_agent'],
            $userId
        ]);
    }
    
    public function logAccess($action, $userId = null, $status = 'success', $details = []) {
        $stmt = $this->conn->prepare("
            INSERT INTO access_logs (
                user_id, action, status,
                ip_address, user_agent, details
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $userId,
            $action,
            $status,
            $this->defaultContext['ip_address'],
            $this->defaultContext['user_agent'],
            json_encode($details)
        ]);
    }
    
    public function logAudit($entityType, $entityId, $action, $userId = null, $oldValues = null, $newValues = null) {
        $stmt = $this->conn->prepare("
            INSERT INTO audit_logs (
                user_id, entity_type, entity_id,
                action, old_values, new_values,
                ip_address
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $userId,
            $entityType,
            $entityId,
            $action,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            $this->defaultContext['ip_address']
        ]);
    }
    
    public function getSystemLogs($filters = [], $page = 1, $limit = 50) {
        $sql = "SELECT * FROM system_logs WHERE 1=1";
        $params = [];
        
        if (!empty($filters['level'])) {
            $sql .= " AND level = ?";
            $params[] = $filters['level'];
        }
        
        if (!empty($filters['channel'])) {
            $sql .= " AND channel = ?";
            $params[] = $filters['channel'];
        }
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = ($page - 1) * $limit;
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getAccessLogs($filters = [], $page = 1, $limit = 50) {
        $sql = "
            SELECT 
                al.*,
                u.name as user_name,
                u.email as user_email
            FROM access_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($filters['action'])) {
            $sql .= " AND al.action = ?";
            $params[] = $filters['action'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND al.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND al.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['ip_address'])) {
            $sql .= " AND al.ip_address = ?";
            $params[] = $filters['ip_address'];
        }
        
        $sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = ($page - 1) * $limit;
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getAuditLogs($filters = [], $page = 1, $limit = 50) {
        $sql = "
            SELECT 
                al.*,
                u.name as user_name
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($filters['entity_type'])) {
            $sql .= " AND al.entity_type = ?";
            $params[] = $filters['entity_type'];
        }
        
        if (!empty($filters['entity_id'])) {
            $sql .= " AND al.entity_id = ?";
            $params[] = $filters['entity_id'];
        }
        
        if (!empty($filters['action'])) {
            $sql .= " AND al.action = ?";
            $params[] = $filters['action'];
        }
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND al.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        $sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = ($page - 1) * $limit;
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function cleanOldLogs($days = 90) {
        $date = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        // Limpiar logs del sistema
        $stmt = $this->conn->prepare("
            DELETE FROM system_logs
            WHERE created_at < ?
            AND level != 'critical'
        ");
        $stmt->execute([$date]);
        
        // Limpiar logs de acceso
        $stmt = $this->conn->prepare("
            DELETE FROM access_logs
            WHERE created_at < ?
            AND action NOT IN ('failed_login', 'password_reset')
        ");
        $stmt->execute([$date]);
        
        // Los logs de auditoría no se limpian automáticamente
    }
} 
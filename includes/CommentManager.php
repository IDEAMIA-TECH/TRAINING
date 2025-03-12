<?php
class CommentManager {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function addComment($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO comments (
                user_id, entity_type,
                entity_id, parent_id,
                content, ip_address
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $data['user_id'],
            $data['entity_type'],
            $data['entity_id'],
            $data['parent_id'] ?? null,
            $data['content'],
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
    
    public function getComments($entityType, $entityId, $status = 'approved') {
        $sql = "
            WITH RECURSIVE comment_tree AS (
                -- Base: comentarios padre
                SELECT 
                    c.*,
                    u.name as user_name,
                    u.avatar as user_avatar,
                    0 as level
                FROM comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.entity_type = ?
                AND c.entity_id = ?
                AND c.parent_id IS NULL
                AND c.status = ?
                
                UNION ALL
                
                -- Recursión: respuestas
                SELECT 
                    c.*,
                    u.name as user_name,
                    u.avatar as user_avatar,
                    ct.level + 1
                FROM comments c
                JOIN users u ON c.user_id = u.id
                JOIN comment_tree ct ON c.parent_id = ct.id
                WHERE c.status = ?
            )
            SELECT * FROM comment_tree
            ORDER BY 
                CASE WHEN parent_id IS NULL THEN id ELSE parent_id END,
                created_at
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$entityType, $entityId, $status, $status]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updateStatus($commentId, $status) {
        $stmt = $this->conn->prepare("
            UPDATE comments
            SET status = ?
            WHERE id = ?
        ");
        
        return $stmt->execute([$status, $commentId]);
    }
    
    public function addReaction($commentId, $userId, $type) {
        $this->conn->beginTransaction();
        
        try {
            // Eliminar reacción existente si existe
            $stmt = $this->conn->prepare("
                DELETE FROM comment_reactions
                WHERE comment_id = ? AND user_id = ?
            ");
            $stmt->execute([$commentId, $userId]);
            
            // Insertar nueva reacción
            $stmt = $this->conn->prepare("
                INSERT INTO comment_reactions (
                    comment_id, user_id, type
                ) VALUES (?, ?, ?)
            ");
            $stmt->execute([$commentId, $userId, $type]);
            
            // Actualizar contador de likes
            $stmt = $this->conn->prepare("
                UPDATE comments c
                SET likes = (
                    SELECT COUNT(*) 
                    FROM comment_reactions 
                    WHERE comment_id = c.id 
                    AND type = 'like'
                )
                WHERE id = ?
            ");
            $stmt->execute([$commentId]);
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    public function reportComment($commentId, $userId, $reason, $details = null) {
        // Verificar si ya existe un reporte
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) 
            FROM comment_reports
            WHERE comment_id = ? AND user_id = ?
        ");
        $stmt->execute([$commentId, $userId]);
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Ya has reportado este comentario");
        }
        
        $this->conn->beginTransaction();
        
        try {
            // Insertar reporte
            $stmt = $this->conn->prepare("
                INSERT INTO comment_reports (
                    comment_id, user_id,
                    reason, details
                ) VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$commentId, $userId, $reason, $details]);
            
            // Actualizar contador de reportes
            $stmt = $this->conn->prepare("
                UPDATE comments
                SET reports = reports + 1
                WHERE id = ?
            ");
            $stmt->execute([$commentId]);
            
            // Si hay muchos reportes, marcar como spam
            $stmt = $this->conn->prepare("
                UPDATE comments
                SET status = 'spam'
                WHERE id = ?
                AND reports >= 5
                AND status = 'approved'
            ");
            $stmt->execute([$commentId]);
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    public function getReports($status = 'pending', $page = 1, $limit = 50) {
        $sql = "
            SELECT 
                cr.*,
                c.content as comment_content,
                u1.name as reporter_name,
                u2.name as commenter_name
            FROM comment_reports cr
            JOIN comments c ON cr.comment_id = c.id
            JOIN users u1 ON cr.user_id = u1.id
            JOIN users u2 ON c.user_id = u2.id
            WHERE cr.status = ?
            ORDER BY cr.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$status, $limit, ($page - 1) * $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getUserComments($userId, $page = 1, $limit = 50) {
        $sql = "
            SELECT 
                c.*,
                CASE c.entity_type
                    WHEN 'course' THEN (SELECT title FROM courses WHERE id = c.entity_id)
                    WHEN 'lesson' THEN (SELECT title FROM lessons WHERE id = c.entity_id)
                    WHEN 'blog_post' THEN (SELECT title FROM blog_posts WHERE id = c.entity_id)
                END as entity_title
            FROM comments c
            WHERE c.user_id = ?
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId, $limit, ($page - 1) * $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} 
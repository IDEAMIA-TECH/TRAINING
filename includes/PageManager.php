<?php
class PageManager {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function createPage($data, $userId) {
        $this->conn->beginTransaction();
        
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO pages (
                    title, slug, content,
                    meta_description, meta_keywords,
                    status, layout, order_index,
                    show_in_menu, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['title'],
                $this->generateSlug($data['title']),
                $data['content'],
                $data['meta_description'] ?? null,
                $data['meta_keywords'] ?? null,
                $data['status'] ?? 'draft',
                $data['layout'] ?? 'default',
                $data['order_index'] ?? 0,
                $data['show_in_menu'] ?? false,
                $userId
            ]);
            
            $pageId = $this->conn->lastInsertId();
            
            // Crear primera revisión
            $this->createRevision($pageId, $data, $userId);
            
            $this->conn->commit();
            return $pageId;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    public function updatePage($pageId, $data, $userId) {
        $this->conn->beginTransaction();
        
        try {
            $sql = "UPDATE pages SET ";
            $params = [];
            
            foreach ($data as $key => $value) {
                if ($key === 'title') {
                    $sql .= "title = ?, slug = ?, ";
                    $params[] = $value;
                    $params[] = $this->generateSlug($value, $pageId);
                } else {
                    $sql .= "$key = ?, ";
                    $params[] = $value;
                }
            }
            
            $sql .= "updated_by = ? WHERE id = ?";
            $params[] = $userId;
            $params[] = $pageId;
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            // Crear nueva revisión
            $this->createRevision($pageId, $data, $userId);
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    public function getPage($identifier, $bySlug = true) {
        $sql = "
            SELECT 
                p.*,
                c.name as creator_name,
                u.name as updater_name
            FROM pages p
            LEFT JOIN users c ON p.created_by = c.id
            LEFT JOIN users u ON p.updated_by = u.id
            WHERE " . ($bySlug ? "p.slug = ?" : "p.id = ?");
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$identifier]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getRevisions($pageId) {
        $stmt = $this->conn->prepare("
            SELECT 
                r.*,
                u.name as creator_name
            FROM page_revisions r
            JOIN users u ON r.created_by = u.id
            WHERE r.page_id = ?
            ORDER BY r.created_at DESC
        ");
        
        $stmt->execute([$pageId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getMenuPages() {
        $stmt = $this->conn->prepare("
            SELECT id, title, slug
            FROM pages
            WHERE status = 'published'
            AND show_in_menu = TRUE
            ORDER BY order_index ASC
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function createRevision($pageId, $data, $userId) {
        $stmt = $this->conn->prepare("
            INSERT INTO page_revisions (
                page_id, title, content,
                meta_description, meta_keywords,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $pageId,
            $data['title'],
            $data['content'],
            $data['meta_description'] ?? null,
            $data['meta_keywords'] ?? null,
            $userId
        ]);
    }
    
    private function generateSlug($title, $pageId = null) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        
        // Verificar si el slug ya existe
        $sql = "SELECT COUNT(*) FROM pages WHERE slug = ?";
        $params = [$slug];
        
        if ($pageId) {
            $sql .= " AND id != ?";
            $params[] = $pageId;
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            $slug .= '-' . time();
        }
        
        return $slug;
    }
} 
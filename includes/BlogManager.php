<?php
class BlogManager {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function createPost($data, $userId) {
        $this->conn->beginTransaction();
        
        try {
            // Generar slug único
            $slug = $this->generateSlug($data['title']);
            
            // Insertar post
            $stmt = $this->conn->prepare("
                INSERT INTO blog_posts (
                    title, slug, content,
                    excerpt, featured_image_id,
                    meta_description, meta_keywords,
                    status, allow_comments,
                    created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['title'],
                $slug,
                $data['content'],
                $data['excerpt'] ?? null,
                $data['featured_image_id'] ?? null,
                $data['meta_description'] ?? null,
                $data['meta_keywords'] ?? null,
                $data['status'] ?? 'draft',
                $data['allow_comments'] ?? true,
                $userId
            ]);
            
            $postId = $this->conn->lastInsertId();
            
            // Asignar categorías
            if (!empty($data['categories'])) {
                $this->assignCategories($postId, $data['categories']);
            }
            
            // Asignar etiquetas
            if (!empty($data['tags'])) {
                $this->assignTags($postId, $data['tags']);
            }
            
            $this->conn->commit();
            return $postId;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    public function getPosts($filters = [], $page = 1, $limit = 10) {
        $sql = "
            SELECT 
                p.*,
                u.name as author_name,
                i.path as featured_image_path,
                GROUP_CONCAT(DISTINCT c.name) as categories,
                GROUP_CONCAT(DISTINCT t.name) as tags
            FROM blog_posts p
            LEFT JOIN users u ON p.created_by = u.id
            LEFT JOIN images i ON p.featured_image_id = i.id
            LEFT JOIN blog_post_categories pc ON p.id = pc.post_id
            LEFT JOIN blog_categories c ON pc.category_id = c.id
            LEFT JOIN blog_post_tags pt ON p.id = pt.post_id
            LEFT JOIN blog_tags t ON pt.tag_id = t.id
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND p.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['category'])) {
            $sql .= " AND c.slug = ?";
            $params[] = $filters['category'];
        }
        
        if (!empty($filters['tag'])) {
            $sql .= " AND t.slug = ?";
            $params[] = $filters['tag'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (p.title LIKE ? OR p.content LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }
        
        $sql .= " GROUP BY p.id ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = ($page - 1) * $limit;
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getPost($slugOrId) {
        $sql = "
            SELECT 
                p.*,
                u.name as author_name,
                u.avatar as author_avatar,
                i.path as featured_image_path,
                GROUP_CONCAT(DISTINCT c.name) as categories,
                GROUP_CONCAT(DISTINCT t.name) as tags
            FROM blog_posts p
            LEFT JOIN users u ON p.created_by = u.id
            LEFT JOIN images i ON p.featured_image_id = i.id
            LEFT JOIN blog_post_categories pc ON p.id = pc.post_id
            LEFT JOIN blog_categories c ON pc.category_id = c.id
            LEFT JOIN blog_post_tags pt ON p.id = pt.post_id
            LEFT JOIN blog_tags t ON pt.tag_id = t.id
            WHERE " . (is_numeric($slugOrId) ? "p.id = ?" : "p.slug = ?") . "
            GROUP BY p.id
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$slugOrId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function updatePost($postId, $data, $userId) {
        $this->conn->beginTransaction();
        
        try {
            $sql = "
                UPDATE blog_posts SET
                    title = ?,
                    content = ?,
                    excerpt = ?,
                    featured_image_id = ?,
                    meta_description = ?,
                    meta_keywords = ?,
                    status = ?,
                    allow_comments = ?,
                    updated_by = ?
                WHERE id = ?
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $data['title'],
                $data['content'],
                $data['excerpt'] ?? null,
                $data['featured_image_id'] ?? null,
                $data['meta_description'] ?? null,
                $data['meta_keywords'] ?? null,
                $data['status'] ?? 'draft',
                $data['allow_comments'] ?? true,
                $userId,
                $postId
            ]);
            
            // Actualizar categorías
            if (isset($data['categories'])) {
                $this->updateCategories($postId, $data['categories']);
            }
            
            // Actualizar etiquetas
            if (isset($data['tags'])) {
                $this->updateTags($postId, $data['tags']);
            }
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    private function generateSlug($title) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        
        // Verificar si existe
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM blog_posts WHERE slug = ?
        ");
        $stmt->execute([$slug]);
        
        if ($stmt->fetchColumn() > 0) {
            $slug .= '-' . time();
        }
        
        return $slug;
    }
    
    private function assignCategories($postId, $categories) {
        foreach ($categories as $categoryId) {
            $stmt = $this->conn->prepare("
                INSERT INTO blog_post_categories (post_id, category_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$postId, $categoryId]);
        }
    }
    
    private function assignTags($postId, $tags) {
        foreach ($tags as $tag) {
            // Crear tag si no existe
            $stmt = $this->conn->prepare("
                INSERT IGNORE INTO blog_tags (name, slug)
                VALUES (?, ?)
            ");
            $stmt->execute([$tag, $this->generateSlug($tag)]);
            
            $tagId = $this->conn->lastInsertId() ?: $this->getTagId($tag);
            
            // Asignar tag al post
            $stmt = $this->conn->prepare("
                INSERT IGNORE INTO blog_post_tags (post_id, tag_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$postId, $tagId]);
        }
    }
    
    private function getTagId($tagName) {
        $stmt = $this->conn->prepare("
            SELECT id FROM blog_tags WHERE name = ?
        ");
        $stmt->execute([$tagName]);
        return $stmt->fetchColumn();
    }
    
    private function updateCategories($postId, $categories) {
        // Eliminar categorías existentes
        $stmt = $this->conn->prepare("
            DELETE FROM blog_post_categories
            WHERE post_id = ?
        ");
        $stmt->execute([$postId]);
        
        // Asignar nuevas categorías
        $this->assignCategories($postId, $categories);
    }
    
    private function updateTags($postId, $tags) {
        // Eliminar tags existentes
        $stmt = $this->conn->prepare("
            DELETE FROM blog_post_tags
            WHERE post_id = ?
        ");
        $stmt->execute([$postId]);
        
        // Asignar nuevos tags
        $this->assignTags($postId, $tags);
    }
} 
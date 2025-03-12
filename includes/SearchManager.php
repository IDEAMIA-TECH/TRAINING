<?php
class SearchManager {
    private $conn;
    private $indexableTypes = ['course', 'lesson', 'blog_post', 'resource'];
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function search($query, $filters = [], $page = 1, $limit = 20) {
        $sql = "
            SELECT 
                si.*,
                MATCH(title, content, keywords) AGAINST (? IN BOOLEAN MODE) as score
            FROM search_index si
            WHERE status = 'active'
            AND MATCH(title, content, keywords) AGAINST (? IN BOOLEAN MODE)
        ";
        $params = [$query, $query];
        
        if (!empty($filters['type'])) {
            $sql .= " AND entity_type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['category'])) {
            $sql .= " AND category = ?";
            $params[] = $filters['category'];
        }
        
        $sql .= " ORDER BY score DESC, relevance DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = ($page - 1) * $limit;
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Registrar búsqueda
        $this->logSearch($query, count($results), $filters);
        
        // Actualizar sugerencias
        $this->updateSuggestions($query, $results);
        
        return $results;
    }
    
    public function indexEntity($type, $id) {
        if (!in_array($type, $this->indexableTypes)) {
            throw new Exception("Tipo de entidad no indexable");
        }
        
        $data = $this->getEntityData($type, $id);
        if (!$data) {
            throw new Exception("Entidad no encontrada");
        }
        
        $stmt = $this->conn->prepare("
            INSERT INTO search_index (
                entity_type, entity_id,
                title, content, keywords,
                category, status, relevance
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                content = VALUES(content),
                keywords = VALUES(keywords),
                category = VALUES(category),
                status = VALUES(status),
                relevance = VALUES(relevance),
                last_indexed = CURRENT_TIMESTAMP
        ");
        
        return $stmt->execute([
            $type,
            $id,
            $data['title'],
            $data['content'],
            $data['keywords'] ?? null,
            $data['category'] ?? null,
            $data['status'] ?? 'active',
            $data['relevance'] ?? 0
        ]);
    }
    
    public function getSuggestions($query, $limit = 5) {
        $stmt = $this->conn->prepare("
            SELECT suggestion
            FROM search_suggestions
            WHERE query LIKE ?
            ORDER BY weight DESC
            LIMIT ?
        ");
        
        $stmt->execute(["%$query%", $limit]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    private function getEntityData($type, $id) {
        switch ($type) {
            case 'course':
                return $this->getCourseData($id);
            case 'lesson':
                return $this->getLessonData($id);
            case 'blog_post':
                return $this->getBlogPostData($id);
            case 'resource':
                return $this->getResourceData($id);
        }
        return null;
    }
    
    private function getCourseData($id) {
        $stmt = $this->conn->prepare("
            SELECT 
                title,
                description as content,
                CONCAT_WS(',', keywords, requirements, objectives) as keywords,
                category_id as category,
                status,
                featured * 2 + popularity as relevance
            FROM courses
            WHERE id = ?
        ");
        
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getLessonData($id) {
        $stmt = $this->conn->prepare("
            SELECT 
                l.title,
                l.content,
                c.keywords,
                c.category_id as category,
                l.status,
                c.featured + l.order_index as relevance
            FROM lessons l
            JOIN courses c ON l.course_id = c.id
            WHERE l.id = ?
        ");
        
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getBlogPostData($id) {
        $stmt = $this->conn->prepare("
            SELECT 
                title,
                CONCAT(excerpt, '\n', content) as content,
                meta_keywords as keywords,
                (SELECT GROUP_CONCAT(name) FROM blog_categories bc
                 JOIN blog_post_categories bpc ON bc.id = bpc.category_id
                 WHERE bpc.post_id = bp.id) as category,
                status,
                views as relevance
            FROM blog_posts bp
            WHERE id = ?
        ");
        
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getResourceData($id) {
        // Implementar según la estructura de recursos
        return null;
    }
    
    private function logSearch($query, $resultsCount, $filters = []) {
        $stmt = $this->conn->prepare("
            INSERT INTO search_history (
                user_id, query,
                results_count, filters
            ) VALUES (?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $query,
            $resultsCount,
            json_encode($filters)
        ]);
    }
    
    private function updateSuggestions($query, $results) {
        if (empty($results)) return;
        
        foreach ($results as $result) {
            $stmt = $this->conn->prepare("
                INSERT INTO search_suggestions (
                    query, suggestion, weight
                ) VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE
                    weight = weight + 1
            ");
            
            $stmt->execute([$query, $result['title']]);
        }
    }
    
    public function reindexAll() {
        // Reindexar cursos
        $stmt = $this->conn->query("SELECT id FROM courses");
        while ($row = $stmt->fetch()) {
            $this->indexEntity('course', $row['id']);
        }
        
        // Reindexar lecciones
        $stmt = $this->conn->query("SELECT id FROM lessons");
        while ($row = $stmt->fetch()) {
            $this->indexEntity('lesson', $row['id']);
        }
        
        // Reindexar posts del blog
        $stmt = $this->conn->query("SELECT id FROM blog_posts");
        while ($row = $stmt->fetch()) {
            $this->indexEntity('blog_post', $row['id']);
        }
        
        // Reindexar recursos
        $stmt = $this->conn->query("SELECT id FROM resources");
        while ($row = $stmt->fetch()) {
            $this->indexEntity('resource', $row['id']);
        }
    }
} 
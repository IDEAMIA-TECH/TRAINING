<?php
class BannerManager {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function createBanner($data, $userId) {
        $stmt = $this->conn->prepare("
            INSERT INTO banners (
                title, description, image_id,
                link, position, start_date,
                end_date, status, priority,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $data['title'],
            $data['description'] ?? null,
            $data['image_id'],
            $data['link'] ?? null,
            $data['position'],
            $data['start_date'] ?? null,
            $data['end_date'] ?? null,
            $data['status'] ?? 'inactive',
            $data['priority'] ?? 0,
            $userId
        ]);
    }
    
    public function getBanners($position, $limit = null) {
        $sql = "
            SELECT 
                b.*,
                i.path as image_path,
                i.alt_text,
                i.title as image_title
            FROM banners b
            JOIN images i ON b.image_id = i.id
            WHERE b.position = ?
            AND b.status = 'active'
            AND (b.start_date IS NULL OR b.start_date <= NOW())
            AND (b.end_date IS NULL OR b.end_date >= NOW())
            ORDER BY b.priority DESC, b.created_at DESC
        ";
        
        if ($limit) {
            $sql .= " LIMIT ?";
        }
        
        $stmt = $this->conn->prepare($sql);
        
        if ($limit) {
            $stmt->execute([$position, $limit]);
        } else {
            $stmt->execute([$position]);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function trackView($bannerId) {
        // Actualizar contador general
        $stmt = $this->conn->prepare("
            UPDATE banners
            SET views = views + 1
            WHERE id = ?
        ");
        $stmt->execute([$bannerId]);
        
        // Actualizar estadísticas diarias
        $stmt = $this->conn->prepare("
            INSERT INTO banner_stats (banner_id, date, views)
            VALUES (?, CURRENT_DATE, 1)
            ON DUPLICATE KEY UPDATE views = views + 1
        ");
        $stmt->execute([$bannerId]);
    }
    
    public function trackClick($bannerId) {
        // Actualizar contador general
        $stmt = $this->conn->prepare("
            UPDATE banners
            SET clicks = clicks + 1
            WHERE id = ?
        ");
        $stmt->execute([$bannerId]);
        
        // Actualizar estadísticas diarias
        $stmt = $this->conn->prepare("
            INSERT INTO banner_stats (banner_id, date, clicks)
            VALUES (?, CURRENT_DATE, 1)
            ON DUPLICATE KEY UPDATE clicks = clicks + 1
        ");
        $stmt->execute([$bannerId]);
    }
    
    public function getStats($bannerId, $startDate = null, $endDate = null) {
        $sql = "
            SELECT 
                date,
                SUM(views) as total_views,
                SUM(clicks) as total_clicks,
                CASE 
                    WHEN SUM(views) > 0 
                    THEN (SUM(clicks) / SUM(views)) * 100 
                    ELSE 0 
                END as ctr
            FROM banner_stats
            WHERE banner_id = ?
        ";
        $params = [$bannerId];
        
        if ($startDate) {
            $sql .= " AND date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND date <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " GROUP BY date ORDER BY date DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updateStatus($bannerId, $status) {
        $stmt = $this->conn->prepare("
            UPDATE banners
            SET status = ?
            WHERE id = ?
        ");
        
        return $stmt->execute([$status, $bannerId]);
    }
    
    public function getPositions($activeOnly = true) {
        $sql = "SELECT * FROM banner_positions";
        
        if ($activeOnly) {
            $sql .= " WHERE is_active = TRUE";
        }
        
        $sql .= " ORDER BY name";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} 
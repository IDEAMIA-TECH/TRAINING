<?php
class StatisticsManager {
    private $conn;
    private $cache;
    
    public function __construct($conn, $cache = null) {
        $this->conn = $conn;
        $this->cache = $cache;
    }
    
    public function trackEvent($eventType, $userId = null, $data = []) {
        $stmt = $this->conn->prepare("
            INSERT INTO user_events (
                user_id, event_type, event_data,
                ip_address, user_agent
            ) VALUES (?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $userId,
            $eventType,
            json_encode($data),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
    
    public function updateDailyMetric($key, $value, $date = null) {
        $date = $date ?? date('Y-m-d');
        
        $stmt = $this->conn->prepare("
            INSERT INTO daily_metrics (date, metric_key, metric_value)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE metric_value = metric_value + VALUES(metric_value)
        ");
        
        return $stmt->execute([$date, $key, $value]);
    }
    
    public function updateRealtimeMetric($key, $value, $operation = 'set') {
        switch ($operation) {
            case 'increment':
                $sql = "
                    INSERT INTO realtime_metrics (metric_key, metric_value)
                    VALUES (?, 1)
                    ON DUPLICATE KEY UPDATE metric_value = metric_value + 1
                ";
                break;
            case 'decrement':
                $sql = "
                    INSERT INTO realtime_metrics (metric_key, metric_value)
                    VALUES (?, -1)
                    ON DUPLICATE KEY UPDATE metric_value = GREATEST(0, metric_value - 1)
                ";
                break;
            default:
                $sql = "
                    INSERT INTO realtime_metrics (metric_key, metric_value)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE metric_value = VALUES(metric_value)
                ";
        }
        
        $stmt = $this->conn->prepare($sql);
        
        if ($operation === 'set') {
            return $stmt->execute([$key, $value]);
        } else {
            return $stmt->execute([$key]);
        }
    }
    
    public function getDailyMetrics($metrics = [], $startDate = null, $endDate = null) {
        $sql = "
            SELECT date, metric_key, metric_value
            FROM daily_metrics
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($metrics)) {
            $sql .= " AND metric_key IN (" . str_repeat('?,', count($metrics) - 1) . "?)";
            $params = array_merge($params, $metrics);
        }
        
        if ($startDate) {
            $sql .= " AND date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND date <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " ORDER BY date DESC, metric_key";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[$row['date']][$row['metric_key']] = $row['metric_value'];
        }
        
        return $results;
    }
    
    public function getRealtimeMetrics($metrics = []) {
        $cacheKey = 'realtime_metrics';
        
        // Intentar obtener del cache
        if ($this->cache && empty($metrics)) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        $sql = "SELECT metric_key, metric_value FROM realtime_metrics";
        $params = [];
        
        if (!empty($metrics)) {
            $sql .= " WHERE metric_key IN (" . str_repeat('?,', count($metrics) - 1) . "?)";
            $params = $metrics;
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[$row['metric_key']] = $row['metric_value'];
        }
        
        // Guardar en cache por 1 minuto
        if ($this->cache && empty($metrics)) {
            $this->cache->set($cacheKey, $results, 60);
        }
        
        return $results;
    }
    
    public function getEventStats($eventType, $period = 'day', $limit = 30) {
        $sql = "
            SELECT 
                DATE_FORMAT(created_at, ?) as period,
                COUNT(*) as total
            FROM user_events
            WHERE event_type = ?
        ";
        
        switch ($period) {
            case 'hour':
                $format = '%Y-%m-%d %H:00:00';
                $interval = 'INTERVAL 24 HOUR';
                break;
            case 'day':
                $format = '%Y-%m-%d';
                $interval = 'INTERVAL 30 DAY';
                break;
            case 'month':
                $format = '%Y-%m';
                $interval = 'INTERVAL 12 MONTH';
                break;
            default:
                throw new Exception("Período no válido");
        }
        
        $sql .= " AND created_at >= DATE_SUB(NOW(), $interval)";
        $sql .= " GROUP BY period ORDER BY period DESC LIMIT ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$format, $eventType, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function generateDailyReport() {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // Nuevos usuarios
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM users
            WHERE DATE(created_at) = ?
        ");
        $stmt->execute([$yesterday]);
        $this->updateDailyMetric('new_users', $stmt->fetchColumn(), $yesterday);
        
        // Nuevas inscripciones
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM course_enrollments
            WHERE DATE(created_at) = ?
        ");
        $stmt->execute([$yesterday]);
        $this->updateDailyMetric('new_enrollments', $stmt->fetchColumn(), $yesterday);
        
        // Lecciones completadas
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM completed_lessons
            WHERE DATE(completed_at) = ?
        ");
        $stmt->execute([$yesterday]);
        $this->updateDailyMetric('completed_lessons', $stmt->fetchColumn(), $yesterday);
        
        // Ingresos
        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(amount), 0) FROM payments
            WHERE DATE(created_at) = ?
            AND status = 'completed'
        ");
        $stmt->execute([$yesterday]);
        $this->updateDailyMetric('revenue', $stmt->fetchColumn(), $yesterday);
    }
} 
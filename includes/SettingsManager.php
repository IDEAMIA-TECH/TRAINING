<?php
class SettingsManager {
    private $conn;
    private static $cache = [];
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function get($key, $category = 'site') {
        // Verificar caché
        $cacheKey = $category . '.' . $key;
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }
        
        $stmt = $this->conn->prepare("
            SELECT setting_value, type
            FROM settings
            WHERE category = ? AND setting_key = ?
        ");
        
        $stmt->execute([$category, $key]);
        $setting = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$setting) {
            return null;
        }
        
        $value = $this->parseValue($setting['setting_value'], $setting['type']);
        self::$cache[$cacheKey] = $value;
        
        return $value;
    }
    
    public function set($key, $value, $category = 'site') {
        $stmt = $this->conn->prepare("
            UPDATE settings
            SET setting_value = ?
            WHERE category = ? AND setting_key = ?
        ");
        
        $success = $stmt->execute([
            $this->formatValue($value),
            $category,
            $key
        ]);
        
        if ($success) {
            // Actualizar caché
            $cacheKey = $category . '.' . $key;
            self::$cache[$cacheKey] = $value;
        }
        
        return $success;
    }
    
    public function getAll($category = null, $publicOnly = false) {
        $sql = "SELECT * FROM settings";
        $params = [];
        
        if ($category) {
            $sql .= " WHERE category = ?";
            $params[] = $category;
            
            if ($publicOnly) {
                $sql .= " AND is_public = TRUE";
            }
        } elseif ($publicOnly) {
            $sql .= " WHERE is_public = TRUE";
        }
        
        $sql .= " ORDER BY category, setting_key";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $value = $this->parseValue($row['setting_value'], $row['type']);
            $settings[$row['category']][$row['setting_key']] = $value;
            
            // Actualizar caché
            $cacheKey = $row['category'] . '.' . $row['setting_key'];
            self::$cache[$cacheKey] = $value;
        }
        
        return $settings;
    }
    
    public function clearCache() {
        self::$cache = [];
    }
    
    private function parseValue($value, $type) {
        switch ($type) {
            case 'integer':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'boolean':
                return $value === 'true' || $value === '1';
            case 'json':
            case 'array':
                return json_decode($value, true);
            default:
                return $value;
        }
    }
    
    private function formatValue($value) {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_array($value)) {
            return json_encode($value);
        } else {
            return (string) $value;
        }
    }
} 
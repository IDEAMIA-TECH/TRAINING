<?php
class Settings {
    private $db;
    private static $cache = [];

    public function __construct($db) {
        $this->db = $db;
    }

    public function get($key, $default = null) {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT setting_value, type 
                FROM system_settings 
                WHERE setting_key = ?
            ");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return $default;
            }

            $value = $this->formatValue($result['setting_value'], $result['type']);
            self::$cache[$key] = $value;

            return $value;
        } catch (Exception $e) {
            error_log("Error obteniendo configuración: " . $e->getMessage());
            return $default;
        }
    }

    private function formatValue($value, $type) {
        switch ($type) {
            case 'boolean':
                return $value == '1';
            case 'number':
                return (int)$value;
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }

    public function clearCache() {
        self::$cache = [];
    }
} 
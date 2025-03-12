<?php
class Settings {
    private $db;
    private $cache;
    private static $settings = null;

    public function __construct($db) {
        $this->db = $db;
    }

    public function get($key, $default = null) {
        if (self::$settings === null) {
            $this->loadSettings();
        }
        return self::$settings[$key] ?? $default;
    }

    public function set($key, $value, $type = 'string', $description = '', $is_public = false) {
        $stmt = $this->db->prepare("
            INSERT INTO system_settings (`key`, value, type, description, is_public)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            value = VALUES(value),
            type = VALUES(type),
            description = VALUES(description),
            is_public = VALUES(is_public)
        ");
        
        $stmt->execute([$key, $value, $type, $description, $is_public]);
        self::$settings[$key] = $value;
    }

    private function loadSettings() {
        $stmt = $this->db->query("SELECT `key`, value FROM system_settings");
        self::$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
} 
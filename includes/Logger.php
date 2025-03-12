<?php
class Logger {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function log($action, $entity_type, $entity_id = null, $details = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO system_logs (
                    user_id,
                    action,
                    entity_type,
                    entity_id,
                    details,
                    ip_address,
                    user_agent
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $_SESSION['user_id'] ?? null,
                $action,
                $entity_type,
                $entity_id,
                $details ? json_encode($details) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Error registrando log: " . $e->getMessage());
            return false;
        }
    }
} 
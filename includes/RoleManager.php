<?php
class RoleManager {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function createRole($name, $description = '') {
        $stmt = $this->conn->prepare("
            INSERT INTO roles (name, description)
            VALUES (?, ?)
        ");
        return $stmt->execute([$name, $description]);
    }
    
    public function updateRole($id, $name, $description = '') {
        $stmt = $this->conn->prepare("
            UPDATE roles 
            SET name = ?, description = ?
            WHERE id = ?
        ");
        return $stmt->execute([$name, $description, $id]);
    }
    
    public function deleteRole($id) {
        $stmt = $this->conn->prepare("DELETE FROM roles WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function getRoles() {
        return $this->conn->query("SELECT * FROM roles ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getRole($id) {
        $stmt = $this->conn->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function assignPermission($role_id, $permission_id) {
        $stmt = $this->conn->prepare("
            INSERT IGNORE INTO role_permissions (role_id, permission_id)
            VALUES (?, ?)
        ");
        return $stmt->execute([$role_id, $permission_id]);
    }
    
    public function removePermission($role_id, $permission_id) {
        $stmt = $this->conn->prepare("
            DELETE FROM role_permissions 
            WHERE role_id = ? AND permission_id = ?
        ");
        return $stmt->execute([$role_id, $permission_id]);
    }
    
    public function getRolePermissions($role_id) {
        $stmt = $this->conn->prepare("
            SELECT p.* 
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?
        ");
        $stmt->execute([$role_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function hasPermission($user_id, $permission_code) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count
            FROM users u
            JOIN role_permissions rp ON u.role_id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE u.id = ? AND p.code = ?
        ");
        $stmt->execute([$user_id, $permission_code]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    }
    
    public function createPermission($name, $code, $description = '') {
        $stmt = $this->conn->prepare("
            INSERT INTO permissions (name, code, description)
            VALUES (?, ?, ?)
        ");
        return $stmt->execute([$name, $code, $description]);
    }
    
    public function getPermissions() {
        return $this->conn->query("
            SELECT * FROM permissions ORDER BY name
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
} 
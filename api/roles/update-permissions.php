<?php
require_once '../../includes/header.php';
require_once '../../includes/RoleManager.php';

if (!is_admin()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['role_id'])) {
        throw new Exception("ID de rol no proporcionado");
    }
    
    $role_manager = new RoleManager($conn);
    
    // Comenzar transacción
    $conn->beginTransaction();
    
    // Eliminar permisos existentes
    $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
    $stmt->execute([$data['role_id']]);
    
    // Asignar nuevos permisos
    if (!empty($data['permissions'])) {
        foreach ($data['permissions'] as $permission_id) {
            $role_manager->assignPermission($data['role_id'], $permission_id);
        }
    }
    
    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
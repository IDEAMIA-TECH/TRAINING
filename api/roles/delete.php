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
    
    if (!isset($data['id'])) {
        throw new Exception("ID de rol no proporcionado");
    }
    
    // Evitar eliminar el rol de administrador
    if ($data['id'] == 1) {
        throw new Exception("No se puede eliminar el rol de administrador");
    }
    
    $role_manager = new RoleManager($conn);
    $success = $role_manager->deleteRole($data['id']);
    
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
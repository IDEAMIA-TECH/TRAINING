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
    
    if (!isset($data['name']) || empty(trim($data['name']))) {
        throw new Exception("El nombre del rol es requerido");
    }
    
    $role_manager = new RoleManager($conn);
    
    // Si es una actualización
    if (isset($data['id'])) {
        $success = $role_manager->updateRole(
            $data['id'],
            $data['name'],
            $data['description'] ?? ''
        );
    }
    // Si es un nuevo rol
    else {
        $success = $role_manager->createRole(
            $data['name'],
            $data['description'] ?? ''
        );
    }
    
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
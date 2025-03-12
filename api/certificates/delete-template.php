<?php
require_once '../../includes/header.php';

if (!has_permission('manage_certificates')) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        throw new Exception("ID no especificado");
    }
    
    // Verificar si es la única plantilla activa
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM certificate_templates 
        WHERE is_active = TRUE AND id != ?
    ");
    $stmt->execute([$data['id']]);
    
    if ($stmt->fetchColumn() == 0) {
        throw new Exception("No se puede eliminar la única plantilla activa");
    }
    
    // Eliminar plantilla
    $stmt = $conn->prepare("DELETE FROM certificate_templates WHERE id = ?");
    $stmt->execute([$data['id']]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
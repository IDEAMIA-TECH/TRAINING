<?php
require_once '../../includes/header.php';

if (!has_permission('manage_certificates')) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validar datos
    if (empty($data['name']) || empty($data['html_template'])) {
        throw new Exception("Datos incompletos");
    }
    
    if (empty($data['id'])) {
        // Crear nueva plantilla
        $stmt = $conn->prepare("
            INSERT INTO certificate_templates (
                name, description, html_template, css_styles, is_active
            ) VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['name'],
            $data['description'],
            $data['html_template'],
            $data['css_styles'],
            $data['is_active']
        ]);
        
    } else {
        // Actualizar plantilla existente
        $stmt = $conn->prepare("
            UPDATE certificate_templates 
            SET name = ?, 
                description = ?, 
                html_template = ?, 
                css_styles = ?,
                is_active = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['name'],
            $data['description'],
            $data['html_template'],
            $data['css_styles'],
            $data['is_active'],
            $data['id']
        ]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
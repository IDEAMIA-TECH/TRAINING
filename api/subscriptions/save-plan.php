<?php
require_once '../../includes/header.php';

if (!has_permission('manage_subscriptions')) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validar datos
    if (empty($data['name']) || !isset($data['price']) || empty($data['duration'])) {
        throw new Exception("Datos incompletos");
    }
    
    // Preparar características como JSON
    $features = json_encode($data['features'] ?? []);
    
    if (empty($data['id'])) {
        // Crear nuevo plan
        $stmt = $conn->prepare("
            INSERT INTO subscription_plans (
                name, description, price, duration, features, is_active
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['name'],
            $data['description'],
            $data['price'],
            $data['duration'],
            $features,
            $data['is_active']
        ]);
        
    } else {
        // Actualizar plan existente
        $stmt = $conn->prepare("
            UPDATE subscription_plans 
            SET name = ?, 
                description = ?, 
                price = ?, 
                duration = ?, 
                features = ?,
                is_active = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['name'],
            $data['description'],
            $data['price'],
            $data['duration'],
            $features,
            $data['is_active'],
            $data['id']
        ]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
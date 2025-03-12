<?php
require_once '../../includes/header.php';

if (!has_permission('manage_subscriptions')) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        throw new Exception("ID no especificado");
    }
    
    // Verificar si hay suscripciones activas
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM subscriptions 
        WHERE plan_id = ? AND status = 'active'
    ");
    $stmt->execute([$data['id']]);
    
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("No se puede eliminar un plan con suscripciones activas");
    }
    
    // Eliminar plan
    $stmt = $conn->prepare("DELETE FROM subscription_plans WHERE id = ?");
    $stmt->execute([$data['id']]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
<?php
require_once '../../includes/header.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        throw new Exception("ID no especificado");
    }
    
    // Verificar permisos
    $stmt = $conn->prepare("
        SELECT user_id FROM subscriptions WHERE id = ?
    ");
    $stmt->execute([$data['id']]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$subscription) {
        throw new Exception("Suscripción no encontrada");
    }
    
    if ($subscription['user_id'] != $_SESSION['user_id'] && !has_permission('manage_subscriptions')) {
        throw new Exception("No tienes permiso para cancelar esta suscripción");
    }
    
    // Cancelar suscripción
    $stmt = $conn->prepare("
        UPDATE subscriptions 
        SET status = 'cancelled', 
            auto_renew = FALSE 
        WHERE id = ?
    ");
    $stmt->execute([$data['id']]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
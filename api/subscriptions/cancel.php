<?php
require_once '../../includes/header.php';
require_once '../../includes/PaymentManager.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['subscription_id'])) {
        throw new Exception("ID de suscripción no especificado");
    }
    
    // Verificar que la suscripción pertenezca al usuario
    $stmt = $conn->prepare("
        SELECT * FROM subscriptions 
        WHERE id = ? AND user_id = ? AND status = 'active'
    ");
    $stmt->execute([$data['subscription_id'], $_SESSION['user_id']]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$subscription) {
        throw new Exception("Suscripción no válida");
    }
    
    // Cancelar suscripción en Stripe
    $payment_manager = new PaymentManager($conn);
    $payment_manager->cancelSubscription($subscription['stripe_subscription_id']);
    
    // Actualizar estado en la base de datos
    $stmt = $conn->prepare("
        UPDATE subscriptions 
        SET status = 'cancelled',
            auto_renew = FALSE
        WHERE id = ?
    ");
    $stmt->execute([$subscription['id']]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
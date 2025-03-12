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
    
    if (empty($data['plan_id'])) {
        throw new Exception("Plan no especificado");
    }
    
    $payment_manager = new PaymentManager($conn);
    $transaction_id = $payment_manager->createSubscription(
        $_SESSION['user_id'],
        $data['plan_id']
    );
    
    // Si es PayPal, generar URL de redirección
    if (isset($data['payment_method']) && $data['payment_method'] === 'paypal') {
        $paypal_url = $payment_manager->createPayPalOrder($transaction_id);
        echo json_encode([
            'success' => true,
            'transaction_id' => $transaction_id,
            'paypal_url' => $paypal_url
        ]);
    } else {
        // Para Stripe, generar client_secret
        $client_secret = $payment_manager->createStripePaymentIntent($transaction_id);
        echo json_encode([
            'success' => true,
            'transaction_id' => $transaction_id,
            'client_secret' => $client_secret
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
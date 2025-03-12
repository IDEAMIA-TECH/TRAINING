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
    
    if (empty($data['transaction_id']) || empty($data['payment_method'])) {
        throw new Exception("Datos incompletos");
    }
    
    $payment_manager = new PaymentManager($conn);
    $success = $payment_manager->processPayment(
        $data['transaction_id'],
        $data['payment_method']
    );
    
    echo json_encode(['success' => $success]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
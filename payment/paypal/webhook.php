<?php
require_once '../../includes/init.php';
require_once '../../includes/payment/PayPalAPI.php';
require_once '../../includes/payment/WebhookHandler.php';

try {
    $payload = file_get_contents('php://input');
    $data = json_decode($payload, true);

    // Validar webhook
    $paypal = new PayPalAPI();
    if (!$paypal->validateWebhook(getallheaders(), $payload)) {
        throw new Exception('Invalid webhook signature');
    }

    $webhookHandler = new WebhookHandler($db, $logger);
    $webhookHandler->handlePayPalWebhook($data);

    http_response_code(200);
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    error_log("PayPal Webhook Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 
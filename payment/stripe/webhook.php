<?php
require_once '../../includes/init.php';
require_once '../../includes/payment/StripeAPI.php';
require_once '../../includes/payment/WebhookHandler.php';

try {
    $payload = file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];

    $stripe = new StripeAPI();
    $event = $stripe->validateWebhookSignature($payload, $sig_header);

    if (!$event) {
        throw new Exception('Invalid webhook signature');
    }

    $webhookHandler = new WebhookHandler($db, $logger);
    $webhookHandler->handleStripeWebhook($event);

    http_response_code(200);
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    error_log("Stripe Webhook Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 
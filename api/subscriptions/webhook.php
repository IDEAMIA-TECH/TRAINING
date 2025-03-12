<?php
require_once '../../includes/header.php';
require_once '../../includes/PaymentManager.php';

try {
    $payload = @file_get_contents('php://input');
    $event = null;
    
    // Verificar firma de Stripe
    try {
        $event = \Stripe\Webhook::constructEvent(
            $payload, $_SERVER['HTTP_STRIPE_SIGNATURE'], STRIPE_WEBHOOK_SECRET
        );
    } catch(\UnexpectedValueException $e) {
        http_response_code(400);
        exit();
    } catch(\Stripe\Exception\SignatureVerificationException $e) {
        http_response_code(400);
        exit();
    }
    
    $payment_manager = new PaymentManager($conn);
    
    // Manejar eventos
    switch ($event->type) {
        case 'payment_intent.succeeded':
            $payment_intent = $event->data->object;
            $payment_manager->handleSuccessfulPayment($payment_intent->id);
            break;
            
        case 'payment_intent.payment_failed':
            $payment_intent = $event->data->object;
            $payment_manager->handleFailedPayment($payment_intent->id);
            break;
            
        case 'customer.subscription.deleted':
            $subscription = $event->data->object;
            $payment_manager->handleCancelledSubscription($subscription->id);
            break;
    }
    
    http_response_code(200);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
} 
<?php
require_once '../../includes/config.php';
require_once '../../includes/PaymentManager.php';

// Obtener el payload del webhook
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];

try {
    // Verificar firma del webhook
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, STRIPE_WEBHOOK_SECRET
    );
    
    // Manejar eventos específicos
    switch ($event->type) {
        case 'checkout.session.completed':
            $session = $event->data->object;
            
            // Procesar el pago exitoso
            $payment_manager = new PaymentManager($conn);
            $payment_manager->processPayment($session->id);
            break;
            
        case 'customer.subscription.deleted':
            $subscription = $event->data->object;
            
            // Actualizar estado de suscripción
            $stmt = $conn->prepare("
                UPDATE subscriptions 
                SET status = 'cancelled', 
                    end_date = NOW()
                WHERE stripe_subscription_id = ?
            ");
            $stmt->execute([$subscription->id]);
            break;
            
        case 'invoice.payment_failed':
            $invoice = $event->data->object;
            
            // Registrar error de pago
            $stmt = $conn->prepare("
                INSERT INTO transactions (
                    user_id, subscription_id, amount,
                    payment_method, status, error_message
                ) VALUES (?, ?, ?, 'stripe', 'failed', ?)
            ");
            $stmt->execute([
                $invoice->customer,
                $invoice->subscription,
                $invoice->amount_due / 100,
                $invoice->last_payment_error['message'] ?? 'Pago fallido'
            ]);
            break;
    }
    
    http_response_code(200);
    
} catch(\UnexpectedValueException $e) {
    http_response_code(400);
    exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit();
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit();
} 
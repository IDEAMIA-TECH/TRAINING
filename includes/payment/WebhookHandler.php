<?php
class WebhookHandler {
    private $db;
    private $logger;

    public function __construct($db, $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }

    public function handlePayPalWebhook($event) {
        try {
            $this->db->beginTransaction();

            switch ($event['event_type']) {
                case 'PAYMENT.CAPTURE.COMPLETED':
                    $this->handlePaymentCompleted('paypal', $event);
                    break;

                case 'PAYMENT.CAPTURE.DENIED':
                case 'PAYMENT.CAPTURE.REVERSED':
                    $this->handlePaymentFailed('paypal', $event);
                    break;

                case 'PAYMENT.CAPTURE.REFUNDED':
                    $this->handlePaymentRefunded('paypal', $event);
                    break;
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->log('error', 'webhook', null, [
                'provider' => 'paypal',
                'error' => $e->getMessage(),
                'event' => $event
            ]);
            throw $e;
        }
    }

    public function handleStripeWebhook($event) {
        try {
            $this->db->beginTransaction();

            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->handlePaymentCompleted('stripe', $event->data->object);
                    break;

                case 'charge.failed':
                    $this->handlePaymentFailed('stripe', $event->data->object);
                    break;

                case 'charge.refunded':
                    $this->handlePaymentRefunded('stripe', $event->data->object);
                    break;
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->log('error', 'webhook', null, [
                'provider' => 'stripe',
                'error' => $e->getMessage(),
                'event' => $event->type
            ]);
            throw $e;
        }
    }

    private function handlePaymentCompleted($provider, $event) {
        $transaction_id = $this->getTransactionId($provider, $event);
        
        // Actualizar estado del pago
        $stmt = $this->db->prepare("
            UPDATE payments 
            SET status = 'completed',
                updated_at = CURRENT_TIMESTAMP 
            WHERE transaction_id = ? 
            AND payment_method = ?
        ");
        $stmt->execute([$transaction_id, $provider]);

        // Obtener información del pago
        $stmt = $this->db->prepare("
            SELECT * FROM payments 
            WHERE transaction_id = ? 
            AND payment_method = ?
        ");
        $stmt->execute([$transaction_id, $provider]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            throw new Exception("Payment not found: {$transaction_id}");
        }

        // Crear registro de inscripción si no existe
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO course_registrations (
                course_id,
                user_id,
                payment_id,
                status
            ) VALUES (?, ?, ?, 'confirmed')
        ");
        $stmt->execute([
            $payment['course_id'],
            $payment['user_id'],
            $payment['id']
        ]);

        // Registrar en el log
        $this->logger->log('payment_completed', 'payment', $payment['id'], [
            'provider' => $provider,
            'amount' => $payment['amount']
        ]);

        // Enviar email de confirmación
        $this->sendPaymentConfirmationEmail($payment);
    }

    private function handlePaymentFailed($provider, $event) {
        $transaction_id = $this->getTransactionId($provider, $event);
        
        $stmt = $this->db->prepare("
            UPDATE payments 
            SET status = 'failed',
                updated_at = CURRENT_TIMESTAMP 
            WHERE transaction_id = ? 
            AND payment_method = ?
        ");
        $stmt->execute([$transaction_id, $provider]);

        $this->logger->log('payment_failed', 'payment', null, [
            'provider' => $provider,
            'transaction_id' => $transaction_id
        ]);
    }

    private function handlePaymentRefunded($provider, $event) {
        $transaction_id = $this->getTransactionId($provider, $event);
        
        $stmt = $this->db->prepare("
            UPDATE payments 
            SET status = 'refunded',
                updated_at = CURRENT_TIMESTAMP 
            WHERE transaction_id = ? 
            AND payment_method = ?
        ");
        $stmt->execute([$transaction_id, $provider]);

        // Cancelar inscripción
        $stmt = $this->db->prepare("
            UPDATE course_registrations cr
            JOIN payments p ON cr.payment_id = p.id
            SET cr.status = 'cancelled'
            WHERE p.transaction_id = ? 
            AND p.payment_method = ?
        ");
        $stmt->execute([$transaction_id, $provider]);

        $this->logger->log('payment_refunded', 'payment', null, [
            'provider' => $provider,
            'transaction_id' => $transaction_id
        ]);
    }

    private function getTransactionId($provider, $event) {
        switch ($provider) {
            case 'paypal':
                return $event['resource']['id'];
            case 'stripe':
                return $event->id;
            default:
                throw new Exception("Invalid payment provider: {$provider}");
        }
    }

    private function sendPaymentConfirmationEmail($payment) {
        $stmt = $this->db->prepare("
            SELECT u.*, c.* 
            FROM users u 
            JOIN payments p ON u.id = p.user_id
            JOIN courses c ON p.course_id = c.id
            WHERE p.id = ?
        ");
        $stmt->execute([$payment['id']]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $emailSender = new EmailSender();
        $emailSender->sendPaymentConfirmation(
            [
                'name' => $data['name'],
                'email' => $data['email']
            ],
            [
                'transaction_id' => $payment['transaction_id'],
                'amount' => $payment['amount'],
                'created_at' => $payment['created_at']
            ],
            [
                'title' => $data['title']
            ]
        );
    }
} 
<?php
require_once 'PaymentGateway.php';

class StripeGateway implements PaymentGateway {
    private $stripe;
    private $apiKey;

    public function __construct() {
        $this->apiKey = STRIPE_SECRET_KEY;
        $this->initializeStripe();
    }

    private function initializeStripe() {
        // Inicializar el cliente de Stripe
        \Stripe\Stripe::setApiKey($this->apiKey);
    }

    public function createPayment(array $paymentData) {
        try {
            // Crear la intención de pago en Stripe
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $paymentData['amount'] * 100, // Stripe usa centavos
                'currency' => 'mxn',
                'description' => $paymentData['description'],
                'metadata' => [
                    'course_id' => $paymentData['course_id'],
                    'user_id' => $paymentData['user_id']
                ]
            ]);

            return [
                'id' => $paymentIntent->id,
                'client_secret' => $paymentIntent->client_secret
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new PaymentException("Error al crear el pago en Stripe: " . $e->getMessage());
        }
    }

    public function processPayment(string $paymentId, array $data) {
        try {
            $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentId);
            
            if ($paymentIntent->status === 'succeeded') {
                return [
                    'transaction_id' => $paymentIntent->id,
                    'status' => 'completed'
                ];
            }
            
            throw new PaymentException("El pago no se completó correctamente");
        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new PaymentException("Error al procesar el pago en Stripe: " . $e->getMessage());
        }
    }

    public function getPaymentStatus(string $paymentId) {
        try {
            $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentId);
            return $paymentIntent->status;
        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new PaymentException("Error al obtener el estado del pago: " . $e->getMessage());
        }
    }
} 
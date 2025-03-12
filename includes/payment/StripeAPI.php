<?php
require_once __DIR__ . '/../../vendor/autoload.php';

class StripeAPI {
    private $stripe;

    public function __construct() {
        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
        $this->stripe = new \Stripe\StripeClient(STRIPE_SECRET_KEY);
    }

    public function createSession($course, $user) {
        try {
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'mxn',
                        'product_data' => [
                            'name' => $course['title'],
                            'description' => "Inscripción al curso"
                        ],
                        'unit_amount' => $course['price'] * 100 // Stripe usa centavos
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => BASE_URL . '/payment/stripe/success.php?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => BASE_URL . '/payment/stripe/cancel.php',
                'customer_email' => $user['email'],
                'metadata' => [
                    'course_id' => $course['id'],
                    'user_id' => $user['id']
                ]
            ]);

            return $session;
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log("Error Stripe createSession: " . $e->getMessage());
            throw new Exception('Error al crear la sesión de pago');
        }
    }

    public function validateWebhookSignature($payload, $sig_header) {
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                STRIPE_WEBHOOK_SECRET
            );
            return $event;
        } catch (\UnexpectedValueException $e) {
            error_log("Invalid payload: " . $e->getMessage());
            return false;
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            error_log("Invalid signature: " . $e->getMessage());
            return false;
        }
    }

    public function getSession($session_id) {
        try {
            return $this->stripe->checkout->sessions->retrieve($session_id);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log("Error retrieving session: " . $e->getMessage());
            throw new Exception('Error al recuperar la información del pago');
        }
    }
} 
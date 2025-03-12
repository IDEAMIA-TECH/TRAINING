<?php
require_once 'vendor/autoload.php';
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\Subscription;

class PaymentManager {
    private $conn;
    private $stripe;
    
    public function __construct($conn) {
        $this->conn = $conn;
        
        // Inicializar Stripe
        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
        $this->stripe = new \Stripe\StripeClient(STRIPE_SECRET_KEY);
    }
    
    public function getSubscriptionPlans() {
        $stmt = $this->conn->prepare("
            SELECT * FROM subscription_plans 
            WHERE is_active = TRUE 
            ORDER BY price ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function createCheckoutSession($planId, $userId) {
        // Obtener información del plan
        $stmt = $this->conn->prepare("
            SELECT * FROM subscription_plans WHERE id = ?
        ");
        $stmt->execute([$planId]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$plan) {
            throw new Exception("Plan no encontrado");
        }
        
        // Crear sesión de checkout en Stripe
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $plan['name'],
                        'description' => $plan['description']
                    ],
                    'unit_amount' => $plan['price'] * 100 // Convertir a centavos
                ],
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'success_url' => BASE_URL . '/payments/success.php?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => BASE_URL . '/payments/cancel.php',
            'customer_email' => $_SESSION['user_email'],
            'metadata' => [
                'user_id' => $userId,
                'plan_id' => $planId
            ]
        ]);
        
        return $session;
    }
    
    public function processPayment($sessionId) {
        try {
            $session = $this->stripe->checkout->sessions->retrieve($sessionId);
            
            if ($session->payment_status !== 'paid') {
                throw new Exception("El pago no ha sido completado");
            }
            
            $this->conn->beginTransaction();
            
            // Crear suscripción
            $stmt = $this->conn->prepare("
                INSERT INTO subscriptions (
                    user_id, plan_id, start_date, end_date, auto_renew
                ) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? MONTH), TRUE)
            ");
            
            $stmt->execute([
                $session->metadata->user_id,
                $session->metadata->plan_id,
                $plan['duration_months']
            ]);
            
            $subscriptionId = $this->conn->lastInsertId();
            
            // Registrar transacción
            $stmt = $this->conn->prepare("
                INSERT INTO transactions (
                    user_id, subscription_id, amount, payment_method,
                    payment_id, status
                ) VALUES (?, ?, ?, 'stripe', ?, 'completed')
            ");
            
            $stmt->execute([
                $session->metadata->user_id,
                $subscriptionId,
                $session->amount_total / 100,
                $session->payment_intent
            ]);
            
            $transactionId = $this->conn->lastInsertId();
            
            // Generar factura
            $this->generateInvoice($transactionId);
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    private function generateInvoice($transactionId) {
        // Obtener datos de la transacción
        $stmt = $this->conn->prepare("
            SELECT t.*, u.name, u.email
            FROM transactions t
            JOIN users u ON t.user_id = u.id
            WHERE t.id = ?
        ");
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calcular impuestos
        $subtotal = $transaction['amount'];
        $tax = $subtotal * 0.16; // 16% de IVA
        $total = $subtotal + $tax;
        
        // Generar número de factura
        $invoiceNumber = 'INV-' . date('Y') . str_pad($transactionId, 6, '0', STR_PAD_LEFT);
        
        // Guardar factura
        $stmt = $this->conn->prepare("
            INSERT INTO invoices (
                transaction_id, invoice_number, billing_name,
                billing_email, subtotal, tax, total
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $transactionId,
            $invoiceNumber,
            $transaction['name'],
            $transaction['email'],
            $subtotal,
            $tax,
            $total
        ]);
        
        return $this->conn->lastInsertId();
    }
} 
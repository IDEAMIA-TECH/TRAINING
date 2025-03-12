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
        
        // Configurar Stripe
        $stmt = $this->conn->prepare("
            SELECT setting_value 
            FROM payment_settings 
            WHERE setting_key = 'stripe_secret_key'
        ");
        $stmt->execute();
        $stripe_key = $stmt->fetchColumn();
        
        Stripe::setApiKey($stripe_key);
    }
    
    public function createSubscription($userId, $planId) {
        try {
            $this->conn->beginTransaction();
            
            // Obtener información del plan
            $stmt = $this->conn->prepare("
                SELECT * FROM subscription_plans WHERE id = ?
            ");
            $stmt->execute([$planId]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$plan) {
                throw new Exception("Plan no encontrado");
            }
            
            // Crear suscripción en la base de datos
            $stmt = $this->conn->prepare("
                INSERT INTO subscriptions (
                    user_id, plan_id, start_date, end_date
                ) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY))
            ");
            $stmt->execute([
                $userId,
                $planId,
                $plan['duration']
            ]);
            
            $subscriptionId = $this->conn->lastInsertId();
            
            // Crear transacción pendiente
            $stmt = $this->conn->prepare("
                INSERT INTO transactions (
                    user_id, subscription_id, amount, currency
                ) VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $subscriptionId,
                $plan['price'],
                'USD'
            ]);
            
            $transactionId = $this->conn->lastInsertId();
            
            $this->conn->commit();
            return $transactionId;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    public function processPayment($transactionId, $paymentMethod) {
        try {
            // Obtener información de la transacción
            $stmt = $this->conn->prepare("
                SELECT t.*, u.email, u.name
                FROM transactions t
                JOIN users u ON t.user_id = u.id
                WHERE t.id = ?
            ");
            $stmt->execute([$transactionId]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$transaction) {
                throw new Exception("Transacción no encontrada");
            }
            
            // Procesar pago según el método
            switch ($paymentMethod) {
                case 'stripe':
                    $paymentId = $this->processStripePayment($transaction);
                    break;
                case 'paypal':
                    $paymentId = $this->processPayPalPayment($transaction);
                    break;
                default:
                    throw new Exception("Método de pago no soportado");
            }
            
            // Actualizar transacción
            $stmt = $this->conn->prepare("
                UPDATE transactions
                SET status = 'completed',
                    payment_method = ?,
                    payment_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$paymentMethod, $paymentId, $transactionId]);
            
            // Generar factura
            $this->generateInvoice($transactionId);
            
            return true;
            
        } catch (Exception $e) {
            // Registrar error
            $stmt = $this->conn->prepare("
                UPDATE transactions
                SET status = 'failed',
                    error_message = ?
                WHERE id = ?
            ");
            $stmt->execute([$e->getMessage(), $transactionId]);
            
            throw $e;
        }
    }
    
    private function processStripePayment($transaction) {
        try {
            // Crear cliente en Stripe si no existe
            $stmt = $this->conn->prepare("
                SELECT stripe_customer_id 
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$transaction['user_id']]);
            $stripeCustomerId = $stmt->fetchColumn();
            
            if (!$stripeCustomerId) {
                $customer = Customer::create([
                    'email' => $transaction['email'],
                    'name' => $transaction['name']
                ]);
                
                $stmt = $this->conn->prepare("
                    UPDATE users 
                    SET stripe_customer_id = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$customer->id, $transaction['user_id']]);
                
                $stripeCustomerId = $customer->id;
            }
            
            // Crear intento de pago
            $paymentIntent = PaymentIntent::create([
                'amount' => $transaction['amount'] * 100, // Convertir a centavos
                'currency' => $transaction['currency'],
                'customer' => $stripeCustomerId,
                'description' => 'Suscripción a plan de cursos'
            ]);
            
            return $paymentIntent->id;
            
        } catch (Exception $e) {
            throw new Exception("Error al procesar pago con Stripe: " . $e->getMessage());
        }
    }
    
    private function processPayPalPayment($transaction) {
        // Implementar integración con PayPal
        throw new Exception("Pago con PayPal no implementado");
    }
    
    private function generateInvoice($transactionId) {
        try {
            // Obtener información de la transacción
            $stmt = $this->conn->prepare("
                SELECT t.*, u.*, sp.name as plan_name
                FROM transactions t
                JOIN users u ON t.user_id = u.id
                LEFT JOIN subscriptions s ON t.subscription_id = s.id
                LEFT JOIN subscription_plans sp ON s.plan_id = sp.id
                WHERE t.id = ?
            ");
            $stmt->execute([$transactionId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Obtener siguiente número de factura
            $stmt = $this->conn->prepare("
                SELECT setting_value 
                FROM payment_settings 
                WHERE setting_key = 'invoice_prefix'
            ");
            $stmt->execute();
            $prefix = $stmt->fetchColumn();
            
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) + 1 FROM invoices
            ");
            $stmt->execute();
            $number = $stmt->fetchColumn();
            
            $invoiceNumber = $prefix . str_pad($number, 6, '0', STR_PAD_LEFT);
            
            // Crear factura
            $stmt = $this->conn->prepare("
                INSERT INTO invoices (
                    transaction_id, invoice_number, user_id,
                    billing_name, billing_email, billing_address,
                    subtotal, tax_amount, total_amount,
                    status, issued_date, due_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'issued', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))
            ");
            
            $taxRate = 0.16; // Obtener de configuración
            $subtotal = $data['amount'];
            $taxAmount = $subtotal * $taxRate;
            $total = $subtotal + $taxAmount;
            
            $stmt->execute([
                $transactionId,
                $invoiceNumber,
                $data['user_id'],
                $data['name'],
                $data['email'],
                $data['address'],
                $subtotal,
                $taxAmount,
                $total
            ]);
            
            $invoiceId = $this->conn->lastInsertId();
            
            // Agregar items a la factura
            $stmt = $this->conn->prepare("
                INSERT INTO invoice_items (
                    invoice_id, description, quantity,
                    unit_price, tax_rate, total_amount
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $invoiceId,
                "Suscripción - " . $data['plan_name'],
                1,
                $subtotal,
                $taxRate,
                $total
            ]);
            
            return $invoiceId;
            
        } catch (Exception $e) {
            throw new Exception("Error al generar factura: " . $e->getMessage());
        }
    }
} 
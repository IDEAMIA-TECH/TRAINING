<?php
require_once '../includes/init.php';
require_once '../includes/payment/PayPalGateway.php';
require_once '../includes/payment/StripeGateway.php';

if (!$user_authenticated) {
    redirect('/login.php');
}

$payment_id = (int)($_GET['id'] ?? 0);
if (!$payment_id) {
    redirect('/courses.php');
}

try {
    // Obtener información del pago
    $stmt = $db->prepare("
        SELECT p.*, c.title as course_title, c.price
        FROM payments p
        JOIN courses c ON p.course_id = c.id
        WHERE p.id = ? AND p.user_id = ? AND p.status = 'pending'
    ");
    $stmt->execute([$payment_id, $_SESSION['user_id']]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        redirect('/courses.php');
    }

    // Inicializar el gateway de pago correspondiente
    $gateway = null;
    switch ($payment['payment_method']) {
        case 'paypal':
            $gateway = new PayPalGateway();
            break;
        case 'stripe':
            $gateway = new StripeGateway();
            break;
        default:
            throw new Exception("Método de pago no válido");
    }

    // Si es una solicitud POST, procesar el pago
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $db->beginTransaction();
            
            $paymentResult = $gateway->processPayment($payment['id'], $_POST);
            
            if ($paymentResult['status'] === 'completed') {
                // Actualizar estado del pago
                $stmt = $db->prepare("
                    UPDATE payments 
                    SET status = 'completed', 
                        transaction_id = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$paymentResult['transaction_id'], $payment_id]);

                // Actualizar estado de la inscripción
                $stmt = $db->prepare("
                    UPDATE course_registrations 
                    SET status = 'confirmed'
                    WHERE payment_id = ?
                ");
                $stmt->execute([$payment_id]);

                // Obtener información del usuario
                $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                // Enviar email de confirmación de pago
                $emailSender = new EmailSender();
                $emailSender->sendPaymentConfirmation($user, [
                    'amount' => $payment['price'],
                    'transaction_id' => $paymentResult['transaction_id'],
                    'created_at' => date('Y-m-d H:i:s')
                ], $course);

                $db->commit();
                redirect('/payment_success.php?id=' . $payment_id);
            }
        } catch (Exception $e) {
            $db->rollBack();
            $error = $e->getMessage();
        }
    }

    // Crear el pago en el gateway
    $paymentData = [
        'amount' => $payment['price'],
        'description' => "Pago del curso: " . $payment['course_title'],
        'course_id' => $payment['course_id'],
        'user_id' => $_SESSION['user_id']
    ];
    
    $gatewayPayment = $gateway->createPayment($paymentData);
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<?php require_once '../templates/header.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Procesar Pago</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php else: ?>
                        <h5>Resumen de la Compra</h5>
                        <p><strong>Curso:</strong> <?php echo htmlspecialchars($payment['course_title']); ?></p>
                        <p><strong>Monto:</strong> $<?php echo number_format($payment['price'], 2); ?></p>
                        <p><strong>Método de Pago:</strong> <?php echo ucfirst($payment['payment_method']); ?></p>

                        <?php if ($payment['payment_method'] === 'paypal'): ?>
                            <!-- Botón de PayPal -->
                            <div id="paypal-button-container"></div>
                            <script src="https://www.paypal.com/sdk/js?client-id=<?php echo PAYPAL_CLIENT_ID; ?>&currency=MXN"></script>
                            <script>
                                paypal.Buttons({
                                    createOrder: function(data, actions) {
                                        return actions.order.create({
                                            purchase_units: [{
                                                amount: {
                                                    value: '<?php echo $payment['price']; ?>'
                                                }
                                            }]
                                        });
                                    },
                                    onApprove: function(data, actions) {
                                        return actions.order.capture().then(function(details) {
                                            // Enviar el formulario con los detalles del pago
                                            document.getElementById('paypal-transaction').value = details.id;
                                            document.getElementById('payment-form').submit();
                                        });
                                    }
                                }).render('#paypal-button-container');
                            </script>
                        <?php else: ?>
                            <!-- Formulario de Stripe -->
                            <form id="payment-form" method="POST">
                                <div id="card-element" class="mb-3">
                                    <!-- Stripe Elements se insertará aquí -->
                                </div>
                                <div id="card-errors" class="alert alert-danger d-none"></div>
                                <button type="submit" class="btn btn-primary" id="submit-button">
                                    Pagar $<?php echo number_format($payment['price'], 2); ?>
                                </button>
                            </form>

                            <script src="https://js.stripe.com/v3/"></script>
                            <script>
                                var stripe = Stripe('<?php echo STRIPE_PUBLIC_KEY; ?>');
                                var elements = stripe.elements();
                                var card = elements.create('card');
                                card.mount('#card-element');

                                var form = document.getElementById('payment-form');
                                form.addEventListener('submit', function(event) {
                                    event.preventDefault();
                                    stripe.confirmCardPayment('<?php echo $gatewayPayment['client_secret']; ?>', {
                                        payment_method: {
                                            card: card,
                                        }
                                    }).then(function(result) {
                                        if (result.error) {
                                            var errorElement = document.getElementById('card-errors');
                                            errorElement.textContent = result.error.message;
                                            errorElement.classList.remove('d-none');
                                        } else {
                                            form.submit();
                                        }
                                    });
                                });
                            </script>
                        <?php endif; ?>

                        <form id="payment-form" method="POST" class="d-none">
                            <input type="hidden" name="payment_id" value="<?php echo $payment_id; ?>">
                            <input type="hidden" name="transaction_id" id="paypal-transaction">
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?> 
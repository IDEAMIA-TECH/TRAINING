<?php
require_once '../includes/header.php';

if (!is_logged_in() || is_admin()) {
    header("Location: ../login.php");
    exit();
}

$error = '';
$success = '';
$enrollment = null;
$course = null;
$user_id = $_SESSION['user_id'];

// Obtener la inscripción y el curso
if (isset($_GET['enrollment_id'])) {
    $enrollment_id = (int)$_GET['enrollment_id'];
    
    $enrollment_query = "
        SELECT e.*, c.title, c.price, c.start_date, c.end_date, c.image_url
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.id = ? AND e.user_id = ? AND e.payment_status = 'pending'
    ";
    
    $stmt = $conn->prepare($enrollment_query);
    $stmt->execute([$enrollment_id, $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        header("Location: dashboard.php");
        exit();
    }
    
    $enrollment = $result;
    $course = $result;
}

// Procesar el pago
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $payment_method = sanitize_input($_POST['payment_method']);
    $reference = sanitize_input($_POST['reference']);
    
    if (empty($payment_method) || empty($reference)) {
        $error = "Por favor completa todos los campos";
    } else {
        try {
            // Registrar el pago
            $stmt = $conn->prepare("
                INSERT INTO payments (
                    enrollment_id, amount, payment_method, 
                    reference, status, created_at
                ) VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            
            if ($stmt->execute([
                $enrollment_id, 
                $course['price'],
                $payment_method,
                $reference
            ])) {
                // Actualizar estado de la inscripción
                $stmt = $conn->prepare("
                    UPDATE enrollments 
                    SET payment_status = 'pending_verification',
                        payment_amount = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                if ($stmt->execute([$course['price'], $enrollment_id])) {
                    $success = "Pago registrado exitosamente. En breve verificaremos tu pago.";
                } else {
                    $error = "Error al actualizar la inscripción";
                }
            } else {
                $error = "Error al registrar el pago";
            }
        } catch(PDOException $e) {
            $error = "Error en la base de datos: " . $e->getMessage();
        }
    }
}
?>

<div class="client-container">
    <div class="client-sidebar">
        <div class="user-info">
            <h3>Bienvenido,</h3>
            <p><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
        </div>
        <nav>
            <a href="dashboard.php">Mi Panel</a>
            <a href="profile.php">Mi Perfil</a>
            <a href="../courses/">Ver Cursos</a>
        </nav>
    </div>

    <div class="client-content">
        <div class="payment-container">
            <h2>Completar Pago</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <br>
                    <a href="dashboard.php">Volver al Panel</a>
                </div>
            <?php else: ?>
                <div class="payment-details">
                    <div class="course-summary">
                        <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                        <div class="course-info">
                            <p>
                                <strong>Fecha de inicio:</strong>
                                <?php echo date('d/m/Y H:i', strtotime($course['start_date'])); ?>
                            </p>
                            <p>
                                <strong>Fecha de fin:</strong>
                                <?php echo date('d/m/Y H:i', strtotime($course['end_date'])); ?>
                            </p>
                            <p class="price">
                                <strong>Monto a pagar:</strong>
                                $<?php echo number_format($course['price'], 2); ?> MXN
                            </p>
                        </div>
                    </div>

                    <div class="payment-instructions">
                        <h3>Instrucciones de Pago</h3>
                        <ol>
                            <li>Realiza una transferencia bancaria a la siguiente cuenta:</li>
                            <li>
                                <strong>Banco:</strong> BBVA<br>
                                <strong>Titular:</strong> Nombre de la Empresa<br>
                                <strong>Cuenta:</strong> 0123456789<br>
                                <strong>CLABE:</strong> 012345678901234567
                            </li>
                            <li>Una vez realizado el pago, registra los datos en el siguiente formulario.</li>
                            <li>Verificaremos tu pago y actualizaremos el estado de tu inscripción.</li>
                        </ol>
                    </div>

                    <div class="payment-methods">
                        <h3>Métodos de Pago</h3>
                        
                        <!-- PayPal -->
                        <div class="payment-method">
                            <h4>Pagar con PayPal</h4>
                            <input type="hidden" id="course_id" value="<?php echo $course['id']; ?>">
                            <div id="paypal-button-container"></div>
                        </div>
                        
                        <!-- Transferencia Bancaria -->
                        <div class="payment-method">
                            <h4>Transferencia Bancaria</h4>
                            <form method="POST" action="" class="payment-form">
                                <div class="form-group">
                                    <label for="payment_method">Método de Pago *</label>
                                    <select id="payment_method" name="payment_method" required>
                                        <option value="">Selecciona un método</option>
                                        <option value="transfer">Transferencia Bancaria</option>
                                        <option value="deposit">Depósito en Ventanilla</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="reference">Referencia de Pago *</label>
                                    <input type="text" id="reference" name="reference" required
                                           placeholder="Número de operación o referencia">
                                    <small class="form-text">
                                        Ingresa el número de operación o referencia de tu pago
                                    </small>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Registrar Pago</button>
                                    <a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
                                </div>
                            </form>
                        </div>

                        <!-- Pagar con Tarjeta -->
                        <div class="payment-method">
                            <h4>Pagar con Tarjeta</h4>
                            <button id="stripe-button" class="btn btn-primary">
                                Pagar con Tarjeta
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="loading" style="display: none;">
    <p>Procesando pago, por favor espera...</p>
</div>

<!-- Scripts de PayPal -->
<script src="https://www.paypal.com/sdk/js?client-id=<?php echo PAYPAL_CLIENT_ID; ?>&currency=<?php echo PAYPAL_CURRENCY; ?>"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/payment.js"></script>

<!-- Scripts de Stripe -->
<script src="https://js.stripe.com/v3/"></script>
<script>
const stripe = Stripe('<?php echo STRIPE_PUBLISHABLE_KEY; ?>');
const stripeButton = document.getElementById('stripe-button');

stripeButton.addEventListener('click', async (e) => {
    e.preventDefault();
    stripeButton.disabled = true;
    
    try {
        const response = await fetch('/api/payments/stripe.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'create_session',
                course_id: document.getElementById('course_id').value
            })
        });
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        // Redirigir a Stripe Checkout
        const result = await stripe.redirectToCheckout({
            sessionId: data.sessionId
        });
        
        if (result.error) {
            throw new Error(result.error.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Ocurrió un error al procesar el pago. Por favor intenta nuevamente.');
        stripeButton.disabled = false;
    }
});
</script>

<?php require_once '../includes/footer.php'; ?> 
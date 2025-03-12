<?php
require_once 'includes/header.php';
require_once 'includes/PaymentManager.php';

$payment_manager = new PaymentManager($conn);

// Obtener planes activos
$stmt = $conn->prepare("
    SELECT * FROM subscription_plans 
    WHERE is_active = TRUE 
    ORDER BY price ASC
");
$stmt->execute();
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener suscripción actual del usuario
$current_subscription = null;
if (is_logged_in()) {
    $stmt = $conn->prepare("
        SELECT s.*, sp.name as plan_name
        FROM subscriptions s
        JOIN subscription_plans sp ON s.plan_id = sp.id
        WHERE s.user_id = ? AND s.status = 'active'
        AND s.end_date > NOW()
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $current_subscription = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="plans-container">
    <div class="plans-header">
        <h1>Planes de Suscripción</h1>
        <?php if ($current_subscription): ?>
            <div class="current-plan">
                <p>
                    Plan actual: <strong><?php echo htmlspecialchars($current_subscription['plan_name']); ?></strong>
                    <br>
                    Válido hasta: <?php echo date('d/m/Y', strtotime($current_subscription['end_date'])); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="plans-grid">
        <?php foreach ($plans as $plan): ?>
            <?php $features = json_decode($plan['features'], true); ?>
            <div class="plan-card">
                <div class="plan-header">
                    <h2><?php echo htmlspecialchars($plan['name']); ?></h2>
                    <div class="plan-price">
                        <span class="currency">$</span>
                        <span class="amount"><?php echo number_format($plan['price'], 2); ?></span>
                        <span class="period">/mes</span>
                    </div>
                </div>
                
                <div class="plan-features">
                    <ul>
                        <?php foreach ($features as $feature): ?>
                            <li>
                                <i class="fas fa-check"></i>
                                <?php echo htmlspecialchars($feature); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="plan-footer">
                    <?php if (!is_logged_in()): ?>
                        <a href="login.php" class="btn btn-primary">
                            Iniciar Sesión para Suscribirse
                        </a>
                    <?php elseif ($current_subscription && $current_subscription['plan_id'] == $plan['id']): ?>
                        <button class="btn btn-secondary" disabled>Plan Actual</button>
                    <?php else: ?>
                        <button class="btn btn-primary subscribe-btn" 
                                data-plan-id="<?php echo $plan['id']; ?>"
                                data-plan-price="<?php echo $plan['price']; ?>">
                            Suscribirse
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal de pago -->
<div id="payment-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Completar Suscripción</h3>
            <button class="modal-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <div id="payment-form">
                <div class="form-group">
                    <label for="card-element">Tarjeta de Crédito o Débito</label>
                    <div id="card-element"></div>
                    <div id="card-errors" class="error-message"></div>
                </div>
                
                <button type="submit" class="btn btn-primary" id="submit-payment">
                    <i class="fas fa-lock"></i> Pagar Ahora
                </button>
                
                <div class="payment-methods">
                    <span>O pagar con:</span>
                    <button type="button" class="btn btn-paypal" id="paypal-button">
                        <i class="fab fa-paypal"></i> PayPal
                    </button>
                </div>
            </div>
            
            <div id="payment-success" style="display: none;">
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <h4>¡Pago Exitoso!</h4>
                    <p>Tu suscripción ha sido activada correctamente.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/payments.js"></script>

<?php require_once 'includes/footer.php'; ?> 
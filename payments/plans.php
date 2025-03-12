<?php
require_once '../includes/header.php';
require_once '../includes/PaymentManager.php';

if (!is_logged_in()) {
    header("Location: ../login.php");
    exit();
}

$payment_manager = new PaymentManager($conn);
$plans = $payment_manager->getSubscriptionPlans();
?>

<div class="plans-container">
    <div class="plans-header">
        <h1>Planes de Suscripción</h1>
        <p>Elige el plan que mejor se adapte a tus necesidades</p>
    </div>
    
    <div class="plans-grid">
        <?php foreach ($plans as $plan): ?>
            <?php 
            $features = json_decode($plan['features'], true);
            $isPopular = isset($features['discount']);
            ?>
            <div class="plan-card <?php echo $isPopular ? 'popular' : ''; ?>">
                <?php if ($isPopular): ?>
                    <div class="popular-badge">Más Popular</div>
                <?php endif; ?>
                
                <div class="plan-header">
                    <h2><?php echo htmlspecialchars($plan['name']); ?></h2>
                    <div class="plan-price">
                        <span class="currency">$</span>
                        <span class="amount"><?php echo number_format($plan['price'], 2); ?></span>
                        <span class="period">/mes</span>
                    </div>
                    <?php if (isset($features['discount'])): ?>
                        <div class="discount-badge">
                            Ahorra <?php echo $features['discount']; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="plan-features">
                    <ul>
                        <?php if ($features['courses'] === 'all'): ?>
                            <li>
                                <i class="fas fa-check"></i>
                                Acceso a todos los cursos
                            </li>
                        <?php else: ?>
                            <li>
                                <i class="fas fa-check"></i>
                                Acceso a cursos básicos
                            </li>
                        <?php endif; ?>
                        
                        <li>
                            <i class="fas fa-<?php echo $features['downloads'] ? 'check' : 'times'; ?>"></i>
                            Descarga de materiales
                        </li>
                        
                        <li>
                            <i class="fas fa-headset"></i>
                            Soporte <?php echo $features['support'] === 'priority' ? 'prioritario' : 'por email'; ?>
                        </li>
                        
                        <li>
                            <i class="fas fa-calendar-alt"></i>
                            Duración: <?php echo $plan['duration_months']; ?> 
                            <?php echo $plan['duration_months'] > 1 ? 'meses' : 'mes'; ?>
                        </li>
                    </ul>
                </div>
                
                <div class="plan-footer">
                    <form action="checkout.php" method="post" class="plan-form">
                        <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                        
                        <div class="coupon-input">
                            <input type="text" 
                                   name="coupon_code" 
                                   class="form-control" 
                                   placeholder="Código de descuento"
                                   data-plan-id="<?php echo $plan['id']; ?>">
                            <button type="button" 
                                    class="btn btn-outline-primary btn-sm"
                                    onclick="validateCoupon(this)">
                                Aplicar
                            </button>
                        </div>
                        
                        <div class="price-summary" style="display: none;">
                            <div class="original-price">
                                <span class="label">Precio original:</span>
                                <span class="value">$<?php echo number_format($plan['price'], 2); ?></span>
                            </div>
                            <div class="discount">
                                <span class="label">Descuento:</span>
                                <span class="value">-$0.00</span>
                            </div>
                            <div class="final-price">
                                <span class="label">Precio final:</span>
                                <span class="value">$<?php echo number_format($plan['price'], 2); ?></span>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">
                            Suscribirse
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/plans.css">

<?php require_once '../includes/footer.php'; ?> 
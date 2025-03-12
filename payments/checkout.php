<?php
require_once '../includes/header.php';
require_once '../includes/PaymentManager.php';

if (!is_logged_in()) {
    header("Location: ../login.php");
    exit();
}

if (empty($_POST['plan_id'])) {
    header("Location: plans.php");
    exit();
}

try {
    $payment_manager = new PaymentManager($conn);
    $session = $payment_manager->createCheckoutSession($_POST['plan_id'], $_SESSION['user_id']);
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: plans.php");
    exit();
}
?>

<div class="checkout-container">
    <div id="loading" class="loading-overlay">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Redirigiendo al checkout...</span>
        </div>
    </div>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script>
const stripe = Stripe('<?php echo STRIPE_PUBLIC_KEY; ?>');

stripe.redirectToCheckout({
    sessionId: '<?php echo $session->id; ?>'
}).then(function (result) {
    if (result.error) {
        alert(result.error.message);
        window.location.href = 'plans.php';
    }
});
</script>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/checkout.css"> 
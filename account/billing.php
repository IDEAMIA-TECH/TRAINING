<?php
require_once '../includes/header.php';

if (!is_logged_in()) {
    header("Location: ../login.php");
    exit();
}

// Obtener suscripción activa
$stmt = $conn->prepare("
    SELECT s.*, p.name as plan_name, p.price
    FROM subscriptions s
    JOIN subscription_plans p ON s.plan_id = p.id
    WHERE s.user_id = ? AND s.status = 'active'
    ORDER BY s.created_at DESC
    LIMIT 1
");
$stmt->execute([$_SESSION['user_id']]);
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener historial de transacciones
$stmt = $conn->prepare("
    SELECT t.*, i.invoice_number
    FROM transactions t
    LEFT JOIN invoices i ON t.id = i.transaction_id
    WHERE t.user_id = ?
    ORDER BY t.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="billing-container">
    <div class="billing-header">
        <h1>Facturación</h1>
    </div>
    
    <?php if ($subscription): ?>
        <div class="subscription-info">
            <h2>Suscripción Actual</h2>
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">Plan:</span>
                    <span class="value"><?php echo htmlspecialchars($subscription['plan_name']); ?></span>
                </div>
                
                <div class="info-item">
                    <span class="label">Precio:</span>
                    <span class="value">$<?php echo number_format($subscription['price'], 2); ?>/mes</span>
                </div>
                
                <div class="info-item">
                    <span class="label">Estado:</span>
                    <span class="value status-badge <?php echo $subscription['status']; ?>">
                        <?php echo ucfirst($subscription['status']); ?>
                    </span>
                </div>
                
                <div class="info-item">
                    <span class="label">Próximo cobro:</span>
                    <span class="value">
                        <?php echo date('d/m/Y', strtotime($subscription['end_date'])); ?>
                    </span>
                </div>
            </div>
            
            <div class="subscription-actions">
                <?php if ($subscription['auto_renew']): ?>
                    <button class="btn btn-danger" onclick="cancelSubscription()">
                        <i class="fas fa-ban"></i> Cancelar Suscripción
                    </button>
                <?php else: ?>
                    <a href="../payments/plans.php" class="btn btn-primary">
                        <i class="fas fa-sync"></i> Renovar Suscripción
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="transactions-history">
        <h2>Historial de Transacciones</h2>
        
        <?php if (empty($transactions)): ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <p>No hay transacciones para mostrar</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Descripción</th>
                            <th>Monto</th>
                            <th>Estado</th>
                            <th>Factura</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($transaction['created_at'])); ?>
                                </td>
                                <td>Pago de suscripción</td>
                                <td>$<?php echo number_format($transaction['amount'], 2); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $transaction['status']; ?>">
                                        <?php echo ucfirst($transaction['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($transaction['invoice_number']): ?>
                                        <a href="invoice.php?id=<?php echo $transaction['id']; ?>" 
                                           class="btn btn-sm btn-secondary">
                                            <i class="fas fa-download"></i> Descargar
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/billing.css">

<?php require_once '../includes/footer.php'; ?> 
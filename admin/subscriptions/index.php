<?php
require_once '../../includes/header.php';
require_once '../../includes/PaymentManager.php';

if (!has_permission('manage_subscriptions')) {
    header("Location: ../../login.php");
    exit();
}

$payment_manager = new PaymentManager($conn);

// Obtener planes
$stmt = $conn->prepare("
    SELECT * FROM subscription_plans 
    ORDER BY price ASC
");
$stmt->execute();
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener suscripciones activas
$stmt = $conn->prepare("
    SELECT s.*, u.name as user_name, u.email,
           sp.name as plan_name, sp.price
    FROM subscriptions s
    JOIN users u ON s.user_id = u.id
    JOIN subscription_plans sp ON s.plan_id = sp.id
    WHERE s.status = 'active'
    ORDER BY s.created_at DESC
");
$stmt->execute();
$subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_subscriptions,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_subscriptions,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_subscriptions
    FROM subscriptions
");
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener ingresos mensuales
$stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(amount) as total_amount,
        COUNT(*) as transaction_count
    FROM transactions
    WHERE status = 'completed'
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
");
$stmt->execute();
$monthly_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-container">
    <?php require_once '../sidebar.php'; ?>
    
    <div class="admin-content">
        <div class="subscriptions-dashboard">
            <div class="dashboard-header">
                <h2>Gestión de Suscripciones</h2>
                
                <button class="btn btn-primary" data-toggle="modal" data-target="#newPlanModal">
                    <i class="fas fa-plus"></i> Nuevo Plan
                </button>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Suscripciones</h3>
                        <p><?php echo $stats['total_subscriptions']; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Suscripciones Activas</h3>
                        <p><?php echo $stats['active_subscriptions']; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Suscripciones Canceladas</h3>
                        <p><?php echo $stats['cancelled_subscriptions']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="content-grid">
                <div class="plans-section">
                    <h3>Planes de Suscripción</h3>
                    <div class="plans-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Precio</th>
                                    <th>Duración</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($plans as $plan): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($plan['name']); ?></td>
                                        <td>$<?php echo number_format($plan['price'], 2); ?></td>
                                        <td><?php echo $plan['duration']; ?> días</td>
                                        <td>
                                            <span class="badge <?php echo $plan['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                                <?php echo $plan['is_active'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary edit-plan" 
                                                    data-plan="<?php echo htmlspecialchars(json_encode($plan)); ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-plan" 
                                                    data-id="<?php echo $plan['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="subscriptions-section">
                    <h3>Suscripciones Activas</h3>
                    <div class="subscriptions-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Plan</th>
                                    <th>Inicio</th>
                                    <th>Fin</th>
                                    <th>Auto-renovación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subscriptions as $sub): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($sub['user_name']); ?>
                                            <br>
                                            <small><?php echo $sub['email']; ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($sub['plan_name']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($sub['start_date'])); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($sub['end_date'])); ?></td>
                                        <td>
                                            <span class="badge <?php echo $sub['auto_renew'] ? 'badge-success' : 'badge-warning'; ?>">
                                                <?php echo $sub['auto_renew'] ? 'Sí' : 'No'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-danger cancel-subscription" 
                                                    data-id="<?php echo $sub['id']; ?>">
                                                <i class="fas fa-ban"></i> Cancelar
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para nuevo/editar plan -->
<div class="modal" id="planModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Plan de Suscripción</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="planForm">
                    <input type="hidden" name="id">
                    
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Precio</label>
                        <input type="number" name="price" class="form-control" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Duración (días)</label>
                        <input type="number" name="duration" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Características</label>
                        <div id="features-container">
                            <div class="feature-input">
                                <input type="text" name="features[]" class="form-control">
                                <button type="button" class="btn btn-sm btn-danger remove-feature">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary" id="add-feature">
                            <i class="fas fa-plus"></i> Agregar Característica
                        </button>
                    </div>
                    
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active">
                            <label class="custom-control-label" for="is_active">Plan Activo</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="savePlan">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/subscription-admin.js"></script>

<?php require_once '../../includes/footer.php'; ?> 
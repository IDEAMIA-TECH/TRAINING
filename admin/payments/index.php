<?php
require_once '../../includes/header.php';

if (!is_admin()) {
    header("Location: ../../login.php");
    exit();
}

$error = '';
$success = '';

// Procesar acciones de pago
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['payment_id'])) {
    $payment_id = (int)$_POST['payment_id'];
    $action = sanitize_input($_POST['action']);
    $enrollment_id = (int)$_POST['enrollment_id'];
    
    try {
        // Iniciar transacción
        $conn->beginTransaction();
        
        // Actualizar estado del pago
        $stmt = $conn->prepare("
            UPDATE payments 
            SET status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        
        $payment_status = ($action === 'approve') ? 'approved' : 'rejected';
        $stmt->execute([$payment_status, $payment_id]);
        
        // Actualizar estado de la inscripción
        $stmt = $conn->prepare("
            UPDATE enrollments 
            SET payment_status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        
        $enrollment_status = ($action === 'approve') ? 'completed' : 'pending';
        $stmt->execute([$enrollment_status, $enrollment_id]);
        
        $conn->commit();
        $success = "Pago " . ($action === 'approve' ? 'aprobado' : 'rechazado') . " exitosamente";
    } catch(PDOException $e) {
        $conn->rollBack();
        $error = "Error al procesar el pago: " . $e->getMessage();
    }
}

// Obtener pagos pendientes
$payments_query = "
    SELECT p.*, e.payment_amount, c.title as course_title, 
           u.name as user_name, u.email as user_email
    FROM payments p
    JOIN enrollments e ON p.enrollment_id = e.id
    JOIN courses c ON e.course_id = c.id
    JOIN users u ON e.user_id = u.id
    WHERE p.status = 'pending'
    ORDER BY p.created_at DESC
";

$stmt = $conn->query($payments_query);
$pending_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-container">
    <div class="admin-sidebar">
        <h3>Panel de Administración</h3>
        <nav>
            <a href="../dashboard.php">Dashboard</a>
            <a href="../courses/">Cursos</a>
            <a href="index.php" class="active">Pagos</a>
            <a href="../users/">Usuarios</a>
            <a href="../reports/">Reportes</a>
        </nav>
    </div>

    <div class="admin-content">
        <h2>Verificación de Pagos</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (empty($pending_payments)): ?>
            <div class="empty-state">
                <p>No hay pagos pendientes de verificación</p>
            </div>
        <?php else: ?>
            <div class="payments-table">
                <table>
                    <thead>
                        <tr>
                            <th>Curso</th>
                            <th>Alumno</th>
                            <th>Monto</th>
                            <th>Método</th>
                            <th>Referencia</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_payments as $payment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['course_title']); ?></td>
                                <td>
                                    <div><?php echo htmlspecialchars($payment['user_name']); ?></div>
                                    <div class="text-muted"><?php echo htmlspecialchars($payment['user_email']); ?></div>
                                </td>
                                <td>$<?php echo number_format($payment['payment_amount'], 2); ?> MXN</td>
                                <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                <td><?php echo htmlspecialchars($payment['reference']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($payment['created_at'])); ?></td>
                                <td class="actions">
                                    <form method="POST" action="" class="d-inline">
                                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                        <input type="hidden" name="enrollment_id" value="<?php echo $payment['enrollment_id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-success btn-sm" 
                                                onclick="return confirm('¿Confirmar aprobación del pago?')">
                                            Aprobar
                                        </button>
                                    </form>
                                    
                                    <form method="POST" action="" class="d-inline">
                                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                        <input type="hidden" name="enrollment_id" value="<?php echo $payment['enrollment_id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-danger btn-sm"
                                                onclick="return confirm('¿Confirmar rechazo del pago?')">
                                            Rechazar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?> 
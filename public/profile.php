<?php
require_once '../includes/init.php';

if (!$user_authenticated) {
    redirect('/login.php');
}

try {
    // Obtener información del usuario
    $stmt = $db->prepare("
        SELECT * FROM users WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Obtener cursos inscritos
    $stmt = $db->prepare("
        SELECT c.*, 
               cr.status as registration_status,
               cr.created_at as registration_date,
               p.status as payment_status,
               p.amount,
               p.payment_method,
               p.transaction_id,
               (SELECT image_url FROM course_images WHERE course_id = c.id AND is_main = 1 LIMIT 1) as main_image
        FROM course_registrations cr
        JOIN courses c ON cr.course_id = c.id
        LEFT JOIN payments p ON cr.payment_id = p.id
        WHERE cr.user_id = ?
        ORDER BY cr.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener historial de pagos
    $stmt = $db->prepare("
        SELECT p.*, c.title as course_title
        FROM payments p
        JOIN courses c ON p.course_id = c.id
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<?php require_once '../templates/header.php'; ?>

<div class="container py-5">
    <div class="row">
        <!-- Información del Usuario -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Mi Perfil</h5>
                    <div class="mb-3">
                        <strong>Nombre:</strong> <?php echo htmlspecialchars($user['name']); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Teléfono:</strong> <?php echo htmlspecialchars($user['phone']); ?>
                    </div>
                    <a href="edit_profile.php" class="btn btn-primary">
                        <i class="bi bi-pencil"></i> Editar Perfil
                    </a>
                </div>
            </div>
        </div>

        <!-- Contenido Principal -->
        <div class="col-md-8">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php else: ?>
                <!-- Mis Cursos -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Mis Cursos</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($registrations)): ?>
                            <p class="text-muted">No estás inscrito en ningún curso.</p>
                        <?php else: ?>
                            <div class="row row-cols-1 row-cols-md-2 g-4">
                                <?php foreach ($registrations as $reg): ?>
                                    <div class="col">
                                        <div class="card h-100">
                                            <img src="<?php echo BASE_URL; ?>/assets/uploads/courses/<?php echo $reg['main_image']; ?>" 
                                                 class="card-img-top" alt="<?php echo htmlspecialchars($reg['title']); ?>">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($reg['title']); ?></h5>
                                                <ul class="list-unstyled">
                                                    <li>
                                                        <strong>Estado:</strong>
                                                        <span class="badge bg-<?php 
                                                            echo $reg['registration_status'] === 'confirmed' ? 'success' : 
                                                                ($reg['registration_status'] === 'cancelled' ? 'danger' : 'warning'); 
                                                        ?>">
                                                            <?php echo $reg['registration_status']; ?>
                                                        </span>
                                                    </li>
                                                    <li>
                                                        <strong>Fecha:</strong>
                                                        <?php echo date('d/m/Y H:i', strtotime($reg['start_date'])); ?>
                                                    </li>
                                                    <li>
                                                        <strong>Pago:</strong>
                                                        <span class="badge bg-<?php 
                                                            echo $reg['payment_status'] === 'completed' ? 'success' : 
                                                                ($reg['payment_status'] === 'failed' ? 'danger' : 'warning'); 
                                                        ?>">
                                                            <?php echo $reg['payment_status']; ?>
                                                        </span>
                                                    </li>
                                                </ul>
                                            </div>
                                            <div class="card-footer">
                                                <a href="<?php echo BASE_URL; ?>/courses.php?id=<?php echo $reg['id']; ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    Ver Detalles
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Historial de Pagos -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Historial de Pagos</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($payments)): ?>
                            <p class="text-muted">No hay pagos registrados.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Curso</th>
                                            <th>Monto</th>
                                            <th>Método</th>
                                            <th>Estado</th>
                                            <th>Fecha</th>
                                            <th>Transacción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $payment): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($payment['course_title']); ?></td>
                                                <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                                <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $payment['status'] === 'completed' ? 'success' : 
                                                            ($payment['status'] === 'failed' ? 'danger' : 'warning'); 
                                                    ?>">
                                                        <?php echo $payment['status']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($payment['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($payment['transaction_id']): ?>
                                                        <small class="text-muted"><?php echo $payment['transaction_id']; ?></small>
                                                    <?php else: ?>
                                                        -
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
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?> 
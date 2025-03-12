<?php
require_once '../../includes/header.php';

if (!is_admin()) {
    header("Location: ../../login.php");
    exit();
}

$error = '';
$success = '';
$user = null;

// Obtener usuario
if (isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    $user_query = "
        SELECT u.*, 
               COUNT(e.id) as total_enrollments,
               SUM(CASE WHEN e.payment_status = 'completed' THEN 1 ELSE 0 END) as active_enrollments,
               SUM(CASE WHEN e.payment_status = 'completed' THEN e.payment_amount ELSE 0 END) as total_spent
        FROM users u
        LEFT JOIN enrollments e ON u.id = e.user_id
        WHERE u.id = ? AND u.role = 'client'
        GROUP BY u.id
    ";
    
    $stmt = $conn->prepare($user_query);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header("Location: index.php");
        exit();
    }
}

// Obtener inscripciones del usuario
$enrollments_query = "
    SELECT e.*, c.title as course_title, c.start_date, c.end_date
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE e.user_id = ?
    ORDER BY e.created_at DESC
";

$stmt = $conn->prepare($enrollments_query);
$stmt->execute([$user_id]);
$enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-container">
    <div class="admin-sidebar">
        <h3>Panel de Administración</h3>
        <nav>
            <a href="../dashboard.php">Dashboard</a>
            <a href="../courses/">Cursos</a>
            <a href="../payments/">Pagos</a>
            <a href="index.php" class="active">Usuarios</a>
            <a href="../reports/">Reportes</a>
        </nav>
    </div>

    <div class="admin-content">
        <div class="content-header">
            <h2>Detalles del Usuario</h2>
            <div class="header-actions">
                <a href="index.php" class="btn btn-secondary">Volver</a>
            </div>
        </div>

        <div class="user-details">
            <div class="user-info-card">
                <div class="user-header">
                    <h3><?php echo htmlspecialchars($user['name']); ?></h3>
                    <span class="status-badge <?php echo $user['status'] ? 'active' : 'inactive'; ?>">
                        <?php echo $user['status'] ? 'Activo' : 'Inactivo'; ?>
                    </span>
                </div>

                <div class="user-meta">
                    <div class="meta-item">
                        <span class="meta-label">Email:</span>
                        <span class="meta-value"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <?php if ($user['phone']): ?>
                        <div class="meta-item">
                            <span class="meta-label">Teléfono:</span>
                            <span class="meta-value"><?php echo htmlspecialchars($user['phone']); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="meta-item">
                        <span class="meta-label">Fecha de registro:</span>
                        <span class="meta-value">
                            <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?>
                        </span>
                    </div>
                </div>

                <div class="user-stats">
                    <div class="stat-item">
                        <span class="stat-label">Cursos Inscritos</span>
                        <span class="stat-value"><?php echo $user['total_enrollments']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Cursos Activos</span>
                        <span class="stat-value"><?php echo $user['active_enrollments']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Total Invertido</span>
                        <span class="stat-value">$<?php echo number_format($user['total_spent'], 2); ?> MXN</span>
                    </div>
                </div>
            </div>

            <div class="user-enrollments">
                <h3>Historial de Inscripciones</h3>
                
                <?php if (empty($enrollments)): ?>
                    <div class="empty-state">
                        <p>Este usuario aún no se ha inscrito a ningún curso</p>
                    </div>
                <?php else: ?>
                    <div class="enrollments-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Curso</th>
                                    <th>Fecha de Inscripción</th>
                                    <th>Estado del Pago</th>
                                    <th>Monto</th>
                                    <th>Estado del Curso</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrollments as $enrollment): ?>
                                    <tr>
                                        <td>
                                            <div><?php echo htmlspecialchars($enrollment['course_title']); ?></div>
                                            <div class="text-muted">
                                                <?php echo date('d/m/Y H:i', strtotime($enrollment['start_date'])); ?> - 
                                                <?php echo date('d/m/Y H:i', strtotime($enrollment['end_date'])); ?>
                                            </div>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($enrollment['created_at'])); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $enrollment['payment_status']; ?>">
                                                <?php echo ucfirst($enrollment['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td>$<?php echo number_format($enrollment['payment_amount'], 2); ?> MXN</td>
                                        <td>
                                            <?php
                                            $now = time();
                                            $start = strtotime($enrollment['start_date']);
                                            $end = strtotime($enrollment['end_date']);
                                            
                                            if ($enrollment['payment_status'] !== 'completed') {
                                                echo '<span class="status-badge pending">Pendiente de Pago</span>';
                                            } elseif ($now < $start) {
                                                echo '<span class="status-badge upcoming">Próximo</span>';
                                            } elseif ($now > $end) {
                                                echo '<span class="status-badge completed">Completado</span>';
                                            } else {
                                                echo '<span class="status-badge active">En Curso</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?> 
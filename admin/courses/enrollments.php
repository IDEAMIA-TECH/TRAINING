<?php
require_once '../../includes/header.php';

if (!is_admin()) {
    header("Location: ../../login.php");
    exit();
}

$error = '';
$success = '';
$course = null;

// Obtener el curso
if (isset($_GET['course_id'])) {
    $course_id = (int)$_GET['course_id'];
    $stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        header("Location: index.php");
        exit();
    }
}

// Procesar cambios de estado de inscripción
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enrollment_id'])) {
    $enrollment_id = (int)$_POST['enrollment_id'];
    $new_status = sanitize_input($_POST['status']);
    
    try {
        $stmt = $conn->prepare("
            UPDATE enrollments 
            SET payment_status = ? 
            WHERE id = ? AND course_id = ?
        ");
        
        if ($stmt->execute([$new_status, $enrollment_id, $course_id])) {
            $success = "Estado de inscripción actualizado";
        } else {
            $error = "Error al actualizar el estado";
        }
    } catch(PDOException $e) {
        $error = "Error en la base de datos: " . $e->getMessage();
    }
}

// Obtener inscripciones
$enrollments_query = "
    SELECT e.*, u.name, u.email, u.phone
    FROM enrollments e
    JOIN users u ON e.user_id = u.id
    WHERE e.course_id = ?
    ORDER BY e.created_at DESC
";

$stmt = $conn->prepare($enrollments_query);
$stmt->execute([$course_id]);
$enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular estadísticas
$stats = [
    'total' => count($enrollments),
    'completed' => 0,
    'pending' => 0,
    'cancelled' => 0,
    'revenue' => 0
];

foreach ($enrollments as $enrollment) {
    $stats[$enrollment['payment_status']]++;
    if ($enrollment['payment_status'] === 'completed') {
        $stats['revenue'] += $enrollment['payment_amount'];
    }
}
?>

<div class="admin-container">
    <div class="admin-sidebar">
        <h3>Panel de Administración</h3>
        <nav>
            <a href="../dashboard.php">Dashboard</a>
            <a href="index.php" class="active">Cursos</a>
            <a href="../users/">Usuarios</a>
            <a href="../banners/">Banners</a>
            <a href="../reports/">Reportes</a>
        </nav>
    </div>

    <div class="admin-content">
        <div class="content-header">
            <h2>Inscritos en: <?php echo htmlspecialchars($course['title']); ?></h2>
            <div class="header-actions">
                <a href="edit.php?id=<?php echo $course['id']; ?>" class="btn btn-secondary">
                    Editar Curso
                </a>
                <a href="index.php" class="btn btn-secondary">Volver</a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Inscritos</h3>
                <p class="stat-number"><?php echo $stats['total']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Pagos Completados</h3>
                <p class="stat-number"><?php echo $stats['completed']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Pagos Pendientes</h3>
                <p class="stat-number"><?php echo $stats['pending']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Ingresos Totales</h3>
                <p class="stat-number">$<?php echo number_format($stats['revenue'], 2); ?> MXN</p>
            </div>
        </div>

        <div class="enrollments-table">
            <table>
                <thead>
                    <tr>
                        <th>Alumno</th>
                        <th>Contacto</th>
                        <th>Fecha de Inscripción</th>
                        <th>Monto</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enrollments as $enrollment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($enrollment['name']); ?></td>
                            <td>
                                <div>Email: <?php echo htmlspecialchars($enrollment['email']); ?></div>
                                <div>Tel: <?php echo htmlspecialchars($enrollment['phone']); ?></div>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($enrollment['created_at'])); ?></td>
                            <td>$<?php echo number_format($enrollment['payment_amount'], 2); ?> MXN</td>
                            <td>
                                <span class="status-badge <?php echo $enrollment['payment_status']; ?>">
                                    <?php echo ucfirst($enrollment['payment_status']); ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" action="" class="status-form">
                                    <input type="hidden" name="enrollment_id" 
                                           value="<?php echo $enrollment['id']; ?>">
                                    <select name="status" class="status-select" 
                                            onchange="this.form.submit()">
                                        <option value="pending" <?php echo $enrollment['payment_status'] === 'pending' ? 'selected' : ''; ?>>
                                            Pendiente
                                        </option>
                                        <option value="completed" <?php echo $enrollment['payment_status'] === 'completed' ? 'selected' : ''; ?>>
                                            Completado
                                        </option>
                                        <option value="cancelled" <?php echo $enrollment['payment_status'] === 'cancelled' ? 'selected' : ''; ?>>
                                            Cancelado
                                        </option>
                                    </select>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?> 
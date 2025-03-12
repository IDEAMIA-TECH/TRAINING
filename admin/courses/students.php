<?php
require_once '../../includes/init.php';

if (!$is_admin) {
    redirect('/login.php');
}

$course_id = (int)($_GET['id'] ?? 0);
if (!$course_id) {
    redirect('/admin/courses');
}

try {
    // Obtener información del curso
    $stmt = $db->prepare("
        SELECT * FROM courses WHERE id = ?
    ");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        redirect('/admin/courses');
    }

    // Obtener lista de estudiantes inscritos
    $stmt = $db->prepare("
        SELECT u.id, u.name, u.email, u.phone,
               cr.status as registration_status,
               p.status as payment_status,
               p.amount, p.payment_method,
               cr.created_at as registration_date
        FROM course_registrations cr
        JOIN users u ON cr.user_id = u.id
        LEFT JOIN payments p ON cr.payment_id = p.id
        WHERE cr.course_id = ?
        ORDER BY cr.created_at DESC
    ");
    $stmt->execute([$course_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Procesar acciones
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $student_id = (int)($_POST['student_id'] ?? 0);

        if ($action && $student_id) {
            try {
                $db->beginTransaction();

                // Obtener información del estudiante y curso
                $stmt = $db->prepare("
                    SELECT u.*, c.*
                    FROM users u
                    JOIN course_registrations cr ON u.id = cr.user_id
                    JOIN courses c ON cr.course_id = c.id
                    WHERE u.id = ? AND c.id = ?
                ");
                $stmt->execute([$student_id, $course_id]);
                $registration = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$registration) {
                    throw new Exception("Registro no encontrado");
                }

                $emailSender = new EmailSender();

                switch ($action) {
                    case 'confirm':
                        $stmt = $db->prepare("
                            UPDATE course_registrations 
                            SET status = 'confirmed' 
                            WHERE course_id = ? AND user_id = ?
                        ");
                        $stmt->execute([$course_id, $student_id]);

                        // Enviar email de confirmación
                        $emailSender->sendRegistrationConfirmation($registration, $course);
                        break;

                    case 'cancel':
                        $stmt = $db->prepare("
                            UPDATE course_registrations 
                            SET status = 'cancelled' 
                            WHERE course_id = ? AND user_id = ?
                        ");
                        $stmt->execute([$course_id, $student_id]);

                        // Enviar email de cancelación
                        $emailSender->sendRegistrationCancellation($registration, $course);
                        break;
                }

                $db->commit();
                redirect("/admin/courses/students.php?id={$course_id}");
            } catch (Exception $e) {
                $db->rollBack();
                $error = $e->getMessage();
            }
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<?php require_once '../../templates/admin/header.php'; ?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Estudiantes Inscritos - <?php echo htmlspecialchars($course['title']); ?></h2>
        <a href="<?php echo BASE_URL; ?>/admin/courses" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Teléfono</th>
                                <th>Estado Inscripción</th>
                                <th>Estado Pago</th>
                                <th>Método Pago</th>
                                <th>Monto</th>
                                <th>Fecha Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td><?php echo htmlspecialchars($student['phone']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $student['registration_status'] === 'confirmed' ? 'success' : 
                                                ($student['registration_status'] === 'cancelled' ? 'danger' : 'warning'); 
                                        ?>">
                                            <?php echo $student['registration_status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $student['payment_status'] === 'completed' ? 'success' : 
                                                ($student['payment_status'] === 'failed' ? 'danger' : 'warning'); 
                                        ?>">
                                            <?php echo $student['payment_status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo ucfirst($student['payment_method']); ?></td>
                                    <td>$<?php echo number_format($student['amount'], 2); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($student['registration_date'])); ?></td>
                                    <td>
                                        <?php if ($student['registration_status'] === 'pending'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                <input type="hidden" name="action" value="confirm">
                                                <button type="submit" class="btn btn-sm btn-success">
                                                    <i class="bi bi-check"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                <input type="hidden" name="action" value="cancel">
                                                <button type="submit" class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('¿Estás seguro de cancelar esta inscripción?')">
                                                    <i class="bi bi-x"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="mailto:<?php echo $student['email']; ?>" class="btn btn-sm btn-info">
                                            <i class="bi bi-envelope"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../templates/admin/footer.php'; ?> 
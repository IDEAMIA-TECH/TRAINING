<?php
require_once '../includes/header.php';
require_once '../includes/ZoomManager.php';

if (!is_logged_in()) {
    header("Location: ../login.php");
    exit();
}

$class_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$zoom_manager = new ZoomManager($conn);

try {
    // Verificar acceso a la clase
    $stmt = $conn->prepare("
        SELECT vc.*, c.title as course_title
        FROM virtual_classes vc
        JOIN courses c ON vc.course_id = c.id
        LEFT JOIN enrollments e ON c.id = e.course_id AND e.user_id = ?
        WHERE vc.id = ? AND (e.user_id IS NOT NULL OR c.instructor_id = ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $class_id, $_SESSION['user_id']]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$class) {
        throw new Exception("No tienes acceso a esta clase virtual");
    }
    
    $is_instructor = $class['instructor_id'] === $_SESSION['user_id'];
    $start_time = strtotime($class['start_time']);
    $end_time = $start_time + ($class['duration'] * 60);
    $now = time();
    
    $status = 'upcoming';
    if ($now >= $start_time && $now <= $end_time) {
        $status = 'in_progress';
    } elseif ($now > $end_time) {
        $status = 'completed';
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<div class="virtual-class-container">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
    <?php else: ?>
        <div class="class-header">
            <div class="class-info">
                <h1><?php echo htmlspecialchars($class['title']); ?></h1>
                <p class="course-title">
                    Curso: <?php echo htmlspecialchars($class['course_title']); ?>
                </p>
                
                <div class="class-meta">
                    <div class="meta-item">
                        <i class="fas fa-calendar"></i>
                        <?php echo date('d/m/Y', $start_time); ?>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-clock"></i>
                        <?php echo date('H:i', $start_time); ?>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-hourglass-half"></i>
                        <?php echo $class['duration']; ?> minutos
                    </div>
                    <div class="meta-item status <?php echo $status; ?>">
                        <?php
                        switch ($status) {
                            case 'upcoming':
                                echo '<i class="fas fa-calendar-alt"></i> Próximamente';
                                break;
                            case 'in_progress':
                                echo '<i class="fas fa-play-circle"></i> En curso';
                                break;
                            case 'completed':
                                echo '<i class="fas fa-check-circle"></i> Finalizada';
                                break;
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <?php if ($status !== 'completed'): ?>
                <div class="class-actions">
                    <?php if ($is_instructor): ?>
                        <a href="<?php echo htmlspecialchars($class['zoom_start_url']); ?>" 
                           target="_blank" class="btn btn-primary">
                            <i class="fas fa-video"></i> Iniciar Clase
                        </a>
                    <?php else: ?>
                        <a href="<?php echo htmlspecialchars($class['zoom_join_url']); ?>" 
                           target="_blank" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Unirse a la Clase
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="class-content">
            <?php if ($class['description']): ?>
                <div class="class-description">
                    <h3>Descripción</h3>
                    <?php echo nl2br(htmlspecialchars($class['description'])); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($status === 'upcoming'): ?>
                <div class="countdown-container">
                    <h3>La clase comienza en:</h3>
                    <div id="countdown" data-start="<?php echo $start_time; ?>">
                        <div class="countdown-item">
                            <span id="days">00</span>
                            <label>Días</label>
                        </div>
                        <div class="countdown-item">
                            <span id="hours">00</span>
                            <label>Horas</label>
                        </div>
                        <div class="countdown-item">
                            <span id="minutes">00</span>
                            <label>Minutos</label>
                        </div>
                        <div class="countdown-item">
                            <span id="seconds">00</span>
                            <label>Segundos</label>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($is_instructor && $status === 'completed'): ?>
                <div class="attendance-section">
                    <h3>Registro de Asistencia</h3>
                    <?php
                    $stmt = $conn->prepare("
                        SELECT u.name, ca.join_time, ca.leave_time
                        FROM class_attendance ca
                        JOIN users u ON ca.user_id = u.id
                        WHERE ca.class_id = ?
                        ORDER BY ca.join_time
                    ");
                    $stmt->execute([$class_id]);
                    $attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <div class="attendance-list">
                        <?php if (empty($attendees)): ?>
                            <p>No hay registros de asistencia</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Estudiante</th>
                                        <th>Hora de Ingreso</th>
                                        <th>Hora de Salida</th>
                                        <th>Duración</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendees as $attendee): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($attendee['name']); ?></td>
                                            <td><?php echo date('H:i:s', strtotime($attendee['join_time'])); ?></td>
                                            <td>
                                                <?php
                                                echo $attendee['leave_time'] 
                                                    ? date('H:i:s', strtotime($attendee['leave_time']))
                                                    : '-';
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                if ($attendee['leave_time']) {
                                                    $duration = strtotime($attendee['leave_time']) - strtotime($attendee['join_time']);
                                                    echo floor($duration / 60) . ' minutos';
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/countdown.js"></script>

<?php require_once '../includes/footer.php'; ?> 
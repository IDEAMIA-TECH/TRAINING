<?php
require_once '../../includes/header.php';
require_once '../../includes/ZoomManager.php';

if (!is_instructor()) {
    header("Location: ../../login.php");
    exit();
}

$zoom_manager = new ZoomManager($conn);
$error = null;
$success = false;

// Obtener cursos del instructor
$stmt = $conn->prepare("
    SELECT id, title 
    FROM courses 
    WHERE instructor_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'course_id' => $_POST['course_id'],
            'title' => $_POST['title'],
            'description' => $_POST['description'],
            'start_time' => $_POST['date'] . ' ' . $_POST['time'],
            'duration' => $_POST['duration']
        ];
        
        $zoom_manager->createMeeting($data);
        $success = true;
        
        // Notificar a los estudiantes inscritos
        $stmt = $conn->prepare("
            SELECT u.id, u.email 
            FROM users u
            JOIN enrollments e ON u.id = e.user_id
            WHERE e.course_id = ?
        ");
        $stmt->execute([$data['course_id']]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($students as $student) {
            // Crear notificación
            $notification_manager->createNotification(
                $student['id'],
                'Nueva clase virtual programada',
                "Se ha programado una nueva clase: {$data['title']}",
                'info',
                "/virtual_classes/view.php?id=" . $class_id
            );
            
            // Enviar email
            $to = $student['email'];
            $subject = "Nueva clase virtual: {$data['title']}";
            $message = "
                <h2>Nueva clase virtual programada</h2>
                <p>Se ha programado una nueva clase para tu curso.</p>
                <p><strong>Título:</strong> {$data['title']}</p>
                <p><strong>Fecha:</strong> " . date('d/m/Y H:i', strtotime($data['start_time'])) . "</p>
                <p><strong>Duración:</strong> {$data['duration']} minutos</p>
            ";
            
            $headers = "From: " . ADMIN_EMAIL . "\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            
            mail($to, $subject, $message, $headers);
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="admin-container">
    <?php require_once '../sidebar.php'; ?>
    
    <div class="admin-content">
        <div class="virtual-class-form-container">
            <h2>Programar Nueva Clase Virtual</h2>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    Clase virtual programada exitosamente
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="virtual-class-form">
                <div class="form-group">
                    <label for="course_id">Curso *</label>
                    <select id="course_id" name="course_id" required>
                        <option value="">Seleccionar curso</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>">
                                <?php echo htmlspecialchars($course['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="title">Título de la Clase *</label>
                    <input type="text" id="title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Descripción</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="date">Fecha *</label>
                        <input type="date" id="date" name="date" 
                               min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="time">Hora *</label>
                        <input type="time" id="time" name="time" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="duration">Duración (minutos) *</label>
                        <input type="number" id="duration" name="duration" 
                               min="15" max="240" step="15" value="60" required>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        Programar Clase
                    </button>
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('date').addEventListener('change', function() {
    const selectedDate = new Date(this.value);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    if (selectedDate < today) {
        alert('La fecha debe ser igual o posterior a hoy');
        this.value = '';
    }
});

document.querySelector('.virtual-class-form').addEventListener('submit', function(e) {
    const date = document.getElementById('date').value;
    const time = document.getElementById('time').value;
    const selectedDateTime = new Date(date + 'T' + time);
    const now = new Date();
    
    if (selectedDateTime < now) {
        e.preventDefault();
        alert('La fecha y hora deben ser posteriores a la actual');
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?> 
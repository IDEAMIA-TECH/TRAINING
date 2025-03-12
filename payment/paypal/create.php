<?php
require_once '../../includes/init.php';
require_once '../../includes/payment/PayPalAPI.php';

if (!$user_authenticated) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

try {
    $course_id = (int)($_POST['course_id'] ?? 0);
    if (!$course_id) {
        throw new Exception('ID de curso inválido');
    }

    // Obtener información del curso
    $stmt = $db->prepare("SELECT * FROM courses WHERE id = ? AND status = 'active'");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        throw new Exception('Curso no encontrado o inactivo');
    }

    // Verificar disponibilidad
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM course_registrations 
        WHERE course_id = ? AND status != 'cancelled'
    ");
    $stmt->execute([$course_id]);
    if ($stmt->fetchColumn() >= $course['capacity']) {
        throw new Exception('Curso sin cupos disponibles');
    }

    // Crear orden en PayPal
    $paypal = new PayPalAPI();
    $order = $paypal->createOrder($course, $_SESSION);

    // Registrar intento de pago
    $stmt = $db->prepare("
        INSERT INTO payments (
            user_id, 
            course_id, 
            amount, 
            payment_method,
            transaction_id,
            status
        ) VALUES (?, ?, ?, 'paypal', ?, 'pending')
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $course_id,
        $course['price'],
        $order['id']
    ]);

    header('Content-Type: application/json');
    echo json_encode([
        'id' => $order['id'],
        'links' => $order['links']
    ]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
} 
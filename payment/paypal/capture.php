<?php
require_once '../../includes/init.php';
require_once '../../includes/payment/PayPalAPI.php';

if (!$user_authenticated) {
    redirect('/login.php');
}

try {
    $order_id = $_GET['token'] ?? '';
    if (!$order_id) {
        throw new Exception('ID de orden inválido');
    }

    // Capturar el pago
    $paypal = new PayPalAPI();
    $result = $paypal->captureOrder($order_id);

    if ($result['status'] === 'COMPLETED') {
        $db->beginTransaction();

        // Actualizar pago
        $stmt = $db->prepare("
            UPDATE payments 
            SET status = 'completed', 
                updated_at = CURRENT_TIMESTAMP 
            WHERE transaction_id = ?
        ");
        $stmt->execute([$order_id]);

        // Obtener información del pago
        $stmt = $db->prepare("
            SELECT * FROM payments 
            WHERE transaction_id = ?
        ");
        $stmt->execute([$order_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        // Crear registro del curso
        $stmt = $db->prepare("
            INSERT INTO course_registrations (
                course_id,
                user_id,
                payment_id,
                status
            ) VALUES (?, ?, ?, 'confirmed')
        ");
        $stmt->execute([
            $payment['course_id'],
            $payment['user_id'],
            $payment['id']
        ]);

        $db->commit();

        // Enviar email de confirmación
        $stmt = $db->prepare("
            SELECT u.*, c.* 
            FROM users u 
            JOIN payments p ON u.id = p.user_id
            JOIN courses c ON p.course_id = c.id
            WHERE p.transaction_id = ?
        ");
        $stmt->execute([$order_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $emailSender = new EmailSender();
        $emailSender->sendPaymentConfirmation(
            [
                'name' => $data['name'],
                'email' => $data['email']
            ],
            [
                'transaction_id' => $order_id,
                'amount' => $payment['amount'],
                'created_at' => $payment['created_at']
            ],
            [
                'title' => $data['title']
            ]
        );

        redirect('/courses/success.php');
    } else {
        throw new Exception('Error al procesar el pago');
    }

} catch (Exception $e) {
    error_log("Error en PayPal capture: " . $e->getMessage());
    redirect('/courses/error.php?message=' . urlencode($e->getMessage()));
} 
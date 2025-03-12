<?php
require_once '../../includes/init.php';
require_once '../../includes/payment/StripeAPI.php';

if (!$user_authenticated) {
    redirect('/login.php');
}

try {
    $session_id = $_GET['session_id'] ?? '';
    if (!$session_id) {
        throw new Exception('ID de sesión inválido');
    }

    $stripe = new StripeAPI();
    $session = $stripe->getSession($session_id);

    if ($session->payment_status === 'paid') {
        $db->beginTransaction();

        // Actualizar pago
        $stmt = $db->prepare("
            UPDATE payments 
            SET status = 'completed', 
                updated_at = CURRENT_TIMESTAMP 
            WHERE transaction_id = ?
        ");
        $stmt->execute([$session_id]);

        // Obtener información del pago
        $stmt = $db->prepare("
            SELECT * FROM payments 
            WHERE transaction_id = ?
        ");
        $stmt->execute([$session_id]);
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
        $stmt->execute([$session_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $emailSender = new EmailSender();
        $emailSender->sendPaymentConfirmation(
            [
                'name' => $data['name'],
                'email' => $data['email']
            ],
            [
                'transaction_id' => $session_id,
                'amount' => $payment['amount'],
                'created_at' => $payment['created_at']
            ],
            [
                'title' => $data['title']
            ]
        );

        redirect('/courses/success.php');
    } else {
        throw new Exception('El pago no ha sido completado');
    }

} catch (Exception $e) {
    error_log("Error en Stripe success: " . $e->getMessage());
    redirect('/courses/error.php?message=' . urlencode($e->getMessage()));
} 
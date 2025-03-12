<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/email/EmailSender.php';

try {
    // Obtener cursos que comienzan en las próximas 24 horas
    $stmt = $db->prepare("
        SELECT c.*, 
               u.id as user_id,
               u.name as user_name,
               u.email as user_email
        FROM courses c
        JOIN course_registrations cr ON c.id = cr.course_id
        JOIN users u ON cr.user_id = u.id
        WHERE c.status = 'active'
        AND c.start_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
        AND cr.status = 'confirmed'
        AND NOT EXISTS (
            -- Verificar si ya se envió un recordatorio
            SELECT 1 FROM email_logs 
            WHERE type = 'course_reminder'
            AND reference_id = c.id
            AND user_id = u.id
        )
    ");
    $stmt->execute();
    $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($reminders)) {
        echo "No hay recordatorios para enviar.\n";
        exit;
    }

    $emailSender = new EmailSender();
    $sent_count = 0;
    $error_count = 0;

    foreach ($reminders as $reminder) {
        try {
            // Enviar recordatorio
            $success = $emailSender->sendCourseReminder(
                [
                    'name' => $reminder['user_name'],
                    'email' => $reminder['user_email']
                ],
                [
                    'title' => $reminder['title'],
                    'start_date' => $reminder['start_date']
                ]
            );

            if ($success) {
                // Registrar el envío en la base de datos
                $stmt = $db->prepare("
                    INSERT INTO email_logs (
                        type, 
                        user_id, 
                        reference_id, 
                        status, 
                        sent_at
                    ) VALUES (
                        'course_reminder',
                        ?,
                        ?,
                        'sent',
                        NOW()
                    )
                ");
                $stmt->execute([
                    $reminder['user_id'],
                    $reminder['id']
                ]);

                $sent_count++;
            } else {
                throw new Exception("Error al enviar el email");
            }

            // Limpiar direcciones para el siguiente envío
            $emailSender->clearAddresses();

        } catch (Exception $e) {
            error_log("Error enviando recordatorio para curso {$reminder['id']}: " . $e->getMessage());
            $error_count++;
        }
    }

    echo "Proceso completado. Enviados: {$sent_count}, Errores: {$error_count}\n";

} catch (Exception $e) {
    error_log("Error en el proceso de recordatorios: " . $e->getMessage());
    echo "Error en el proceso de recordatorios: " . $e->getMessage() . "\n";
} 
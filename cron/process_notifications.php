<?php
require_once '../includes/header.php';
require_once '../includes/Notification.php';

try {
    // Obtener notificaciones pendientes
    $stmt = $conn->prepare("
        SELECT * FROM notifications 
        WHERE status = 'pending'
        ORDER BY created_at ASC
        LIMIT 50
    ");
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($notifications)) {
        echo "No hay notificaciones pendientes\n";
        exit();
    }
    
    $notification_service = new Notification($conn);
    
    foreach ($notifications as $notification) {
        // Procesar cada notificación
        $success = $notification_service->send(
            $notification['user_id'],
            $notification['course_id'],
            $notification['title'],
            $notification['message'],
            $notification['type']
        );
        
        echo "Notificación {$notification['id']}: " . ($success ? "Enviada" : "Fallida") . "\n";
        
        // Esperar un poco entre cada envío
        usleep(500000); // 0.5 segundos
    }
    
    echo "Proceso completado\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 
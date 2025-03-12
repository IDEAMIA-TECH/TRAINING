<?php
require_once '../../includes/header.php';
require_once '../../includes/Calendar.php';

if (!is_admin()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data['title'] || !$data['start'] || !$data['end']) {
        throw new Exception('Datos incompletos');
    }
    
    $calendar = new Calendar($conn);
    $success = $calendar->createEvent($data);
    
    if ($success && isset($data['sync_google']) && $data['sync_google']) {
        $calendar->syncWithGoogleCalendar($conn->lastInsertId());
    }
    
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
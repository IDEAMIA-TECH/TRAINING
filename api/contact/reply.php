<?php
require_once '../../includes/header.php';

if (!is_admin()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id']) || !isset($data['message'])) {
        throw new Exception("Datos incompletos");
    }
    
    // Obtener información del mensaje original
    $stmt = $conn->prepare("SELECT * FROM contact_messages WHERE id = ?");
    $stmt->execute([$data['id']]);
    $original = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$original) {
        throw new Exception("Mensaje no encontrado");
    }
    
    // Enviar email de respuesta
    $to = $original['email'];
    $subject = "RE: " . ($original['subject'] ?: 'Sin asunto');
    $message = $data['message'];
    $headers = "From: " . ADMIN_EMAIL . "\r\n";
    $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";
    
    if (!mail($to, $subject, $message, $headers)) {
        throw new Exception("Error al enviar el email");
    }
    
    // Actualizar estado del mensaje
    $stmt = $conn->prepare("
        UPDATE contact_messages 
        SET status = 'replied' 
        WHERE id = ?
    ");
    
    $success = $stmt->execute([$data['id']]);
    
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
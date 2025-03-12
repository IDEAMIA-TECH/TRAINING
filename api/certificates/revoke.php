<?php
require_once '../../includes/header.php';

if (!has_permission('manage_certificates')) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        throw new Exception("ID no especificado");
    }
    
    // Revocar certificado
    $stmt = $conn->prepare("
        UPDATE certificates 
        SET status = 'revoked' 
        WHERE id = ?
    ");
    $stmt->execute([$data['id']]);
    
    // Registrar acción
    $stmt = $conn->prepare("
        INSERT INTO certificate_verifications (
            certificate_id, verifier_ip, verifier_user_agent, verification_type
        ) VALUES (?, ?, ?, 'revocation')
    ");
    
    $stmt->execute([
        $data['id'],
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
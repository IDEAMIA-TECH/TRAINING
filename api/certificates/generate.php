<?php
require_once '../../includes/header.php';
require_once '../../includes/CertificateManager.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['course_id'])) {
        throw new Exception("Curso no especificado");
    }
    
    // Verificar si el usuario ha completado el curso
    $stmt = $conn->prepare("
        SELECT completed 
        FROM course_progress 
        WHERE user_id = ? AND course_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $data['course_id']]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$progress || !$progress['completed']) {
        throw new Exception("Debes completar el curso para obtener el certificado");
    }
    
    $certificate_manager = new CertificateManager($conn);
    $certificateId = $certificate_manager->generateCertificate(
        $_SESSION['user_id'],
        $data['course_id']
    );
    
    echo json_encode([
        'success' => true,
        'certificate_id' => $certificateId
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
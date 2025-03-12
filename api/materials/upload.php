<?php
require_once '../../includes/header.php';
require_once '../../includes/CourseMaterial.php';

if (!is_admin()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $course_id = (int)$_POST['course_id'];
    
    // Validar curso
    $stmt = $conn->prepare("SELECT id FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    if (!$stmt->fetch()) {
        throw new Exception("Curso no encontrado");
    }
    
    $material_manager = new CourseMaterial($conn);
    
    // Si es una actualización
    if (isset($_POST['id'])) {
        $data = [
            'title' => $_POST['title'],
            'description' => $_POST['description'],
            'is_public' => isset($_POST['is_public']),
            'order_index' => (int)$_POST['order_index']
        ];
        
        $success = $material_manager->updateMaterial($_POST['id'], $data);
    }
    // Si es una nueva subida
    else {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Por favor selecciona un archivo");
        }
        
        $data = [
            'title' => $_POST['title'],
            'description' => $_POST['description'],
            'is_public' => isset($_POST['is_public']),
            'order_index' => (int)$_POST['order_index']
        ];
        
        $material_id = $material_manager->uploadMaterial($course_id, $data, $_FILES['file']);
        $success = $material_id > 0;
    }
    
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
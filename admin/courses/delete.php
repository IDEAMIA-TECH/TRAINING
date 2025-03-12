<?php
require_once '../../includes/init.php';

if (!$is_admin) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Obtener y validar el ID del curso
$data = json_decode(file_get_contents('php://input'), true);
$id = (int)($data['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID de curso inválido']);
    exit;
}

try {
    $db->beginTransaction();

    // Verificar si hay estudiantes registrados
    $stmt = $db->prepare("SELECT COUNT(*) FROM course_registrations WHERE course_id = ?");
    $stmt->execute([$id]);
    $registrations = $stmt->fetchColumn();

    if ($registrations > 0) {
        // Si hay estudiantes, solo marcar como inactivo
        $stmt = $db->prepare("UPDATE courses SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        // Si no hay estudiantes, eliminar completamente
        delete_course_images($id);

        $stmt = $db->prepare("DELETE FROM course_images WHERE course_id = ?");
        $stmt->execute([$id]);

        $stmt = $db->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->execute([$id]);
    }

    $db->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 
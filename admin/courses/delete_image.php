<?php
require_once '../../includes/init.php';

if (!$is_admin) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
    exit;
}

try {
    $db->beginTransaction();

    // Obtener información de la imagen
    $stmt = $db->prepare("SELECT * FROM course_images WHERE id = ?");
    $stmt->execute([$id]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$image) {
        throw new Exception('Imagen no encontrada');
    }

    // Si es la imagen principal y hay otras imágenes, establecer otra como principal
    if ($image['is_main']) {
        $stmt = $db->prepare("
            SELECT id FROM course_images 
            WHERE course_id = ? AND id != ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$image['course_id'], $id]);
        $new_main = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($new_main) {
            $stmt = $db->prepare("UPDATE course_images SET is_main = 1 WHERE id = ?");
            $stmt->execute([$new_main['id']]);
        }
    }

    // Eliminar el archivo físico
    $file_path = __DIR__ . '/../../assets/uploads/courses/' . $image['image_url'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }

    // Eliminar el registro de la base de datos
    $stmt = $db->prepare("DELETE FROM course_images WHERE id = ?");
    $stmt->execute([$id]);

    $db->commit();

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $db->rollBack();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 
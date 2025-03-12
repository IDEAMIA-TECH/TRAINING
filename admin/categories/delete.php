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
    // Verificar que la categoría no tenga cursos asociados
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM courses 
        WHERE category_id = ?
    ");
    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        throw new Exception('No se puede eliminar una categoría con cursos asociados');
    }

    // Eliminar la categoría
    $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 
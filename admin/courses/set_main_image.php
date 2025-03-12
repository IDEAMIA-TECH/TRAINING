<?php
require_once '../../includes/init.php';

if (!$is_admin) {
    redirect('/login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/courses');
}

$image_id = (int)($_POST['image_id'] ?? 0);
$course_id = (int)($_POST['course_id'] ?? 0);

if (!$image_id || !$course_id) {
    redirect('/admin/courses');
}

try {
    $db->beginTransaction();

    // Primero quitamos la marca de principal de todas las imágenes del curso
    $stmt = $db->prepare("
        UPDATE course_images 
        SET is_main = 0 
        WHERE course_id = ?
    ");
    $stmt->execute([$course_id]);

    // Establecemos la nueva imagen principal
    $stmt = $db->prepare("
        UPDATE course_images 
        SET is_main = 1 
        WHERE id = ? AND course_id = ?
    ");
    $stmt->execute([$image_id, $course_id]);

    $db->commit();
    redirect("/admin/courses/images.php?id={$course_id}&success=1");
} catch (Exception $e) {
    $db->rollBack();
    redirect("/admin/courses/images.php?id={$course_id}&error=" . urlencode($e->getMessage()));
} 
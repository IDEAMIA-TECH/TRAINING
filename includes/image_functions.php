<?php
function process_course_image($file, $course_id, $is_main = false) {
    $upload_dir = UPLOAD_DIR . '/courses/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

    if (!in_array($file_extension, $allowed_types)) {
        return false;
    }

    $filename = $is_main ? 
        "course_{$course_id}_main.{$file_extension}" : 
        "course_{$course_id}_" . uniqid() . ".{$file_extension}";
    
    $upload_path = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        // Registrar en la base de datos
        global $db;
        $stmt = $db->prepare("
            INSERT INTO course_images (course_id, image_url, is_main) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$course_id, $filename, $is_main ? 1 : 0]);
        
        return $filename;
    }

    return false;
}

function delete_course_images($course_id) {
    global $db;
    
    // Obtener todas las imágenes del curso
    $stmt = $db->prepare("SELECT image_url FROM course_images WHERE course_id = ?");
    $stmt->execute([$course_id]);
    $images = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Eliminar archivos físicos
    foreach ($images as $image) {
        $file_path = UPLOAD_DIR . '/courses/' . $image;
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }

    // Eliminar registros de la base de datos
    $stmt = $db->prepare("DELETE FROM course_images WHERE course_id = ?");
    $stmt->execute([$course_id]);

    return true;
} 
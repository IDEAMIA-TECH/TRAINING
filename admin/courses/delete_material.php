<?php
require_once '../../includes/header.php';

if (!is_admin()) {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['material_id'])) {
    $material_id = (int)$_POST['material_id'];
    $course_id = (int)$_POST['course_id'];
    
    try {
        // Obtener información del material
        $stmt = $conn->prepare("SELECT file_url FROM course_materials WHERE id = ?");
        $stmt->execute([$material_id]);
        $material = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($material) {
            // Eliminar archivo físico
            $file_path = "../../" . $material['file_url'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Eliminar registro de la base de datos
            $stmt = $conn->prepare("DELETE FROM course_materials WHERE id = ?");
            $stmt->execute([$material_id]);
        }
        
        $_SESSION['success'] = "Material eliminado exitosamente";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al eliminar el material";
    }
    
    header("Location: materials.php?course_id=" . $course_id);
    exit();
}

header("Location: index.php");
exit(); 
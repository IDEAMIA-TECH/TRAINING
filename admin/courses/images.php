<?php
require_once '../../includes/init.php';

if (!$is_admin) {
    redirect('/login.php');
}

$course_id = (int)($_GET['id'] ?? 0);
if (!$course_id) {
    redirect('/admin/courses');
}

try {
    // Obtener información del curso
    $stmt = $db->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        redirect('/admin/courses');
    }

    // Obtener imágenes del curso
    $stmt = $db->prepare("
        SELECT * FROM course_images 
        WHERE course_id = ? 
        ORDER BY is_main DESC, created_at DESC
    ");
    $stmt->execute([$course_id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Procesar carga de imágenes
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_FILES['images'])) {
            $uploaded_files = $_FILES['images'];
            $upload_dir = __DIR__ . '/../../assets/uploads/courses/';
            
            // Crear directorio si no existe
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $db->beginTransaction();

            try {
                foreach ($uploaded_files['tmp_name'] as $key => $tmp_name) {
                    if ($uploaded_files['error'][$key] === UPLOAD_ERR_OK) {
                        $file_info = pathinfo($uploaded_files['name'][$key]);
                        $extension = strtolower($file_info['extension']);
                        
                        // Validar extensión
                        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                            throw new Exception('Formato de imagen no válido');
                        }

                        // Generar nombre único
                        $new_filename = uniqid('course_') . '.' . $extension;
                        $destination = $upload_dir . $new_filename;

                        // Mover archivo
                        if (move_uploaded_file($tmp_name, $destination)) {
                            // Guardar en base de datos
                            $stmt = $db->prepare("
                                INSERT INTO course_images (course_id, image_url, is_main)
                                VALUES (?, ?, ?)
                            ");
                            $stmt->execute([
                                $course_id, 
                                $new_filename,
                                empty($images) ? 1 : 0 // Primera imagen será la principal
                            ]);
                        }
                    }
                }

                $db->commit();
                redirect("/admin/courses/images.php?id={$course_id}&success=1");
            } catch (Exception $e) {
                $db->rollBack();
                $error = $e->getMessage();
            }
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<?php require_once '../../templates/admin/header.php'; ?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Gestión de Imágenes - <?php echo htmlspecialchars($course['title']); ?></h2>
        <a href="<?php echo BASE_URL; ?>/admin/courses" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">Imágenes subidas correctamente</div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Formulario de carga -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Subir Imágenes</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="images" class="form-label">Seleccionar Imágenes</label>
                            <input type="file" class="form-control" id="images" name="images[]" 
                                   accept="image/*" multiple required>
                            <small class="text-muted">
                                Formatos permitidos: JPG, JPEG, PNG, GIF
                            </small>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Subir Imágenes</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Lista de imágenes -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Imágenes del Curso</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($images)): ?>
                        <p class="text-muted">No hay imágenes cargadas</p>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($images as $image): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card">
                                        <img src="<?php echo BASE_URL; ?>/assets/uploads/courses/<?php echo $image['image_url']; ?>" 
                                             class="card-img-top" alt="Imagen del curso">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <?php if (!$image['is_main']): ?>
                                                    <form method="POST" action="set_main_image.php">
                                                        <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                                        <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                                                        <button type="submit" class="btn btn-sm btn-primary">
                                                            Establecer como Principal
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Imagen Principal</span>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        onclick="deleteImage(<?php echo $image['id']; ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function deleteImage(id) {
    if (confirm('¿Estás seguro de eliminar esta imagen?')) {
        fetch('<?php echo BASE_URL; ?>/admin/courses/delete_image.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + id
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al eliminar la imagen');
        });
    }
}
</script>

<?php require_once '../../templates/admin/footer.php'; ?> 
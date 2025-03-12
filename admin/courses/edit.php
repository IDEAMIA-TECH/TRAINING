<?php
require_once '../../includes/init.php';

if (!$is_admin) {
    redirect('/login.php');
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    redirect('/admin/courses');
}

$errors = [];
$success = false;

try {
    // Obtener información del curso
    $stmt = $db->prepare("
        SELECT * FROM courses 
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        redirect('/admin/courses');
    }

    // Obtener imágenes del curso
    $stmt = $db->prepare("
        SELECT * FROM course_images 
        WHERE course_id = ?
        ORDER BY is_main DESC
    ");
    $stmt->execute([$id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = sanitize_input($_POST['title'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $capacity = (int)($_POST['capacity'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        // Validaciones
        if (empty($title)) {
            $errors[] = "El título es requerido";
        }
        if (empty($description)) {
            $errors[] = "La descripción es requerida";
        }
        if (empty($start_date)) {
            $errors[] = "La fecha de inicio es requerida";
        }
        if (empty($end_date)) {
            $errors[] = "La fecha de fin es requerida";
        }
        if ($capacity <= 0) {
            $errors[] = "La capacidad debe ser mayor a 0";
        }
        if ($price <= 0) {
            $errors[] = "El precio debe ser mayor a 0";
        }

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                // Actualizar curso
                $stmt = $db->prepare("
                    UPDATE courses 
                    SET title = ?, description = ?, start_date = ?, 
                        end_date = ?, capacity = ?, price = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $title, $description, $start_date, $end_date, 
                    $capacity, $price, $status, $id
                ]);

                // Procesar nueva imagen principal si se subió
                if (!empty($_FILES['main_image']['name'])) {
                    // Eliminar imagen principal anterior
                    $stmt = $db->prepare("DELETE FROM course_images WHERE course_id = ? AND is_main = 1");
                    $stmt->execute([$id]);

                    $main_image_path = process_course_image($_FILES['main_image'], $id, true);
                    if (!$main_image_path) {
                        throw new Exception("Error al procesar la imagen principal");
                    }
                }

                // Procesar imágenes adicionales nuevas
                if (!empty($_FILES['images']['name'][0])) {
                    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                            $image_path = process_course_image([
                                'tmp_name' => $tmp_name,
                                'name' => $_FILES['images']['name'][$key],
                                'type' => $_FILES['images']['type'][$key]
                            ], $id, false);
                            
                            if (!$image_path) {
                                throw new Exception("Error al procesar una imagen adicional");
                            }
                        }
                    }
                }

                $db->commit();
                $success = true;
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = "Error al actualizar el curso: " . $e->getMessage();
            }
        }
    }
} catch (PDOException $e) {
    $errors[] = "Error al cargar el curso: " . $e->getMessage();
}
?>

<?php require_once '../../templates/admin/header.php'; ?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Editar Curso</h4>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            Curso actualizado exitosamente.
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="title" class="form-label">Título del Curso</label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   value="<?php echo htmlspecialchars($course['title']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Descripción</label>
                            <textarea class="form-control" id="description" name="description" 
                                    rows="4" required><?php echo htmlspecialchars($course['description']); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start_date" class="form-label">Fecha de Inicio</label>
                                <input type="datetime-local" class="form-control" id="start_date" name="start_date" 
                                       value="<?php echo date('Y-m-d\TH:i', strtotime($course['start_date'])); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="end_date" class="form-label">Fecha de Fin</label>
                                <input type="datetime-local" class="form-control" id="end_date" name="end_date" 
                                       value="<?php echo date('Y-m-d\TH:i', strtotime($course['end_date'])); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="capacity" class="form-label">Capacidad</label>
                                <input type="number" class="form-control" id="capacity" name="capacity" 
                                       value="<?php echo $course['capacity']; ?>" min="1" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="price" class="form-label">Precio</label>
                                <input type="number" class="form-control" id="price" name="price" 
                                       value="<?php echo $course['price']; ?>" min="0" step="0.01" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="status" class="form-label">Estado</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="active" <?php echo $course['status'] === 'active' ? 'selected' : ''; ?>>Activo</option>
                                    <option value="inactive" <?php echo $course['status'] === 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="main_image" class="form-label">Cambiar Imagen Principal</label>
                            <input type="file" class="form-control" id="main_image" name="main_image" accept="image/*">
                        </div>

                        <div class="mb-3">
                            <label for="images" class="form-label">Agregar Imágenes Adicionales</label>
                            <input type="file" class="form-control" id="images" name="images[]" accept="image/*" multiple>
                        </div>

                        <!-- Mostrar imágenes actuales -->
                        <div class="mb-3">
                            <label class="form-label">Imágenes Actuales</label>
                            <div class="row">
                                <?php foreach ($images as $image): ?>
                                    <div class="col-md-3 mb-2">
                                        <img src="<?php echo BASE_URL; ?>/assets/uploads/courses/<?php echo $image['image_url']; ?>" 
                                             class="img-thumbnail" alt="Imagen del curso">
                                        <?php if (!$image['is_main']): ?>
                                            <button type="button" class="btn btn-danger btn-sm mt-1" 
                                                    onclick="deleteImage(<?php echo $image['id']; ?>)">
                                                Eliminar
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="<?php echo BASE_URL; ?>/admin/courses" class="btn btn-secondary">Volver</a>
                            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function deleteImage(imageId) {
    if (confirm('¿Estás seguro de que deseas eliminar esta imagen?')) {
        fetch('<?php echo BASE_URL; ?>/admin/courses/delete_image.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: imageId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error al eliminar la imagen: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al procesar la solicitud');
        });
    }
}
</script>

<?php require_once '../../templates/admin/footer.php'; ?> 
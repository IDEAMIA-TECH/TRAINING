<?php
require_once '../../includes/init.php';

if (!$is_admin) {
    redirect('/login.php');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize_input($_POST['title'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $capacity = (int)($_POST['capacity'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);

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

    // Procesar imágenes
    $images = $_FILES['images'] ?? [];
    $main_image = $_FILES['main_image'] ?? null;

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Insertar curso
            $stmt = $db->prepare("
                INSERT INTO courses (title, description, start_date, end_date, capacity, price, status)
                VALUES (?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([$title, $description, $start_date, $end_date, $capacity, $price]);
            $course_id = $db->lastInsertId();

            // Procesar imagen principal
            if ($main_image && $main_image['error'] === UPLOAD_ERR_OK) {
                $main_image_path = process_course_image($main_image, $course_id, true);
                if (!$main_image_path) {
                    throw new Exception("Error al procesar la imagen principal");
                }
            }

            // Procesar imágenes adicionales
            if (!empty($images['name'][0])) {
                foreach ($images['tmp_name'] as $key => $tmp_name) {
                    if ($images['error'][$key] === UPLOAD_ERR_OK) {
                        $image_path = process_course_image([
                            'tmp_name' => $tmp_name,
                            'name' => $images['name'][$key],
                            'type' => $images['type'][$key]
                        ], $course_id, false);
                        
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
            $errors[] = "Error al crear el curso: " . $e->getMessage();
        }
    }
}
?>

<?php require_once '../../templates/admin/header.php'; ?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Crear Nuevo Curso</h4>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            Curso creado exitosamente. 
                            <a href="<?php echo BASE_URL; ?>/admin/courses" class="alert-link">Volver a la lista</a>
                        </div>
                    <?php else: ?>
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
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Descripción</label>
                                <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="start_date" class="form-label">Fecha de Inicio</label>
                                    <input type="datetime-local" class="form-control" id="start_date" name="start_date" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="end_date" class="form-label">Fecha de Fin</label>
                                    <input type="datetime-local" class="form-control" id="end_date" name="end_date" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="capacity" class="form-label">Capacidad</label>
                                    <input type="number" class="form-control" id="capacity" name="capacity" min="1" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="price" class="form-label">Precio</label>
                                    <input type="number" class="form-control" id="price" name="price" min="0" step="0.01" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="main_image" class="form-label">Imagen Principal</label>
                                <input type="file" class="form-control" id="main_image" name="main_image" accept="image/*" required>
                            </div>

                            <div class="mb-3">
                                <label for="images" class="form-label">Imágenes Adicionales</label>
                                <input type="file" class="form-control" id="images" name="images[]" accept="image/*" multiple>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="<?php echo BASE_URL; ?>/admin/courses" class="btn btn-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-primary">Crear Curso</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../templates/admin/footer.php'; ?> 
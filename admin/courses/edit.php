<?php
require_once '../../includes/header.php';

if (!is_admin()) {
    header("Location: ../../login.php");
    exit();
}

$error = '';
$success = '';
$course = null;

// Obtener el curso
if (isset($_GET['id'])) {
    $course_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        header("Location: index.php");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = sanitize_input($_POST['title']);
    $description = sanitize_input($_POST['description']);
    $max_capacity = (int)$_POST['max_capacity'];
    $price = (float)$_POST['price'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $syllabus = sanitize_input($_POST['syllabus']);
    $status = sanitize_input($_POST['status']);

    // Validaciones
    if (empty($title) || empty($description) || empty($start_date) || empty($end_date)) {
        $error = "Por favor completa todos los campos requeridos";
    } elseif ($max_capacity <= 0) {
        $error = "La capacidad debe ser mayor a 0";
    } elseif ($price < 0) {
        $error = "El precio no puede ser negativo";
    } elseif (strtotime($end_date) <= strtotime($start_date)) {
        $error = "La fecha de fin debe ser posterior a la fecha de inicio";
    } else {
        // Procesar nueva imagen si se subió una
        $image_url = $course['image_url'];
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/courses/';
            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png'];

            if (!in_array($file_extension, $allowed_extensions)) {
                $error = "Solo se permiten imágenes JPG, JPEG y PNG";
            } else {
                // Eliminar imagen anterior si existe
                if ($image_url && file_exists($upload_dir . $image_url)) {
                    unlink($upload_dir . $image_url);
                }

                $image_url = uniqid() . '.' . $file_extension;
                $destination = $upload_dir . $image_url;

                if (!move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                    $error = "Error al subir la imagen";
                }
            }
        }

        if (empty($error)) {
            try {
                $stmt = $conn->prepare("
                    UPDATE courses SET 
                        title = ?, description = ?, max_capacity = ?, 
                        price = ?, start_date = ?, end_date = ?, 
                        syllabus = ?, image_url = ?, status = ?
                    WHERE id = ?
                ");

                if ($stmt->execute([
                    $title, $description, $max_capacity, $price,
                    $start_date, $end_date, $syllabus, $image_url,
                    $status, $course_id
                ])) {
                    $success = "Curso actualizado exitosamente";
                    // Actualizar datos del curso
                    $course = array_merge($course, [
                        'title' => $title,
                        'description' => $description,
                        'max_capacity' => $max_capacity,
                        'price' => $price,
                        'start_date' => $start_date,
                        'end_date' => $end_date,
                        'syllabus' => $syllabus,
                        'image_url' => $image_url,
                        'status' => $status
                    ]);
                } else {
                    $error = "Error al actualizar el curso";
                }
            } catch(PDOException $e) {
                $error = "Error en la base de datos: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="admin-container">
    <div class="admin-sidebar">
        <h3>Panel de Administración</h3>
        <nav>
            <a href="../dashboard.php">Dashboard</a>
            <a href="index.php" class="active">Cursos</a>
            <a href="../users/">Usuarios</a>
            <a href="../banners/">Banners</a>
            <a href="../reports/">Reportes</a>
        </nav>
    </div>

    <div class="admin-content">
        <div class="content-header">
            <h2>Editar Curso</h2>
            <div class="header-actions">
                <a href="enrollments.php?course_id=<?php echo $course['id']; ?>" 
                   class="btn btn-info">Ver Inscritos</a>
                <a href="index.php" class="btn btn-secondary">Volver</a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label for="title">Título del Curso *</label>
                        <input type="text" id="title" name="title" required 
                               value="<?php echo htmlspecialchars($course['title']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="max_capacity">Capacidad Máxima *</label>
                        <input type="number" id="max_capacity" name="max_capacity" required min="1"
                               value="<?php echo $course['max_capacity']; ?>">
                    </div>

                    <div class="form-group">
                        <label for="price">Precio (MXN) *</label>
                        <input type="number" id="price" name="price" required step="0.01" min="0"
                               value="<?php echo $course['price']; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date">Fecha de Inicio *</label>
                        <input type="datetime-local" id="start_date" name="start_date" required
                               value="<?php echo date('Y-m-d\TH:i', strtotime($course['start_date'])); ?>">
                    </div>

                    <div class="form-group">
                        <label for="end_date">Fecha de Fin *</label>
                        <input type="datetime-local" id="end_date" name="end_date" required
                               value="<?php echo date('Y-m-d\TH:i', strtotime($course['end_date'])); ?>">
                    </div>

                    <div class="form-group">
                        <label for="status">Estado</label>
                        <select id="status" name="status" required>
                            <option value="active" <?php echo $course['status'] === 'active' ? 'selected' : ''; ?>>
                                Activo
                            </option>
                            <option value="cancelled" <?php echo $course['status'] === 'cancelled' ? 'selected' : ''; ?>>
                                Cancelado
                            </option>
                            <option value="completed" <?php echo $course['status'] === 'completed' ? 'selected' : ''; ?>>
                                Completado
                            </option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Descripción *</label>
                    <textarea id="description" name="description" required>
                        <?php echo htmlspecialchars($course['description']); ?>
                    </textarea>
                </div>

                <div class="form-group">
                    <label for="syllabus">Temario</label>
                    <textarea id="syllabus" name="syllabus">
                        <?php echo htmlspecialchars($course['syllabus']); ?>
                    </textarea>
                </div>

                <div class="form-group">
                    <label for="image">Imagen del Curso</label>
                    <?php if ($course['image_url']): ?>
                        <div class="current-image">
                            <img src="<?php echo UPLOADS_URL . '/courses/' . $course['image_url']; ?>" 
                                 alt="Imagen actual del curso" style="max-width: 200px;">
                            <p>Imagen actual</p>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="image" name="image" accept="image/jpeg,image/png">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?> 
<?php
require_once '../../includes/header.php';

if (!is_admin()) {
    header("Location: ../../login.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = sanitize_input($_POST['title']);
    $description = sanitize_input($_POST['description']);
    $max_capacity = (int)$_POST['max_capacity'];
    $price = (float)$_POST['price'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $syllabus = sanitize_input($_POST['syllabus']);

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
        // Procesar imagen si se subió una
        $image_url = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/courses/';
            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png'];

            if (!in_array($file_extension, $allowed_extensions)) {
                $error = "Solo se permiten imágenes JPG, JPEG y PNG";
            } else {
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
                    INSERT INTO courses (
                        title, description, max_capacity, price, 
                        start_date, end_date, syllabus, image_url, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
                ");

                if ($stmt->execute([
                    $title, $description, $max_capacity, $price,
                    $start_date, $end_date, $syllabus, $image_url
                ])) {
                    $success = "Curso creado exitosamente";
                } else {
                    $error = "Error al crear el curso";
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
            <h2>Crear Nuevo Curso</h2>
            <a href="index.php" class="btn btn-secondary">Volver</a>
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
                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="max_capacity">Capacidad Máxima *</label>
                        <input type="number" id="max_capacity" name="max_capacity" required min="1"
                               value="<?php echo isset($_POST['max_capacity']) ? htmlspecialchars($_POST['max_capacity']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="price">Precio (MXN) *</label>
                        <input type="number" id="price" name="price" required step="0.01" min="0"
                               value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date">Fecha de Inicio *</label>
                        <input type="datetime-local" id="start_date" name="start_date" required
                               value="<?php echo isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="end_date">Fecha de Fin *</label>
                        <input type="datetime-local" id="end_date" name="end_date" required
                               value="<?php echo isset($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Descripción *</label>
                    <textarea id="description" name="description" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label for="syllabus">Temario</label>
                    <textarea id="syllabus" name="syllabus"><?php echo isset($_POST['syllabus']) ? htmlspecialchars($_POST['syllabus']) : ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label for="image">Imagen del Curso</label>
                    <input type="file" id="image" name="image" accept="image/jpeg,image/png">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Crear Curso</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?> 
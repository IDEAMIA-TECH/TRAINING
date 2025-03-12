<?php
require_once '../../includes/header.php';

if (!is_admin()) {
    header("Location: ../../login.php");
    exit();
}

$error = '';
$success = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'create':
                case 'update':
                    $title = sanitize_input($_POST['title']);
                    $action_url = sanitize_input($_POST['action_url']);
                    $order_index = (int)$_POST['order_index'];
                    $banner_id = isset($_POST['banner_id']) ? (int)$_POST['banner_id'] : null;
                    
                    // Procesar imagen
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        $image = $_FILES['image'];
                        $allowed = ['jpg', 'jpeg', 'png'];
                        $ext = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
                        
                        if (!in_array($ext, $allowed)) {
                            throw new Exception("Formato de imagen no válido");
                        }
                        
                        $filename = 'banner_' . time() . '.' . $ext;
                        $destination = '../../uploads/banners/' . $filename;
                        
                        if (!file_exists('../../uploads/banners/')) {
                            mkdir('../../uploads/banners/', 0777, true);
                        }
                        
                        if (!move_uploaded_file($image['tmp_name'], $destination)) {
                            throw new Exception("Error al subir la imagen");
                        }
                        
                        $image_url = 'uploads/banners/' . $filename;
                    }
                    
                    if ($action === 'create') {
                        $stmt = $conn->prepare("
                            INSERT INTO banner_images (title, image_url, action_url, order_index)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$title, $image_url, $action_url, $order_index]);
                        $success = "Banner creado exitosamente";
                    } else {
                        $update_fields = ["title = ?", "action_url = ?", "order_index = ?"];
                        $params = [$title, $action_url, $order_index];
                        
                        if (isset($image_url)) {
                            $update_fields[] = "image_url = ?";
                            $params[] = $image_url;
                        }
                        
                        $params[] = $banner_id;
                        
                        $stmt = $conn->prepare("
                            UPDATE banner_images 
                            SET " . implode(", ", $update_fields) . "
                            WHERE id = ?
                        ");
                        $stmt->execute($params);
                        $success = "Banner actualizado exitosamente";
                    }
                    break;
                    
                case 'delete':
                    $banner_id = (int)$_POST['banner_id'];
                    
                    // Obtener URL de la imagen
                    $stmt = $conn->prepare("SELECT image_url FROM banner_images WHERE id = ?");
                    $stmt->execute([$banner_id]);
                    $banner = $stmt->fetch();
                    
                    if ($banner) {
                        // Eliminar archivo físico
                        $file_path = "../../" . $banner['image_url'];
                        if (file_exists($file_path)) {
                            unlink($file_path);
                        }
                        
                        // Eliminar registro
                        $stmt = $conn->prepare("DELETE FROM banner_images WHERE id = ?");
                        $stmt->execute([$banner_id]);
                        $success = "Banner eliminado exitosamente";
                    }
                    break;
                    
                case 'toggle':
                    $banner_id = (int)$_POST['banner_id'];
                    $stmt = $conn->prepare("
                        UPDATE banner_images 
                        SET active = NOT active 
                        WHERE id = ?
                    ");
                    $stmt->execute([$banner_id]);
                    $success = "Estado del banner actualizado";
                    break;
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Obtener banners
$banners_query = "SELECT * FROM banner_images ORDER BY order_index ASC";
$banners = $conn->query($banners_query)->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-container">
    <?php require_once '../../includes/admin_sidebar.php'; ?>

    <div class="admin-content">
        <div class="content-header">
            <h2>Gestión de Banners</h2>
            <div class="header-actions">
                <button type="button" class="btn btn-primary" 
                        onclick="document.getElementById('newBannerForm').style.display='block'">
                    Nuevo Banner
                </button>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="banners-container">
            <!-- Formulario para nuevo banner -->
            <div id="newBannerForm" class="banner-form" style="display: none;">
                <h3>Nuevo Banner</h3>
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group">
                        <label for="title">Título</label>
                        <input type="text" id="title" name="title" required>
                    </div>

                    <div class="form-group">
                        <label for="image">Imagen *</label>
                        <input type="file" id="image" name="image" required accept="image/*">
                        <p class="form-text">Formatos permitidos: JPG, PNG</p>
                    </div>

                    <div class="form-group">
                        <label for="action_url">URL de Acción</label>
                        <input type="url" id="action_url" name="action_url">
                    </div>

                    <div class="form-group">
                        <label for="order_index">Orden</label>
                        <input type="number" id="order_index" name="order_index" 
                               value="<?php echo count($banners) + 1; ?>" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Guardar</button>
                        <button type="button" class="btn btn-secondary" 
                                onclick="document.getElementById('newBannerForm').style.display='none'">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>

            <!-- Lista de banners -->
            <div class="banners-list">
                <?php if (empty($banners)): ?>
                    <div class="empty-state">
                        <p>No hay banners configurados</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($banners as $banner): ?>
                        <div class="banner-card <?php echo $banner['active'] ? 'active' : 'inactive'; ?>">
                            <div class="banner-preview">
                                <img src="<?php echo BASE_URL . '/' . $banner['image_url']; ?>" 
                                     alt="<?php echo htmlspecialchars($banner['title']); ?>">
                            </div>
                            <div class="banner-info">
                                <h4><?php echo htmlspecialchars($banner['title']); ?></h4>
                                <p class="banner-meta">
                                    Orden: <?php echo $banner['order_index']; ?> |
                                    Estado: <?php echo $banner['active'] ? 'Activo' : 'Inactivo'; ?>
                                </p>
                                <?php if ($banner['action_url']): ?>
                                    <p class="banner-url">
                                        URL: <?php echo htmlspecialchars($banner['action_url']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="banner-actions">
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="banner_id" value="<?php echo $banner['id']; ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm">
                                        <?php echo $banner['active'] ? 'Desactivar' : 'Activar'; ?>
                                    </button>
                                </form>
                                
                                <button type="button" class="btn btn-info btn-sm"
                                        onclick="editBanner(<?php echo htmlspecialchars(json_encode($banner)); ?>)">
                                    Editar
                                </button>
                                
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="banner_id" value="<?php echo $banner['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"
                                            onclick="return confirm('¿Estás seguro de eliminar este banner?')">
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function editBanner(banner) {
    // Implementar lógica para editar banner
    console.log('Editar banner:', banner);
}
</script>

<?php require_once '../../includes/footer.php'; ?> 
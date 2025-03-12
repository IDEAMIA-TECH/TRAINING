<?php
require_once '../../includes/header.php';
require_once '../../includes/CourseMaterial.php';

if (!is_admin()) {
    header("Location: ../../login.php");
    exit();
}

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$material_manager = new CourseMaterial($conn);

// Obtener información del curso
$stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    header("Location: index.php");
    exit();
}

// Procesar formulario de subida
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Por favor selecciona un archivo");
        }
        
        $data = [
            'title' => $_POST['title'],
            'description' => $_POST['description'],
            'is_public' => isset($_POST['is_public']),
            'order_index' => (int)$_POST['order_index']
        ];
        
        $material_manager->uploadMaterial($course_id, $data, $_FILES['file']);
        $success = "Material subido exitosamente";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener materiales del curso
$materials = $material_manager->getMaterials($course_id);
?>

<div class="admin-container">
    <?php require_once '../sidebar.php'; ?>
    
    <div class="admin-content">
        <div class="materials-container">
            <div class="materials-header">
                <h2>Materiales del Curso: <?php echo htmlspecialchars($course['title']); ?></h2>
                <button class="btn btn-primary" onclick="showUploadModal()">
                    <i class="fas fa-plus"></i> Nuevo Material
                </button>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="materials-grid">
                <?php foreach ($materials as $material): ?>
                    <div class="material-card">
                        <div class="material-icon">
                            <?php echo $this->getMaterialIcon($material['file_type']); ?>
                        </div>
                        
                        <div class="material-info">
                            <h3><?php echo htmlspecialchars($material['title']); ?></h3>
                            <p class="material-description">
                                <?php echo htmlspecialchars($material['description']); ?>
                            </p>
                            <div class="material-meta">
                                <span class="material-type">
                                    <?php echo ucfirst($material['file_type']); ?>
                                </span>
                                <span class="material-size">
                                    <?php echo $this->formatFileSize($material['file_size']); ?>
                                </span>
                                <span class="material-date">
                                    <?php echo date('d/m/Y H:i', strtotime($material['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="material-actions">
                            <a href="<?php echo BASE_URL . '/' . $material['file_url']; ?>" 
                               class="btn btn-sm btn-secondary" target="_blank">
                                <i class="fas fa-eye"></i> Ver
                            </a>
                            <button class="btn btn-sm btn-info" 
                                    onclick="editMaterial(<?php echo htmlspecialchars(json_encode($material)); ?>)">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            <button class="btn btn-sm btn-danger" 
                                    onclick="deleteMaterial(<?php echo $material['id']; ?>)">
                                <i class="fas fa-trash"></i> Eliminar
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Subida -->
<div class="modal" id="uploadModal">
    <div class="modal-content">
        <h3>Subir Material</h3>
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <div class="form-group">
                <label for="title">Título *</label>
                <input type="text" id="title" name="title" required>
            </div>
            
            <div class="form-group">
                <label for="description">Descripción</label>
                <textarea id="description" name="description"></textarea>
            </div>
            
            <div class="form-group">
                <label for="file">Archivo *</label>
                <input type="file" id="file" name="file" required>
                <small class="form-text">
                    Máximo 50MB. Formatos permitidos: PDF, DOC, DOCX, JPG, PNG, MP4, MP3
                </small>
            </div>
            
            <div class="form-group">
                <label for="order_index">Orden</label>
                <input type="number" id="order_index" name="order_index" value="0" min="0">
            </div>
            
            <div class="form-check">
                <input type="checkbox" id="is_public" name="is_public">
                <label for="is_public">Material público</label>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Subir</button>
                <button type="button" class="btn btn-secondary" onclick="closeUploadModal()">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/materials.js"></script>

<?php require_once '../../includes/footer.php'; ?> 
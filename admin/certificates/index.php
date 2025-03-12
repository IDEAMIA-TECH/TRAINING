<?php
require_once '../../includes/header.php';
require_once '../../includes/CertificateManager.php';

if (!has_permission('manage_certificates')) {
    header("Location: ../../login.php");
    exit();
}

$certificate_manager = new CertificateManager($conn);

// Obtener estadísticas
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_certificates,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_certificates,
        SUM(CASE WHEN status = 'revoked' THEN 1 ELSE 0 END) as revoked_certificates,
        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_certificates
    FROM certificates
");
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener certificados recientes
$stmt = $conn->prepare("
    SELECT c.*, u.name as student_name, co.title as course_name
    FROM certificates c
    JOIN users u ON c.user_id = u.id
    JOIN courses co ON c.course_id = co.id
    ORDER BY c.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recent_certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener plantillas
$stmt = $conn->prepare("SELECT * FROM certificate_templates ORDER BY name");
$stmt->execute();
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-container">
    <?php require_once '../sidebar.php'; ?>
    
    <div class="admin-content">
        <div class="dashboard-header">
            <h2>Gestión de Certificados</h2>
            
            <div class="header-actions">
                <button class="btn btn-primary" data-toggle="modal" data-target="#templateModal">
                    <i class="fas fa-plus"></i> Nueva Plantilla
                </button>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-certificate"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Certificados</h3>
                    <p><?php echo $stats['total_certificates']; ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>Certificados Activos</h3>
                    <p><?php echo $stats['active_certificates']; ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-ban"></i>
                </div>
                <div class="stat-content">
                    <h3>Certificados Revocados</h3>
                    <p><?php echo $stats['revoked_certificates']; ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3>Certificados Expirados</h3>
                    <p><?php echo $stats['expired_certificates']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="content-grid">
            <!-- Certificados Recientes -->
            <div class="content-card">
                <div class="card-header">
                    <h3>Certificados Recientes</h3>
                    <a href="certificates.php" class="btn btn-sm btn-secondary">Ver Todos</a>
                </div>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Estudiante</th>
                                <th>Curso</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_certificates as $cert): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cert['certificate_number']); ?></td>
                                    <td><?php echo htmlspecialchars($cert['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($cert['course_name']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($cert['issue_date'])); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $cert['status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($cert['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="../certificates/download.php?id=<?php echo $cert['id']; ?>" 
                                               class="btn btn-sm btn-info" target="_blank">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <?php if ($cert['status'] === 'active'): ?>
                                                <button class="btn btn-sm btn-danger revoke-cert" 
                                                        data-id="<?php echo $cert['id']; ?>">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Plantillas -->
            <div class="content-card">
                <div class="card-header">
                    <h3>Plantillas de Certificados</h3>
                </div>
                
                <div class="templates-grid">
                    <?php foreach ($templates as $template): ?>
                        <div class="template-card">
                            <div class="template-preview">
                                <!-- Aquí iría una vista previa de la plantilla -->
                            </div>
                            <div class="template-info">
                                <h4><?php echo htmlspecialchars($template['name']); ?></h4>
                                <p><?php echo htmlspecialchars($template['description']); ?></p>
                                <div class="template-actions">
                                    <button class="btn btn-sm btn-primary edit-template" 
                                            data-template='<?php echo htmlspecialchars(json_encode($template)); ?>'>
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <?php if (!$template['is_active']): ?>
                                        <button class="btn btn-sm btn-danger delete-template" 
                                                data-id="<?php echo $template['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Plantilla -->
<div class="modal" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Plantilla de Certificado</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="templateForm">
                    <input type="hidden" name="id">
                    
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>HTML Template</label>
                        <textarea name="html_template" class="form-control code-editor" rows="10"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>CSS Styles</label>
                        <textarea name="css_styles" class="form-control code-editor" rows="10"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active">
                            <label class="custom-control-label" for="is_active">Plantilla Activa</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="saveTemplate">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/certificate-admin.js"></script>

<?php require_once '../../includes/footer.php'; ?> 
<?php
require_once '../../includes/header.php';
require_once '../../includes/RoleManager.php';

if (!is_admin()) {
    header("Location: ../../login.php");
    exit();
}

$role_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$role_manager = new RoleManager($conn);

$role = $role_manager->getRole($role_id);
if (!$role) {
    header("Location: index.php");
    exit();
}

$all_permissions = $role_manager->getPermissions();
$role_permissions = $role_manager->getRolePermissions($role_id);
$role_permission_ids = array_column($role_permissions, 'id');
?>

<div class="admin-container">
    <?php require_once '../sidebar.php'; ?>
    
    <div class="admin-content">
        <div class="permissions-container">
            <div class="permissions-header">
                <h2>Permisos del Rol: <?php echo htmlspecialchars($role['name']); ?></h2>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
            
            <form id="permissionsForm" data-role-id="<?php echo $role_id; ?>">
                <div class="permissions-grid">
                    <?php foreach ($all_permissions as $permission): ?>
                        <div class="permission-item">
                            <label class="permission-label">
                                <input type="checkbox" 
                                       name="permissions[]" 
                                       value="<?php echo $permission['id']; ?>"
                                       <?php echo in_array($permission['id'], $role_permission_ids) ? 'checked' : ''; ?>>
                                <span class="permission-name">
                                    <?php echo htmlspecialchars($permission['name']); ?>
                                </span>
                            </label>
                            <?php if ($permission['description']): ?>
                                <p class="permission-description">
                                    <?php echo htmlspecialchars($permission['description']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/permissions.js"></script>

<?php require_once '../../includes/footer.php'; ?> 
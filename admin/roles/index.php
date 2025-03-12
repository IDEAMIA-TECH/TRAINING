<?php
require_once '../../includes/header.php';
require_once '../../includes/RoleManager.php';

if (!is_admin()) {
    header("Location: ../../login.php");
    exit();
}

$role_manager = new RoleManager($conn);
$roles = $role_manager->getRoles();
?>

<div class="admin-container">
    <?php require_once '../sidebar.php'; ?>
    
    <div class="admin-content">
        <div class="roles-container">
            <div class="roles-header">
                <h2>Gestión de Roles</h2>
                <button class="btn btn-primary" onclick="showRoleModal()">
                    <i class="fas fa-plus"></i> Nuevo Rol
                </button>
            </div>
            
            <div class="roles-grid">
                <?php foreach ($roles as $role): ?>
                    <div class="role-card">
                        <div class="role-info">
                            <h3><?php echo htmlspecialchars($role['name']); ?></h3>
                            <p class="role-description">
                                <?php echo htmlspecialchars($role['description']); ?>
                            </p>
                            
                            <div class="role-meta">
                                <span class="role-users">
                                    <?php
                                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role_id = ?");
                                    $stmt->execute([$role['id']]);
                                    echo $stmt->fetch(PDO::FETCH_ASSOC)['count'] . ' usuarios';
                                    ?>
                                </span>
                                <span class="role-permissions">
                                    <?php
                                    $permissions = $role_manager->getRolePermissions($role['id']);
                                    echo count($permissions) . ' permisos';
                                    ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="role-actions">
                            <button class="btn btn-sm btn-info" 
                                    onclick="editRole(<?php echo htmlspecialchars(json_encode($role)); ?>)">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            <button class="btn btn-sm btn-secondary" 
                                    onclick="managePermissions(<?php echo $role['id']; ?>)">
                                <i class="fas fa-key"></i> Permisos
                            </button>
                            <?php if ($role['id'] !== 1): // Evitar eliminar el rol de administrador ?>
                                <button class="btn btn-sm btn-danger" 
                                        onclick="deleteRole(<?php echo $role['id']; ?>)">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Rol -->
<div class="modal" id="roleModal">
    <div class="modal-content">
        <h3>Rol</h3>
        <form id="roleForm">
            <input type="hidden" id="roleId">
            
            <div class="form-group">
                <label for="roleName">Nombre *</label>
                <input type="text" id="roleName" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="roleDescription">Descripción</label>
                <textarea id="roleDescription" name="description"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Guardar</button>
                <button type="button" class="btn btn-secondary" onclick="closeRoleModal()">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/roles.js"></script>

<?php require_once '../../includes/footer.php'; ?> 
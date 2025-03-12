// Variables globales
let currentRole = null;

// Funciones para el modal
function showRoleModal() {
    document.getElementById('roleModal').style.display = 'block';
    document.getElementById('roleForm').reset();
    currentRole = null;
}

function closeRoleModal() {
    document.getElementById('roleModal').style.display = 'none';
    currentRole = null;
}

// Editar rol
function editRole(role) {
    currentRole = role;
    
    document.getElementById('roleId').value = role.id;
    document.getElementById('roleName').value = role.name;
    document.getElementById('roleDescription').value = role.description || '';
    
    showRoleModal();
}

// Eliminar rol
async function deleteRole(id) {
    if (!confirm('¿Estás seguro de eliminar este rol? Los usuarios asignados perderán sus permisos.')) {
        return;
    }
    
    try {
        const response = await fetch('/api/roles/delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id })
        });
        
        const data = await response.json();
        
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.error || 'Error al eliminar el rol');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al eliminar el rol');
    }
}

// Gestionar permisos
function managePermissions(roleId) {
    window.location.href = `permissions.php?id=${roleId}`;
}

// Manejar envío del formulario
document.getElementById('roleForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const roleData = {
        name: formData.get('name'),
        description: formData.get('description')
    };
    
    if (currentRole) {
        roleData.id = currentRole.id;
    }
    
    try {
        const response = await fetch('/api/roles/save.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(roleData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.error || 'Error al guardar el rol');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al guardar el rol');
    }
}); 
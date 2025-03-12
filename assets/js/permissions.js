document.getElementById('permissionsForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const roleId = this.dataset.roleId;
    const permissions = Array.from(this.querySelectorAll('input[name="permissions[]"]:checked'))
        .map(input => input.value);
    
    try {
        const response = await fetch('/api/roles/update-permissions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                role_id: roleId,
                permissions: permissions
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Permisos actualizados correctamente');
            window.location.href = 'index.php';
        } else {
            alert(data.error || 'Error al actualizar los permisos');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al actualizar los permisos');
    }
}); 
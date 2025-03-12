// Variables globales
let currentMaterial = null;

// Funciones para el modal
function showUploadModal() {
    document.getElementById('uploadModal').style.display = 'block';
    document.getElementById('uploadForm').reset();
}

function closeUploadModal() {
    document.getElementById('uploadModal').style.display = 'none';
    currentMaterial = null;
}

// Editar material
function editMaterial(material) {
    currentMaterial = material;
    
    // Llenar formulario
    document.getElementById('title').value = material.title;
    document.getElementById('description').value = material.description;
    document.getElementById('order_index').value = material.order_index;
    document.getElementById('is_public').checked = material.is_public;
    
    // Mostrar modal
    showUploadModal();
}

// Eliminar material
async function deleteMaterial(id) {
    if (!confirm('¿Estás seguro de eliminar este material?')) {
        return;
    }
    
    try {
        const response = await fetch('/api/materials/delete.php', {
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
            alert(data.error || 'Error al eliminar el material');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al eliminar el material');
    }
}

// Manejar envío del formulario
document.getElementById('uploadForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    if (currentMaterial) {
        formData.append('id', currentMaterial.id);
    }
    
    try {
        const response = await fetch('/api/materials/upload.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.error || 'Error al subir el material');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al subir el material');
    }
});

// Previsualización de archivos
document.getElementById('file').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    // Mostrar nombre y tamaño del archivo
    const fileInfo = document.createElement('div');
    fileInfo.className = 'file-info';
    fileInfo.innerHTML = `
        <strong>${file.name}</strong>
        <span>(${formatFileSize(file.size)})</span>
    `;
    
    const container = this.parentElement;
    const existingInfo = container.querySelector('.file-info');
    if (existingInfo) {
        container.removeChild(existingInfo);
    }
    container.appendChild(fileInfo);
});

// Utilidades
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
} 
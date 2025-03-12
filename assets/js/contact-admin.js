// Variables globales
let currentMessageId = null;

// Funciones para el modal
function showReplyModal() {
    document.getElementById('replyModal').style.display = 'block';
}

function closeReplyModal() {
    document.getElementById('replyModal').style.display = 'none';
    document.getElementById('replyForm').reset();
    currentMessageId = null;
}

// Marcar mensaje como leído
async function markAsRead(id) {
    try {
        const response = await fetch('/api/contact/mark-read.php', {
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
            alert(data.error || 'Error al marcar el mensaje como leído');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al marcar el mensaje como leído');
    }
}

// Responder mensaje
function replyMessage(id) {
    currentMessageId = id;
    showReplyModal();
}

// Eliminar mensaje
async function deleteMessage(id) {
    if (!confirm('¿Estás seguro de eliminar este mensaje?')) {
        return;
    }
    
    try {
        const response = await fetch('/api/contact/delete.php', {
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
            alert(data.error || 'Error al eliminar el mensaje');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al eliminar el mensaje');
    }
}

// Manejar envío del formulario de respuesta
document.getElementById('replyForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    if (!currentMessageId) return;
    
    const formData = new FormData(this);
    const message = formData.get('message');
    
    try {
        const response = await fetch('/api/contact/reply.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: currentMessageId,
                message: message
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeReplyModal();
            window.location.reload();
        } else {
            alert(data.error || 'Error al enviar la respuesta');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al enviar la respuesta');
    }
}); 
// Función para cancelar suscripción
async function cancelSubscription() {
    if (!confirm('¿Estás seguro de que deseas cancelar tu suscripción?')) {
        return;
    }
    
    try {
        const subscriptionId = document.querySelector('[data-subscription-id]').dataset.subscriptionId;
        
        const response = await fetch('/api/subscriptions/cancel.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ subscription_id: subscriptionId })
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || 'Error al cancelar la suscripción');
        }
        
        // Mostrar mensaje de éxito
        showAlert('success', 'Suscripción cancelada correctamente');
        
        // Recargar la página después de 2 segundos
        setTimeout(() => {
            window.location.reload();
        }, 2000);
        
    } catch (error) {
        showAlert('error', error.message);
    }
}

// Función para mostrar alertas
function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.querySelector('.billing-container').prepend(alertDiv);
    
    // Remover alerta después de 5 segundos
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Inicializar tooltips de Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(tooltip => {
        new bootstrap.Tooltip(tooltip);
    });
}); 
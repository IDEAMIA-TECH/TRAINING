// Variables globales
let ws = null;
let notificationSound = new Audio('/assets/sounds/notification.mp3');

// Inicializar WebSocket y notificaciones del navegador
document.addEventListener('DOMContentLoaded', function() {
    initWebSocket();
    requestNotificationPermission();
});

// Inicializar WebSocket
function initWebSocket() {
    ws = new WebSocket('ws://' + window.location.hostname + ':8080');
    
    ws.onopen = function() {
        console.log('Conectado al servidor de notificaciones');
        // Enviar ID de usuario para identificación
        ws.send(JSON.stringify({
            type: 'auth',
            user_id: USER_ID
        }));
    };
    
    ws.onmessage = function(event) {
        const data = JSON.parse(event.data);
        handleNewNotification(data);
    };
    
    ws.onclose = function() {
        console.log('Desconectado del servidor de notificaciones');
        // Intentar reconectar después de 5 segundos
        setTimeout(initWebSocket, 5000);
    };
}

// Solicitar permiso para notificaciones del navegador
async function requestNotificationPermission() {
    if (!('Notification' in window)) {
        console.log('Este navegador no soporta notificaciones');
        return;
    }
    
    try {
        const permission = await Notification.requestPermission();
        if (permission === 'granted') {
            console.log('Permiso de notificaciones concedido');
        }
    } catch (error) {
        console.error('Error al solicitar permiso:', error);
    }
}

// Manejar nueva notificación
function handleNewNotification(data) {
    // Reproducir sonido
    notificationSound.play();
    
    // Mostrar notificación del navegador si está permitido
    if (Notification.permission === 'granted' && data.browser_notification) {
        new Notification(data.title, {
            body: data.message,
            icon: '/assets/images/notification-icon.png'
        });
    }
    
    // Actualizar contador de notificaciones
    updateNotificationCount();
    
    // Agregar notificación a la lista si estamos en la página de notificaciones
    if (document.querySelector('.notifications-list')) {
        prependNotification(data);
    }
}

// Agregar nueva notificación al inicio de la lista
function prependNotification(data) {
    const template = `
        <div class="notification-item unread" data-id="${data.id}">
            <div class="notification-icon">
                ${getNotificationIcon(data.type)}
            </div>
            <div class="notification-content">
                <h3>${escapeHtml(data.title)}</h3>
                <p>${escapeHtml(data.message)}</p>
                <div class="notification-meta">
                    <span class="notification-time">Hace un momento</span>
                    ${data.link ? `<a href="${data.link}" class="notification-link">Ver más</a>` : ''}
                </div>
            </div>
            <div class="notification-actions">
                <button onclick="markAsRead(${data.id})" class="btn btn-sm btn-secondary">
                    <i class="fas fa-check"></i>
                </button>
                <button onclick="deleteNotification(${data.id})" class="btn btn-sm btn-danger">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    
    const list = document.querySelector('.notifications-list');
    const emptyState = list.querySelector('.empty-state');
    
    if (emptyState) {
        emptyState.remove();
    }
    
    list.insertAdjacentHTML('afterbegin', template);
}

// Marcar notificación como leída
async function markAsRead(id) {
    try {
        const response = await fetch('/api/notifications/mark-read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id })
        });
        
        const data = await response.json();
        
        if (data.success) {
            const notification = document.querySelector(`.notification-item[data-id="${id}"]`);
            notification.classList.remove('unread');
            notification.classList.add('read');
            
            const readButton = notification.querySelector('.btn-secondary');
            if (readButton) {
                readButton.remove();
            }
            
            updateNotificationCount();
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al marcar como leída');
    }
}

// Marcar todas como leídas
async function markAllAsRead() {
    try {
        const response = await fetch('/api/notifications/mark-all-read.php', {
            method: 'POST'
        });
        
        const data = await response.json();
        
        if (data.success) {
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
                item.classList.add('read');
                
                const readButton = item.querySelector('.btn-secondary');
                if (readButton) {
                    readButton.remove();
                }
            });
            
            updateNotificationCount();
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al marcar todas como leídas');
    }
}

// Eliminar notificación
async function deleteNotification(id) {
    if (!confirm('¿Estás seguro de eliminar esta notificación?')) {
        return;
    }
    
    try {
        const response = await fetch('/api/notifications/delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id })
        });
        
        const data = await response.json();
        
        if (data.success) {
            const notification = document.querySelector(`.notification-item[data-id="${id}"]`);
            notification.remove();
            
            updateNotificationCount();
            
            // Mostrar estado vacío si no hay más notificaciones
            const list = document.querySelector('.notifications-list');
            if (!list.querySelector('.notification-item')) {
                list.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <p>No tienes notificaciones</p>
                    </div>
                `;
            }
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al eliminar la notificación');
    }
}

// Actualizar contador de notificaciones
async function updateNotificationCount() {
    try {
        const response = await fetch('/api/notifications/unread-count.php');
        const data = await response.json();
        
        const counter = document.querySelector('.notification-counter');
        if (counter) {
            counter.textContent = data.count;
            counter.style.display = data.count > 0 ? 'block' : 'none';
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Funciones del modal de preferencias
function showPreferences() {
    document.getElementById('preferencesModal').style.display = 'block';
}

function closePreferencesModal() {
    document.getElementById('preferencesModal').style.display = 'none';
}

// Manejar formulario de preferencias
document.getElementById('preferencesForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const preferences = {
        email_notifications: formData.get('email_notifications') === 'on',
        browser_notifications: formData.get('browser_notifications') === 'on'
    };
    
    try {
        const response = await fetch('/api/notifications/update-preferences.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(preferences)
        });
        
        const data = await response.json();
        
        if (data.success) {
            closePreferencesModal();
            alert('Preferencias actualizadas correctamente');
        } else {
            alert(data.error || 'Error al actualizar las preferencias');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al actualizar las preferencias');
    }
});

// Utilidades
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getNotificationIcon(type) {
    const icons = {
        'info': '<i class="fas fa-info-circle text-info"></i>',
        'success': '<i class="fas fa-check-circle text-success"></i>',
        'warning': '<i class="fas fa-exclamation-triangle text-warning"></i>',
        'error': '<i class="fas fa-times-circle text-danger"></i>'
    };
    return icons[type] || icons['info'];
} 
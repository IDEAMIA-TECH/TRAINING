class SupportDashboard {
    constructor() {
        this.modal = document.getElementById('chat-modal');
        this.messages = document.getElementById('modal-messages');
        this.form = document.getElementById('modal-chat-form');
        this.userName = document.getElementById('chat-user-name');
        this.sessionId = document.getElementById('modal-session-id');
        this.lastMessageId = 0;
        this.updateInterval = null;
        
        this.init();
    }
    
    init() {
        // Manejar cambio de estado del operador
        document.querySelector('.status-form').addEventListener('change', (e) => {
            e.target.form.submit();
        });
        
        // Manejar acciones de las sesiones
        document.querySelectorAll('.session-card').forEach(card => {
            const sessionId = card.dataset.sessionId;
            const userName = card.querySelector('h4').textContent;
            
            card.querySelector('.join-chat').addEventListener('click', () => {
                this.openChat(sessionId, userName);
            });
            
            card.querySelector('.close-chat').addEventListener('click', () => {
                this.closeSession(sessionId);
            });
        });
        
        // Manejar modal
        document.querySelector('.modal-close').addEventListener('click', () => {
            this.closeModal();
        });
        
        // Manejar envío de mensajes
        this.form.addEventListener('submit', (e) => this.sendMessage(e));
    }
    
    openChat(sessionId, userName) {
        this.sessionId.value = sessionId;
        this.userName.textContent = userName;
        this.modal.style.display = 'flex';
        this.messages.innerHTML = '';
        this.lastMessageId = 0;
        
        this.updateMessages();
        this.startMessagePolling();
    }
    
    closeModal() {
        this.modal.style.display = 'none';
        clearInterval(this.updateInterval);
    }
    
    async closeSession(sessionId) {
        if (!confirm('¿Estás seguro de cerrar esta sesión de chat?')) return;
        
        try {
            const response = await fetch('/api/chat/close.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ session_id: sessionId })
            });
            
            if (response.ok) {
                window.location.reload();
            } else {
                const data = await response.json();
                alert(data.error || 'Error al cerrar la sesión');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error al cerrar la sesión');
        }
    }
    
    async sendMessage(e) {
        e.preventDefault();
        const formData = new FormData(this.form);
        
        try {
            const response = await fetch('/api/chat/send.php', {
                method: 'POST',
                body: formData
            });
            
            if (response.ok) {
                this.form.reset();
                await this.updateMessages();
            } else {
                const data = await response.json();
                alert(data.error || 'Error al enviar el mensaje');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error al enviar el mensaje');
        }
    }
    
    async updateMessages() {
        try {
            const response = await fetch(
                `/api/chat/messages.php?session_id=${this.sessionId.value}&last_id=${this.lastMessageId}`
            );
            
            if (response.ok) {
                const data = await response.json();
                if (data.messages.length > 0) {
                    this.lastMessageId = data.messages[data.messages.length - 1].id;
                    this.renderMessages(data.messages);
                }
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }
    
    renderMessages(messages) {
        const currentUserId = document.body.dataset.userId;
        
        messages.forEach(message => {
            const messageDiv = document.createElement('div');
            messageDiv.className = `chat-message ${message.sender_id == currentUserId ? 'sent' : 'received'}`;
            messageDiv.innerHTML = `
                <div class="message-content">
                    <div class="message-sender">${message.sender_name}</div>
                    <div class="message-text">${message.message}</div>
                    <div class="message-time">
                        ${new Date(message.created_at).toLocaleTimeString()}
                    </div>
                </div>
            `;
            
            this.messages.appendChild(messageDiv);
        });
        
        this.messages.scrollTop = this.messages.scrollHeight;
    }
    
    startMessagePolling() {
        this.updateInterval = setInterval(() => this.updateMessages(), 5000);
    }
}

document.addEventListener('DOMContentLoaded', () => new SupportDashboard()); 
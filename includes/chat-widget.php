<?php
require_once 'ChatManager.php';
$chat_manager = new ChatManager($conn);

$session = null;
if (is_logged_in()) {
    // Verificar si hay una sesión activa
    $stmt = $conn->prepare("
        SELECT id FROM chat_sessions 
        WHERE user_id = ? AND status = 'active'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div id="chat-widget" class="chat-widget">
    <button id="chat-toggle" class="chat-toggle">
        <i class="fas fa-comments"></i>
        <span class="chat-badge" style="display: none">0</span>
    </button>
    
    <div id="chat-container" class="chat-container" style="display: none;">
        <div class="chat-header">
            <h3>Soporte en línea</h3>
            <button class="chat-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <?php if (!is_logged_in()): ?>
            <div class="chat-login-prompt">
                <p>Por favor inicia sesión para chatear con soporte</p>
                <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-primary">
                    Iniciar Sesión
                </a>
            </div>
        <?php elseif (!$session): ?>
            <div class="chat-start-prompt">
                <p>¿En qué podemos ayudarte?</p>
                <button id="start-chat" class="btn btn-primary">
                    Iniciar Chat
                </button>
            </div>
        <?php else: ?>
            <div id="chat-messages" class="chat-messages"></div>
            
            <form id="chat-form" class="chat-form">
                <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                <div class="chat-input-container">
                    <textarea name="message" placeholder="Escribe tu mensaje..." required></textarea>
                    <button type="submit">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
const chatWidget = {
    toggle: document.getElementById('chat-toggle'),
    container: document.getElementById('chat-container'),
    messages: document.getElementById('chat-messages'),
    form: document.getElementById('chat-form'),
    startButton: document.getElementById('start-chat'),
    badge: document.querySelector('.chat-badge'),
    lastMessageId: 0,
    updateInterval: null,
    
    init() {
        this.toggle.addEventListener('click', () => this.toggleChat());
        document.querySelector('.chat-close').addEventListener('click', () => this.toggleChat());
        
        if (this.startButton) {
            this.startButton.addEventListener('click', () => this.startChat());
        }
        
        if (this.form) {
            this.form.addEventListener('submit', (e) => this.sendMessage(e));
            this.startMessagePolling();
        }
    },
    
    toggleChat() {
        this.container.style.display = this.container.style.display === 'none' ? 'flex' : 'none';
        if (this.container.style.display === 'flex') {
            this.badge.style.display = 'none';
            this.badge.textContent = '0';
            if (this.messages) {
                this.messages.scrollTop = this.messages.scrollHeight;
            }
        }
    },
    
    async startChat() {
        try {
            const response = await fetch('<?php echo BASE_URL; ?>/api/chat/start.php', {
                method: 'POST'
            });
            
            if (response.ok) {
                window.location.reload();
            } else {
                const data = await response.json();
                alert(data.error || 'Error al iniciar el chat');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error al iniciar el chat');
        }
    },
    
    async sendMessage(e) {
        e.preventDefault();
        const formData = new FormData(this.form);
        
        try {
            const response = await fetch('<?php echo BASE_URL; ?>/api/chat/send.php', {
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
    },
    
    async updateMessages() {
        try {
            const sessionId = this.form.querySelector('[name="session_id"]').value;
            const response = await fetch(
                `<?php echo BASE_URL; ?>/api/chat/messages.php?session_id=${sessionId}&last_id=${this.lastMessageId}`
            );
            
            if (response.ok) {
                const data = await response.json();
                if (data.messages.length > 0) {
                    this.lastMessageId = data.messages[data.messages.length - 1].id;
                    this.renderMessages(data.messages);
                    
                    if (this.container.style.display === 'none') {
                        this.badge.style.display = 'block';
                        this.badge.textContent = parseInt(this.badge.textContent || 0) + data.messages.length;
                    }
                }
            }
        } catch (error) {
            console.error('Error:', error);
        }
    },
    
    renderMessages(messages) {
        const currentUserId = <?php echo is_logged_in() ? $_SESSION['user_id'] : 'null'; ?>;
        
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
    },
    
    startMessagePolling() {
        this.updateInterval = setInterval(() => this.updateMessages(), 5000);
    }
};

document.addEventListener('DOMContentLoaded', () => chatWidget.init());
</script> 
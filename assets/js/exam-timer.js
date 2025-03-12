class ExamTimer {
    constructor(duration) {
        this.duration = duration * 60; // Convertir a segundos
        this.timeLeft = this.duration;
        this.timerElement = document.getElementById('timer');
        this.interval = null;
    }
    
    start() {
        this.interval = setInterval(() => {
            this.timeLeft--;
            this.updateDisplay();
            
            if (this.timeLeft <= 0) {
                this.stop();
                document.getElementById('examForm').submit();
            }
        }, 1000);
        
        this.updateDisplay();
    }
    
    stop() {
        clearInterval(this.interval);
    }
    
    updateDisplay() {
        const minutes = Math.floor(this.timeLeft / 60);
        const seconds = this.timeLeft % 60;
        
        this.timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        
        if (this.timeLeft <= 300) { // 5 minutos
            this.timerElement.classList.add('warning');
        }
        
        if (this.timeLeft <= 60) { // 1 minuto
            this.timerElement.classList.add('danger');
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('examForm');
    if (!form) return;
    
    const duration = parseInt(form.dataset.duration);
    const timer = new ExamTimer(duration);
    timer.start();
    
    // Confirmar antes de salir de la página
    window.addEventListener('beforeunload', function(e) {
        e.preventDefault();
        e.returnValue = '';
    });
    
    // Manejar envío del formulario
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        timer.stop();
        
        if (!confirm('¿Estás seguro de enviar tus respuestas?')) {
            timer.start();
            return;
        }
        
        try {
            const formData = new FormData(this);
            const response = await fetch('/api/exams/submit.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                window.location.href = `/exams/results.php?attempt_id=${data.attempt_id}`;
            } else {
                alert(data.error || 'Error al enviar las respuestas');
                timer.start();
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error al enviar las respuestas');
            timer.start();
        }
    });
}); 
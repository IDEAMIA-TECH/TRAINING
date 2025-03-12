class ExamTake {
    constructor() {
        this.form = document.getElementById('examForm');
        this.attemptId = this.form.dataset.attempt;
        this.questions = document.querySelectorAll('.question-slide');
        this.currentIndex = 0;
        this.timer = document.querySelector('.timer');
        this.duration = parseInt(this.timer.dataset.duration);
        this.timeRemaining = this.duration * 60; // convertir a segundos
        
        this.initEventListeners();
        this.showQuestion(0);
        this.startTimer();
        this.initAutoSave();
    }
    
    initEventListeners() {
        // Navegación entre preguntas
        document.querySelectorAll('.prev-question').forEach(button => {
            button.addEventListener('click', () => this.prevQuestion());
        });
        
        document.querySelectorAll('.next-question').forEach(button => {
            button.addEventListener('click', () => this.nextQuestion());
        });
        
        // Finalizar examen
        document.querySelector('.finish-exam')?.addEventListener('click', () => {
            if (confirm('¿Estás seguro de finalizar el examen?')) {
                this.finishExam();
            }
        });
        
        // Guardar respuestas al cambiar
        this.form.addEventListener('change', (e) => {
            if (e.target.name?.startsWith('answer_')) {
                this.saveAnswer(e.target);
            }
        });
        
        // Prevenir envío accidental del formulario
        this.form.addEventListener('submit', (e) => e.preventDefault());
    }
    
    showQuestion(index) {
        this.questions.forEach((q, i) => {
            q.style.display = i === index ? 'block' : 'none';
        });
        
        document.querySelector('.current-question').textContent = index + 1;
        this.currentIndex = index;
    }
    
    prevQuestion() {
        if (this.currentIndex > 0) {
            this.showQuestion(this.currentIndex - 1);
        }
    }
    
    nextQuestion() {
        if (this.currentIndex < this.questions.length - 1) {
            this.showQuestion(this.currentIndex + 1);
        }
    }
    
    startTimer() {
        const updateTimer = () => {
            const minutes = Math.floor(this.timeRemaining / 60);
            const seconds = this.timeRemaining % 60;
            
            this.timer.querySelector('.time-remaining').textContent = 
                `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (this.timeRemaining <= 0) {
                this.finishExam();
            } else {
                this.timeRemaining--;
            }
        };
        
        updateTimer();
        this.timerInterval = setInterval(updateTimer, 1000);
    }
    
    initAutoSave() {
        // Guardar cada 30 segundos
        setInterval(() => {
            const pendingInputs = document.querySelectorAll('[data-pending="true"]');
            pendingInputs.forEach(input => this.saveAnswer(input));
        }, 30000);
    }
    
    async saveAnswer(input) {
        try {
            const questionId = input.name.split('_')[1];
            const answer = input.type === 'radio' ? input.value : input.value.trim();
            
            input.setAttribute('data-pending', 'true');
            
            const response = await fetch('/api/exams/save-answer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    attempt_id: this.attemptId,
                    question_id: questionId,
                    answer: answer
                })
            });
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error);
            }
            
            input.removeAttribute('data-pending');
            
        } catch (error) {
            console.error('Error al guardar respuesta:', error);
        }
    }
    
    async finishExam() {
        try {
            clearInterval(this.timerInterval);
            
            const response = await fetch('/api/exams/finish-attempt.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    attempt_id: this.attemptId
                })
            });
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error);
            }
            
            window.location.href = `results.php?attempt_id=${this.attemptId}`;
            
        } catch (error) {
            alert('Error al finalizar el examen: ' + error.message);
        }
    }
}

document.addEventListener('DOMContentLoaded', () => new ExamTake()); 
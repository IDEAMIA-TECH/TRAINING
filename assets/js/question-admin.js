class QuestionAdmin {
    constructor() {
        this.modal = document.getElementById('questionModal');
        this.form = document.getElementById('questionForm');
        this.optionsContainer = document.getElementById('optionsContainer');
        this.correctAnswerContainer = document.getElementById('correctAnswerContainer');
        this.optionTemplate = this.createOptionTemplate();
        this.initEventListeners();
    }
    
    createOptionTemplate() {
        const div = document.createElement('div');
        div.className = 'option-item mb-2';
        div.innerHTML = `
            <div class="input-group">
                <div class="input-group-prepend">
                    <div class="input-group-text">
                        <input type="checkbox" name="is_correct[]">
                    </div>
                </div>
                <input type="text" name="option_text[]" class="form-control" required>
                <div class="input-group-append">
                    <button type="button" class="btn btn-danger remove-option">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
        return div;
    }
    
    initEventListeners() {
        // Manejar cambio de tipo de pregunta
        this.form.querySelector('[name="question_type"]').addEventListener('change', (e) => {
            this.toggleQuestionType(e.target.value);
        });
        
        // Manejar agregar opción
        document.getElementById('addOption').addEventListener('click', () => {
            this.addOption();
        });
        
        // Manejar guardar pregunta
        document.getElementById('saveQuestion').addEventListener('click', () => {
            this.saveQuestion();
        });
        
        // Manejar edición de pregunta
        document.querySelectorAll('.edit-question').forEach(button => {
            button.addEventListener('click', (e) => {
                const question = JSON.parse(e.target.closest('button').dataset.question);
                this.loadQuestion(question);
            });
        });
        
        // Manejar eliminación de pregunta
        document.querySelectorAll('.delete-question').forEach(button => {
            button.addEventListener('click', (e) => {
                if (confirm('¿Estás seguro de eliminar esta pregunta?')) {
                    this.deleteQuestion(e.target.closest('button').dataset.id);
                }
            });
        });
        
        // Delegación de eventos para eliminar opciones
        this.optionsContainer.addEventListener('click', (e) => {
            if (e.target.closest('.remove-option')) {
                e.target.closest('.option-item').remove();
            }
        });
    }
    
    toggleQuestionType(type) {
        this.optionsContainer.style.display = type === 'multiple_choice' ? 'block' : 'none';
        this.correctAnswerContainer.style.display = type === 'short_answer' ? 'block' : 'none';
        
        // Limpiar contenedores
        if (type !== 'multiple_choice') {
            this.optionsContainer.querySelector('.options-list').innerHTML = '';
        }
        if (type !== 'short_answer') {
            this.form.querySelector('[name="correct_answer"]').value = '';
        }
    }
    
    addOption() {
        const optionsList = this.optionsContainer.querySelector('.options-list');
        const newOption = this.optionTemplate.cloneNode(true);
        optionsList.appendChild(newOption);
    }
    
    loadQuestion(question) {
        this.form.querySelector('[name="id"]').value = question.id;
        this.form.querySelector('[name="question_type"]').value = question.question_type;
        this.form.querySelector('[name="question_text"]').value = question.question_text;
        this.form.querySelector('[name="points"]').value = question.points;
        this.form.querySelector('[name="explanation"]').value = question.explanation || '';
        
        this.toggleQuestionType(question.question_type);
        
        // Cargar opciones si es opción múltiple
        if (question.question_type === 'multiple_choice') {
            this.loadOptions(question.id);
        }
        
        // Cargar respuesta correcta si es respuesta corta
        if (question.question_type === 'short_answer') {
            this.form.querySelector('[name="correct_answer"]').value = question.correct_answer || '';
        }
        
        $(this.modal).modal('show');
    }
    
    async loadOptions(questionId) {
        try {
            const response = await fetch(`/api/exams/get-options.php?question_id=${questionId}`);
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error);
            }
            
            const optionsList = this.optionsContainer.querySelector('.options-list');
            optionsList.innerHTML = '';
            
            result.options.forEach(option => {
                const optionElement = this.optionTemplate.cloneNode(true);
                optionElement.querySelector('[name="option_text[]"]').value = option.option_text;
                optionElement.querySelector('[name="is_correct[]"]').checked = option.is_correct;
                optionsList.appendChild(optionElement);
            });
            
        } catch (error) {
            alert('Error al cargar opciones: ' + error.message);
        }
    }
    
    async saveQuestion() {
        try {
            const formData = new FormData(this.form);
            const data = {
                id: formData.get('id'),
                exam_id: formData.get('exam_id'),
                question_type: formData.get('question_type'),
                question_text: formData.get('question_text'),
                points: formData.get('points'),
                explanation: formData.get('explanation'),
                correct_answer: formData.get('correct_answer')
            };
            
            // Recopilar opciones si es opción múltiple
            if (data.question_type === 'multiple_choice') {
                const options = [];
                const optionTexts = formData.getAll('option_text[]');
                const isCorrect = formData.getAll('is_correct[]');
                
                optionTexts.forEach((text, index) => {
                    if (text.trim()) {
                        options.push({
                            text: text.trim(),
                            is_correct: isCorrect[index] === 'on'
                        });
                    }
                });
                
                data.options = options;
            }
            
            const response = await fetch('/api/exams/save-question.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error);
            }
            
            window.location.reload();
            
        } catch (error) {
            alert('Error al guardar la pregunta: ' + error.message);
        }
    }
    
    async deleteQuestion(questionId) {
        try {
            const response = await fetch('/api/exams/delete-question.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: questionId })
            });
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error);
            }
            
            window.location.reload();
            
        } catch (error) {
            alert('Error al eliminar la pregunta: ' + error.message);
        }
    }
}

document.addEventListener('DOMContentLoaded', () => new QuestionAdmin()); 
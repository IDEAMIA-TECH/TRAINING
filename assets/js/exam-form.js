let questionCounter = 0;

function addQuestion() {
    const questionsList = document.getElementById('questionsList');
    const questionDiv = document.createElement('div');
    questionDiv.className = 'question-item';
    questionDiv.dataset.questionId = questionCounter;
    
    questionDiv.innerHTML = `
        <div class="question-header">
            <h4>Pregunta ${questionCounter + 1}</h4>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeQuestion(${questionCounter})">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        
        <div class="form-group">
            <label>Texto de la pregunta *</label>
            <textarea name="questions[${questionCounter}][text]" required></textarea>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Tipo de pregunta *</label>
                <select name="questions[${questionCounter}][type]" onchange="handleQuestionType(${questionCounter}, this.value)" required>
                    <option value="multiple_choice">Opción múltiple</option>
                    <option value="true_false">Verdadero/Falso</option>
                    <option value="short_answer">Respuesta corta</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Puntos *</label>
                <input type="number" name="questions[${questionCounter}][points]" min="1" value="1" required>
            </div>
        </div>
        
        <div class="options-container" id="options_${questionCounter}">
            <div class="options-list"></div>
            <button type="button" class="btn btn-secondary btn-sm" onclick="addOption(${questionCounter})">
                <i class="fas fa-plus"></i> Agregar Opción
            </button>
        </div>
    `;
    
    questionsList.appendChild(questionDiv);
    handleQuestionType(questionCounter, 'multiple_choice');
    questionCounter++;
}

function removeQuestion(id) {
    const question = document.querySelector(`[data-question-id="${id}"]`);
    question.remove();
    updateQuestionNumbers();
}

function updateQuestionNumbers() {
    document.querySelectorAll('.question-item').forEach((item, index) => {
        item.querySelector('h4').textContent = `Pregunta ${index + 1}`;
    });
}

function handleQuestionType(questionId, type) {
    const optionsContainer = document.getElementById(`options_${questionId}`);
    
    if (type === 'multiple_choice') {
        optionsContainer.style.display = 'block';
        if (!optionsContainer.querySelector('.option-item')) {
            addOption(questionId);
            addOption(questionId);
        }
    } else if (type === 'true_false') {
        optionsContainer.style.display = 'block';
        const optionsList = optionsContainer.querySelector('.options-list');
        optionsList.innerHTML = `
            <div class="option-item">
                <input type="radio" name="questions[${questionId}][options][correct]" value="true" required>
                <input type="text" name="questions[${questionId}][options][0][text]" value="Verdadero" readonly>
            </div>
            <div class="option-item">
                <input type="radio" name="questions[${questionId}][options][correct]" value="false" required>
                <input type="text" name="questions[${questionId}][options][1][text]" value="Falso" readonly>
            </div>
        `;
        optionsContainer.querySelector('button').style.display = 'none';
    } else {
        optionsContainer.style.display = 'none';
    }
}

function addOption(questionId) {
    const optionsList = document.querySelector(`#options_${questionId} .options-list`);
    const optionCount = optionsList.children.length;
    
    const optionDiv = document.createElement('div');
    optionDiv.className = 'option-item';
    
    optionDiv.innerHTML = `
        <input type="radio" name="questions[${questionId}][options][correct]" value="${optionCount}" required>
        <input type="text" name="questions[${questionId}][options][${optionCount}][text]" placeholder="Opción ${optionCount + 1}" required>
        <button type="button" class="btn btn-danger btn-sm" onclick="removeOption(this)">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    optionsList.appendChild(optionDiv);
}

function removeOption(button) {
    const optionItem = button.parentElement;
    const optionsList = optionItem.parentElement;
    
    if (optionsList.children.length > 2) {
        optionItem.remove();
        updateOptionNumbers(optionsList);
    } else {
        alert('Debe haber al menos dos opciones');
    }
}

function updateOptionNumbers(optionsList) {
    optionsList.querySelectorAll('.option-item').forEach((item, index) => {
        const textInput = item.querySelector('input[type="text"]');
        textInput.placeholder = `Opción ${index + 1}`;
    });
}

// Agregar primera pregunta al cargar el formulario
document.addEventListener('DOMContentLoaded', function() {
    addQuestion();
});

// Validar formulario antes de enviar
document.getElementById('examForm').addEventListener('submit', function(e) {
    const questions = document.querySelectorAll('.question-item');
    
    if (questions.length === 0) {
        e.preventDefault();
        alert('Debe agregar al menos una pregunta');
        return;
    }
    
    questions.forEach(question => {
        const type = question.querySelector('select').value;
        if (type === 'multiple_choice') {
            const options = question.querySelectorAll('.option-item');
            const hasCorrectAnswer = Array.from(options).some(option => 
                option.querySelector('input[type="radio"]').checked
            );
            
            if (!hasCorrectAnswer) {
                e.preventDefault();
                alert('Todas las preguntas de opción múltiple deben tener una respuesta correcta marcada');
                return;
            }
        }
    });
}); 
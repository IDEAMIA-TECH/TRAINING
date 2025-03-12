class ExamAdmin {
    constructor() {
        this.modal = document.getElementById('examModal');
        this.form = document.getElementById('examForm');
        this.initEventListeners();
    }
    
    initEventListeners() {
        // Manejar guardar examen
        document.getElementById('saveExam').addEventListener('click', () => {
            this.saveExam();
        });
        
        // Manejar eliminación de examen
        document.querySelectorAll('.delete-exam').forEach(button => {
            button.addEventListener('click', (e) => {
                if (confirm('¿Estás seguro de eliminar este examen?')) {
                    this.deleteExam(e.target.closest('button').dataset.id);
                }
            });
        });
    }
    
    async saveExam() {
        try {
            const formData = new FormData(this.form);
            const data = {
                course_id: formData.get('course_id'),
                title: formData.get('title'),
                description: formData.get('description'),
                duration: formData.get('duration'),
                passing_score: formData.get('passing_score'),
                attempts_allowed: formData.get('attempts_allowed'),
                is_active: formData.get('is_active') === 'on'
            };
            
            const response = await fetch('/api/exams/save.php', {
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
            
            window.location.href = `questions.php?exam_id=${result.exam_id}`;
            
        } catch (error) {
            alert('Error al guardar el examen: ' + error.message);
        }
    }
    
    async deleteExam(examId) {
        try {
            const response = await fetch('/api/exams/delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: examId })
            });
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error);
            }
            
            window.location.reload();
            
        } catch (error) {
            alert('Error al eliminar el examen: ' + error.message);
        }
    }
}

document.addEventListener('DOMContentLoaded', () => new ExamAdmin()); 
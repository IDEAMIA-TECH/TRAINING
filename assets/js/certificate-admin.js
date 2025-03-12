class CertificateAdmin {
    constructor() {
        this.modal = document.getElementById('templateModal');
        this.form = document.getElementById('templateForm');
        this.initCodeEditors();
        this.initEventListeners();
    }
    
    initCodeEditors() {
        // Inicializar editores de código con CodeMirror
        this.htmlEditor = CodeMirror.fromTextArea(
            document.querySelector('[name="html_template"]'),
            {
                mode: 'xml',
                theme: 'monokai',
                lineNumbers: true,
                autoCloseTags: true
            }
        );
        
        this.cssEditor = CodeMirror.fromTextArea(
            document.querySelector('[name="css_styles"]'),
            {
                mode: 'css',
                theme: 'monokai',
                lineNumbers: true
            }
        );
    }
    
    initEventListeners() {
        // Manejar edición de plantilla
        document.querySelectorAll('.edit-template').forEach(button => {
            button.addEventListener('click', (e) => {
                const template = JSON.parse(e.target.closest('button').dataset.template);
                this.loadTemplate(template);
                this.showModal();
            });
        });
        
        // Manejar eliminación de plantilla
        document.querySelectorAll('.delete-template').forEach(button => {
            button.addEventListener('click', (e) => {
                if (confirm('¿Estás seguro de eliminar esta plantilla?')) {
                    this.deleteTemplate(e.target.closest('button').dataset.id);
                }
            });
        });
        
        // Manejar revocación de certificado
        document.querySelectorAll('.revoke-cert').forEach(button => {
            button.addEventListener('click', (e) => {
                if (confirm('¿Estás seguro de revocar este certificado?')) {
                    this.revokeCertificate(e.target.closest('button').dataset.id);
                }
            });
        });
        
        // Manejar guardar plantilla
        document.getElementById('saveTemplate').addEventListener('click', () => {
            this.saveTemplate();
        });
        
        // Manejar cierre del modal
        document.querySelector('.modal .close').addEventListener('click', () => {
            this.hideModal();
        });
    }
    
    showModal() {
        this.modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
        this.htmlEditor.refresh();
        this.cssEditor.refresh();
    }
    
    hideModal() {
        this.modal.style.display = 'none';
        document.body.style.overflow = '';
        this.resetForm();
    }
    
    resetForm() {
        this.form.reset();
        this.form.querySelector('[name="id"]').value = '';
        this.htmlEditor.setValue('');
        this.cssEditor.setValue('');
    }
    
    loadTemplate(template) {
        this.form.querySelector('[name="id"]').value = template.id;
        this.form.querySelector('[name="name"]').value = template.name;
        this.form.querySelector('[name="description"]').value = template.description;
        this.htmlEditor.setValue(template.html_template);
        this.cssEditor.setValue(template.css_styles);
        this.form.querySelector('[name="is_active"]').checked = template.is_active;
    }
    
    async saveTemplate() {
        try {
            const formData = new FormData(this.form);
            const data = {
                id: formData.get('id'),
                name: formData.get('name'),
                description: formData.get('description'),
                html_template: this.htmlEditor.getValue(),
                css_styles: this.cssEditor.getValue(),
                is_active: formData.get('is_active') === 'on'
            };
            
            const response = await fetch('/api/certificates/save-template.php', {
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
            alert('Error al guardar la plantilla: ' + error.message);
        }
    }
    
    async deleteTemplate(templateId) {
        try {
            const response = await fetch('/api/certificates/delete-template.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: templateId })
            });
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error);
            }
            
            window.location.reload();
            
        } catch (error) {
            alert('Error al eliminar la plantilla: ' + error.message);
        }
    }
    
    async revokeCertificate(certificateId) {
        try {
            const response = await fetch('/api/certificates/revoke.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: certificateId })
            });
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error);
            }
            
            window.location.reload();
            
        } catch (error) {
            alert('Error al revocar el certificado: ' + error.message);
        }
    }
}

document.addEventListener('DOMContentLoaded', () => new CertificateAdmin()); 
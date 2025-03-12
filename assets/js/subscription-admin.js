class SubscriptionAdmin {
    constructor() {
        this.modal = document.getElementById('planModal');
        this.form = document.getElementById('planForm');
        this.featuresContainer = document.getElementById('features-container');
        
        this.init();
    }
    
    init() {
        // Manejar nuevo plan
        document.querySelector('[data-target="#newPlanModal"]').addEventListener('click', () => {
            this.resetForm();
            this.showModal();
        });
        
        // Manejar edición de plan
        document.querySelectorAll('.edit-plan').forEach(button => {
            button.addEventListener('click', (e) => {
                const plan = JSON.parse(e.target.closest('button').dataset.plan);
                this.loadPlan(plan);
                this.showModal();
            });
        });
        
        // Manejar eliminación de plan
        document.querySelectorAll('.delete-plan').forEach(button => {
            button.addEventListener('click', (e) => {
                if (confirm('¿Estás seguro de eliminar este plan?')) {
                    this.deletePlan(e.target.closest('button').dataset.id);
                }
            });
        });
        
        // Manejar cancelación de suscripción
        document.querySelectorAll('.cancel-subscription').forEach(button => {
            button.addEventListener('click', (e) => {
                if (confirm('¿Estás seguro de cancelar esta suscripción?')) {
                    this.cancelSubscription(e.target.closest('button').dataset.id);
                }
            });
        });
        
        // Manejar agregar característica
        document.getElementById('add-feature').addEventListener('click', () => {
            this.addFeatureInput();
        });
        
        // Manejar guardar plan
        document.getElementById('savePlan').addEventListener('click', () => {
            this.savePlan();
        });
        
        // Manejar cierre del modal
        document.querySelector('.modal .close').addEventListener('click', () => {
            this.hideModal();
        });
    }
    
    showModal() {
        this.modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
    
    hideModal() {
        this.modal.style.display = 'none';
        document.body.style.overflow = '';
    }
    
    resetForm() {
        this.form.reset();
        this.form.querySelector('[name="id"]').value = '';
        this.featuresContainer.innerHTML = '';
        this.addFeatureInput();
    }
    
    loadPlan(plan) {
        this.form.querySelector('[name="id"]').value = plan.id;
        this.form.querySelector('[name="name"]').value = plan.name;
        this.form.querySelector('[name="description"]').value = plan.description;
        this.form.querySelector('[name="price"]').value = plan.price;
        this.form.querySelector('[name="duration"]').value = plan.duration;
        this.form.querySelector('[name="is_active"]').checked = plan.is_active;
        
        // Cargar características
        this.featuresContainer.innerHTML = '';
        const features = JSON.parse(plan.features);
        features.forEach(feature => {
            this.addFeatureInput(feature);
        });
    }
    
    addFeatureInput(value = '') {
        const div = document.createElement('div');
        div.className = 'feature-input';
        div.innerHTML = `
            <input type="text" name="features[]" class="form-control" value="${value}">
            <button type="button" class="btn btn-sm btn-danger remove-feature">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        div.querySelector('.remove-feature').addEventListener('click', () => {
            div.remove();
        });
        
        this.featuresContainer.appendChild(div);
    }
    
    async savePlan() {
        try {
            const formData = new FormData(this.form);
            const features = Array.from(formData.getAll('features[]')).filter(f => f.trim());
            
            const data = {
                id: formData.get('id'),
                name: formData.get('name'),
                description: formData.get('description'),
                price: parseFloat(formData.get('price')),
                duration: parseInt(formData.get('duration')),
                features: features,
                is_active: formData.get('is_active') === 'on'
            };
            
            const response = await fetch('/api/subscriptions/save-plan.php', {
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
            alert('Error al guardar el plan: ' + error.message);
        }
    }
    
    async deletePlan(planId) {
        try {
            const response = await fetch('/api/subscriptions/delete-plan.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: planId })
            });
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error);
            }
            
            window.location.reload();
            
        } catch (error) {
            alert('Error al eliminar el plan: ' + error.message);
        }
    }
    
    async cancelSubscription(subscriptionId) {
        try {
            const response = await fetch('/api/subscriptions/cancel.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: subscriptionId })
            });
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error);
            }
            
            window.location.reload();
            
        } catch (error) {
            alert('Error al cancelar la suscripción: ' + error.message);
        }
    }
}

document.addEventListener('DOMContentLoaded', () => new SubscriptionAdmin()); 
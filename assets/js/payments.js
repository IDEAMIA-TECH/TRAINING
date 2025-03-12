class PaymentHandler {
    constructor() {
        this.stripe = Stripe(STRIPE_PUBLIC_KEY); // Definido en header.php
        this.elements = this.stripe.elements();
        this.card = null;
        this.modal = document.getElementById('payment-modal');
        this.form = document.getElementById('payment-form');
        this.errorDiv = document.getElementById('card-errors');
        this.submitButton = document.getElementById('submit-payment');
        this.paypalButton = document.getElementById('paypal-button');
        this.currentPlanId = null;
        
        this.init();
    }
    
    init() {
        // Configurar elemento de tarjeta
        this.card = this.elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#32325d',
                    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                    '::placeholder': {
                        color: '#aab7c4'
                    }
                },
                invalid: {
                    color: '#dc3545',
                    iconColor: '#dc3545'
                }
            }
        });
        
        this.card.mount('#card-element');
        
        // Manejar errores de validación
        this.card.addEventListener('change', (event) => {
            if (event.error) {
                this.showError(event.error.message);
            } else {
                this.showError('');
            }
        });
        
        // Manejar clic en botones de suscripción
        document.querySelectorAll('.subscribe-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                this.currentPlanId = e.target.dataset.planId;
                this.showModal();
            });
        });
        
        // Manejar cierre del modal
        document.querySelector('.modal-close').addEventListener('click', () => {
            this.hideModal();
        });
        
        // Manejar envío del formulario
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        
        // Manejar pago con PayPal
        this.paypalButton.addEventListener('click', () => this.handlePayPalPayment());
    }
    
    async handleSubmit(e) {
        e.preventDefault();
        
        this.submitButton.disabled = true;
        this.showProcessing();
        
        try {
            // Crear suscripción en el servidor
            const response = await fetch('/api/subscriptions/create.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    plan_id: this.currentPlanId
                })
            });
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Error al crear la suscripción');
            }
            
            // Confirmar pago con Stripe
            const result = await this.stripe.confirmCardPayment(data.client_secret, {
                payment_method: {
                    card: this.card,
                    billing_details: {
                        name: document.querySelector('[name="billing_name"]')?.value
                    }
                }
            });
            
            if (result.error) {
                throw new Error(result.error.message);
            }
            
            // Procesar pago en el servidor
            const processResponse = await fetch('/api/payments/process.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    transaction_id: data.transaction_id,
                    payment_method: 'stripe',
                    payment_id: result.paymentIntent.id
                })
            });
            
            const processData = await processResponse.json();
            
            if (!processData.success) {
                throw new Error(processData.error || 'Error al procesar el pago');
            }
            
            this.showSuccess();
            
        } catch (error) {
            this.showError(error.message);
            this.submitButton.disabled = false;
        }
    }
    
    async handlePayPalPayment() {
        try {
            // Crear suscripción en el servidor
            const response = await fetch('/api/subscriptions/create.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    plan_id: this.currentPlanId,
                    payment_method: 'paypal'
                })
            });
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Error al crear la suscripción');
            }
            
            // Redirigir a PayPal
            window.location.href = data.paypal_url;
            
        } catch (error) {
            this.showError(error.message);
        }
    }
    
    showModal() {
        this.modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    
    hideModal() {
        this.modal.style.display = 'none';
        document.body.style.overflow = '';
        this.resetForm();
    }
    
    showError(message) {
        this.errorDiv.textContent = message;
        if (message) {
            this.errorDiv.style.display = 'block';
        } else {
            this.errorDiv.style.display = 'none';
        }
    }
    
    showProcessing() {
        this.submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    }
    
    showSuccess() {
        this.form.style.display = 'none';
        document.getElementById('payment-success').style.display = 'block';
        setTimeout(() => {
            window.location.reload();
        }, 3000);
    }
    
    resetForm() {
        this.form.reset();
        this.card.clear();
        this.showError('');
        this.submitButton.disabled = false;
        this.submitButton.innerHTML = '<i class="fas fa-lock"></i> Pagar Ahora';
        this.form.style.display = 'block';
        document.getElementById('payment-success').style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', () => new PaymentHandler()); 
// Función para validar cupón
async function validateCoupon(button) {
    const form = button.closest('form');
    const input = form.querySelector('input[name="coupon_code"]');
    const planId = input.dataset.planId;
    const code = input.value.trim();
    
    if (!code) {
        showAlert('error', 'Ingresa un código de cupón');
        return;
    }
    
    try {
        const response = await fetch('/api/coupons/validate.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                code: code,
                plan_id: planId
            })
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error);
        }
        
        // Mostrar resumen de precios
        const summary = form.querySelector('.price-summary');
        const discount = summary.querySelector('.discount .value');
        const finalPrice = summary.querySelector('.final-price .value');
        
        discount.textContent = `-$${data.coupon.discount_amount.toFixed(2)}`;
        finalPrice.textContent = `$${data.coupon.final_price.toFixed(2)}`;
        
        summary.style.display = 'block';
        
        // Agregar ID del cupón al formulario
        const couponInput = document.createElement('input');
        couponInput.type = 'hidden';
        couponInput.name = 'coupon_id';
        couponInput.value = data.coupon.id;
        form.appendChild(couponInput);
        
        // Deshabilitar input y botón
        input.disabled = true;
        button.disabled = true;
        
        showAlert('success', 'Cupón aplicado correctamente');
        
    } catch (error) {
        showAlert('error', error.message);
    }
}

// Función para mostrar alertas
function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.querySelector('.plans-container').prepend(alertDiv);
    
    // Remover alerta después de 5 segundos
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
} 
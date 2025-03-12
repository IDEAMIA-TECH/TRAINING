// Inicializar botón de PayPal
paypal.Buttons({
    createOrder: function(data, actions) {
        // Crear orden en nuestro servidor
        return fetch('/api/payments/paypal.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'create_order',
                course_id: document.getElementById('course_id').value
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            return data.order_id;
        });
    },
    onApprove: function(data, actions) {
        // Mostrar mensaje de carga
        document.getElementById('loading').style.display = 'block';
        
        // Redirigir a la URL de captura
        window.location.href = '/api/payments/paypal.php?action=capture&token=' + data.orderID;
    },
    onError: function(err) {
        console.error('Error:', err);
        alert('Ocurrió un error al procesar el pago. Por favor intenta nuevamente.');
    }
}).render('#paypal-button-container'); 
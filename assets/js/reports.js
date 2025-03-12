// Configuración global de Chart.js
Chart.defaults.font.family = getComputedStyle(document.body).getPropertyValue('--font-family');
Chart.defaults.color = '#666';

// Inicializar gráficos cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    initRevenueChart();
    initEnrollmentsChart();
    initPaymentMethodsChart();
});

// Gráfico de ingresos por mes
async function initRevenueChart() {
    const response = await fetch('/api/reports/revenue.php' + window.location.search);
    const data = await response.json();
    
    const ctx = document.getElementById('revenueChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(item => {
                const date = new Date(item.month + '-01');
                return date.toLocaleDateString('es-MX', { month: 'short', year: 'numeric' });
            }),
            datasets: [{
                label: 'Ingresos',
                data: data.map(item => item.total),
                borderColor: '#4CAF50',
                backgroundColor: 'rgba(76, 175, 80, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return '$ ' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$ ' + value;
                        }
                    }
                }
            }
        }
    });
}

// Gráfico de inscripciones por curso
async function initEnrollmentsChart() {
    const response = await fetch('/api/reports/enrollments.php' + window.location.search);
    const data = await response.json();
    
    const ctx = document.getElementById('enrollmentsChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(item => item.title),
            datasets: [{
                data: data.map(item => item.total),
                backgroundColor: '#2196F3'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// Gráfico de métodos de pago
async function initPaymentMethodsChart() {
    const response = await fetch('/api/reports/payment-methods.php' + window.location.search);
    const data = await response.json();
    
    const ctx = document.getElementById('paymentMethodsChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.map(item => item.payment_method),
            datasets: [{
                data: data.map(item => item.total),
                backgroundColor: [
                    '#4CAF50',
                    '#2196F3',
                    '#FFC107',
                    '#9C27B0'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// Exportar a PDF
async function exportToPDF() {
    try {
        const response = await fetch('/api/reports/export-pdf.php' + window.location.search);
        const blob = await response.blob();
        
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'reporte.pdf';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    } catch (error) {
        console.error('Error al exportar PDF:', error);
        alert('Error al generar el PDF');
    }
}

// Exportar a Excel
async function exportToExcel() {
    try {
        const response = await fetch('/api/reports/export-excel.php' + window.location.search);
        const blob = await response.blob();
        
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'reporte.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    } catch (error) {
        console.error('Error al exportar Excel:', error);
        alert('Error al generar el Excel');
    }
} 
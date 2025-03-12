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

class ReportManager {
    constructor() {
        this.chart = null;
        this.reportType = document.getElementById('type').value;
        this.data = window.reportData || [];
        
        this.init();
    }
    
    init() {
        this.createChart();
        
        // Manejar cambios en los filtros
        document.querySelectorAll('.filters-form select, .filters-form input').forEach(input => {
            input.addEventListener('change', () => {
                if (input.id === 'type') return;
                document.querySelector('.filters-form').submit();
            });
        });
    }
    
    createChart() {
        const ctx = document.getElementById('report-chart');
        if (!ctx) return;
        
        switch (this.reportType) {
            case 'course_performance':
                this.createCoursePerformanceChart();
                break;
            case 'user_activity':
                this.createUserActivityChart();
                break;
            case 'enrollment_trends':
                this.createEnrollmentTrendsChart();
                break;
            case 'exam_statistics':
                this.createExamStatisticsChart();
                break;
        }
    }
    
    createCoursePerformanceChart() {
        const labels = this.data.map(item => item.course_name);
        const datasets = [
            {
                label: 'Estudiantes',
                data: this.data.map(item => item.total_students),
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgb(54, 162, 235)',
                borderWidth: 1
            },
            {
                label: 'Tasa de Completitud (%)',
                data: this.data.map(item => item.completion_rate),
                backgroundColor: 'rgba(75, 192, 192, 0.5)',
                borderColor: 'rgb(75, 192, 192)',
                borderWidth: 1
            }
        ];
        
        this.chart = new Chart(ctx, {
            type: 'bar',
            data: { labels, datasets },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
    
    createUserActivityChart() {
        const labels = this.data.map(item => item.date);
        const datasets = [
            {
                label: 'Usuarios Activos',
                data: this.data.map(item => item.active_users),
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgb(54, 162, 235)',
                borderWidth: 1,
                fill: true
            }
        ];
        
        this.chart = new Chart(ctx, {
            type: 'line',
            data: { labels, datasets },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
    
    createEnrollmentTrendsChart() {
        const labels = this.data.map(item => item.period);
        const datasets = [
            {
                label: 'Inscripciones',
                data: this.data.map(item => item.total_enrollments),
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgb(54, 162, 235)',
                borderWidth: 1,
                fill: true
            }
        ];
        
        this.chart = new Chart(ctx, {
            type: 'line',
            data: { labels, datasets },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
    
    createExamStatisticsChart() {
        const labels = this.data.map(item => item.exam_title);
        const datasets = [
            {
                label: 'Aprobados',
                data: this.data.map(item => item.passed_count),
                backgroundColor: 'rgba(75, 192, 192, 0.5)',
                borderColor: 'rgb(75, 192, 192)',
                borderWidth: 1
            },
            {
                label: 'Reprobados',
                data: this.data.map(item => item.failed_count),
                backgroundColor: 'rgba(255, 99, 132, 0.5)',
                borderColor: 'rgb(255, 99, 132)',
                borderWidth: 1
            }
        ];
        
        this.chart = new Chart(ctx, {
            type: 'bar',
            data: { labels, datasets },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
}

function exportReport() {
    const table = document.querySelector('.report-table table');
    if (!table) return;
    
    const rows = Array.from(table.querySelectorAll('tr'));
    let csv = [];
    
    rows.forEach(row => {
        const cells = Array.from(row.querySelectorAll('th, td'));
        const rowData = cells.map(cell => {
            let text = cell.textContent.trim();
            // Escapar comillas y encerrar en comillas si contiene comas
            if (text.includes(',') || text.includes('"')) {
                text = `"${text.replace(/"/g, '""')}"`;
            }
            return text;
        });
        csv.push(rowData.join(','));
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', 'reporte.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

document.addEventListener('DOMContentLoaded', () => new ReportManager()); 
<?php
require_once '../../includes/init.php';

if (!$is_admin) {
    redirect('/login.php');
}

try {
    // Obtener estadísticas generales
    $stats = [
        'total_students' => $db->query("SELECT COUNT(DISTINCT user_id) FROM course_registrations")->fetchColumn(),
        'total_courses' => $db->query("SELECT COUNT(*) FROM courses")->fetchColumn(),
        'total_revenue' => $db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed'")->fetchColumn(),
        'active_courses' => $db->query("SELECT COUNT(*) FROM courses WHERE status = 'active' AND start_date > CURRENT_TIMESTAMP")->fetchColumn()
    ];

    // Obtener ingresos por mes (últimos 12 meses)
    $stmt = $db->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
               COUNT(*) as total_payments,
               SUM(amount) as revenue
        FROM payments
        WHERE status = 'completed'
        AND created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ");
    $stmt->execute();
    $monthly_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener cursos más populares
    $stmt = $db->prepare("
        SELECT c.title,
               COUNT(cr.id) as registrations,
               SUM(CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END) as revenue
        FROM courses c
        LEFT JOIN course_registrations cr ON c.id = cr.course_id
        LEFT JOIN payments p ON cr.payment_id = p.id
        GROUP BY c.id
        ORDER BY registrations DESC
        LIMIT 10
    ");
    $stmt->execute();
    $popular_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener métodos de pago más utilizados
    $stmt = $db->prepare("
        SELECT payment_method,
               COUNT(*) as total_payments,
               SUM(amount) as total_amount
        FROM payments
        WHERE status = 'completed'
        GROUP BY payment_method
        ORDER BY total_payments DESC
    ");
    $stmt->execute();
    $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<?php require_once '../../templates/admin/header.php'; ?>

<div class="container-fluid py-4">
    <h2 class="mb-4">Panel de Reportes y Estadísticas</h2>

    <!-- Botones de exportación -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Exportar Datos</h5>
        </div>
        <div class="card-body">
            <form action="../export.php" method="GET" class="row g-3">
                <div class="col-md-3">
                    <select name="type" class="form-select" required>
                        <option value="">Seleccionar datos...</option>
                        <option value="users">Usuarios</option>
                        <option value="courses">Cursos</option>
                        <option value="payments">Pagos</option>
                        <option value="registrations">Inscripciones</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="format" class="form-select">
                        <option value="excel">Excel</option>
                        <option value="csv">CSV</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control" placeholder="Fecha desde">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control" placeholder="Fecha hasta">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-download"></i> Exportar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Resumen de Estadísticas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Estudiantes Totales</h5>
                    <h2 class="mb-0"><?php echo number_format($stats['total_students']); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Ingresos Totales</h5>
                    <h2 class="mb-0">$<?php echo number_format($stats['total_revenue'], 2); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Cursos Totales</h5>
                    <h2 class="mb-0"><?php echo number_format($stats['total_courses']); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Cursos Activos</h5>
                    <h2 class="mb-0"><?php echo number_format($stats['active_courses']); ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Gráfico de Ingresos Mensuales -->
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Ingresos Mensuales</h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Métodos de Pago -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Métodos de Pago</h5>
                </div>
                <div class="card-body">
                    <canvas id="paymentMethodsChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Cursos Más Populares -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Cursos Más Populares</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Curso</th>
                            <th>Inscripciones</th>
                            <th>Ingresos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($popular_courses as $course): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($course['title']); ?></td>
                                <td><?php echo number_format($course['registrations']); ?></td>
                                <td>$<?php echo number_format($course['revenue'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Datos para el gráfico de ingresos
const monthlyData = <?php echo json_encode($monthly_revenue); ?>;
const months = monthlyData.map(item => item.month);
const revenue = monthlyData.map(item => item.revenue);

// Gráfico de ingresos mensuales
new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
        labels: months,
        datasets: [{
            label: 'Ingresos Mensuales',
            data: revenue,
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        }
    }
});

// Datos para el gráfico de métodos de pago
const paymentData = <?php echo json_encode($payment_methods); ?>;
new Chart(document.getElementById('paymentMethodsChart'), {
    type: 'doughnut',
    data: {
        labels: paymentData.map(item => item.payment_method.toUpperCase()),
        datasets: [{
            data: paymentData.map(item => item.total_payments),
            backgroundColor: [
                'rgb(255, 99, 132)',
                'rgb(54, 162, 235)',
                'rgb(255, 205, 86)'
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
</script>

<?php require_once '../../templates/admin/footer.php'; ?> 
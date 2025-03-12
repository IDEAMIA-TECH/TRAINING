<?php
require_once '../../includes/header.php';
require_once '../../includes/Reports.php';

if (!is_admin()) {
    header("Location: ../../login.php");
    exit();
}

$reports = new Reports($conn);
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
?>

<div class="admin-container">
    <?php require_once '../sidebar.php'; ?>
    
    <div class="admin-content">
        <div class="reports-container">
            <div class="reports-header">
                <h2>Reportes y Estadísticas</h2>
                
                <div class="date-filters">
                    <form method="GET" class="filter-form">
                        <div class="form-group">
                            <label>Desde:</label>
                            <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="form-group">
                            <label>Hasta:</label>
                            <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                    </form>
                </div>
            </div>
            
            <!-- Resumen General -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Ingresos Totales</h3>
                    <div class="stat-number">
                        $<?php echo number_format($reports->getTotalRevenue($start_date, $end_date), 2); ?>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3>Nuevas Inscripciones</h3>
                    <div class="stat-number">
                        <?php echo $reports->getNewEnrollments($start_date, $end_date); ?>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3>Cursos Activos</h3>
                    <div class="stat-number">
                        <?php echo $reports->getActiveCourses($start_date, $end_date); ?>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3>Tasa de Conversión</h3>
                    <div class="stat-number">
                        <?php echo number_format($reports->getConversionRate($start_date, $end_date), 1); ?>%
                    </div>
                </div>
            </div>
            
            <!-- Gráficos -->
            <div class="charts-grid">
                <div class="chart-card">
                    <h3>Ingresos por Mes</h3>
                    <canvas id="revenueChart"></canvas>
                </div>
                
                <div class="chart-card">
                    <h3>Inscripciones por Curso</h3>
                    <canvas id="enrollmentsChart"></canvas>
                </div>
                
                <div class="chart-card">
                    <h3>Métodos de Pago</h3>
                    <canvas id="paymentMethodsChart"></canvas>
                </div>
            </div>
            
            <!-- Tabla de Transacciones -->
            <div class="transactions-table">
                <h3>Últimas Transacciones</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Usuario</th>
                                <th>Curso</th>
                                <th>Monto</th>
                                <th>Método</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports->getRecentTransactions() as $transaction): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($transaction['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['user_name']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['course_title']); ?></td>
                                    <td>$<?php echo number_format($transaction['amount'], 2); ?></td>
                                    <td><?php echo ucfirst($transaction['payment_method']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $transaction['status']; ?>">
                                            <?php echo ucfirst($transaction['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Acciones de Exportación -->
            <div class="export-actions">
                <button onclick="exportToPDF()" class="btn btn-secondary">
                    <i class="fas fa-file-pdf"></i> Exportar a PDF
                </button>
                <button onclick="exportToExcel()" class="btn btn-secondary">
                    <i class="fas fa-file-excel"></i> Exportar a Excel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/reports.js"></script>

<?php require_once '../../includes/footer.php'; ?> 
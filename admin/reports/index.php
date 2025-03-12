<?php
require_once '../../includes/header.php';
require_once '../../includes/ReportManager.php';

if (!has_permission('view_reports')) {
    header("Location: ../../login.php");
    exit();
}

$report_manager = new ReportManager($conn);

// Obtener cursos para el filtro
$stmt = $conn->prepare("SELECT id, title FROM courses ORDER BY title");
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener tipo de reporte y parámetros
$report_type = $_GET['type'] ?? 'course_performance';
$parameters = [
    'course_id' => $_GET['course_id'] ?? null,
    'start_date' => $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days')),
    'end_date' => $_GET['end_date'] ?? date('Y-m-d'),
    'period' => $_GET['period'] ?? 'monthly'
];

try {
    $report = $report_manager->generateReport($report_type, $parameters);
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<div class="admin-container">
    <?php require_once '../sidebar.php'; ?>
    
    <div class="admin-content">
        <div class="reports-container">
            <div class="reports-header">
                <h2>Reportes y Estadísticas</h2>
                
                <div class="report-filters">
                    <form method="GET" class="filters-form">
                        <div class="form-group">
                            <label for="type">Tipo de Reporte</label>
                            <select id="type" name="type" onchange="this.form.submit()">
                                <option value="course_performance" <?php echo $report_type === 'course_performance' ? 'selected' : ''; ?>>
                                    Rendimiento de Cursos
                                </option>
                                <option value="user_activity" <?php echo $report_type === 'user_activity' ? 'selected' : ''; ?>>
                                    Actividad de Usuarios
                                </option>
                                <option value="enrollment_trends" <?php echo $report_type === 'enrollment_trends' ? 'selected' : ''; ?>>
                                    Tendencias de Inscripción
                                </option>
                                <option value="exam_statistics" <?php echo $report_type === 'exam_statistics' ? 'selected' : ''; ?>>
                                    Estadísticas de Exámenes
                                </option>
                            </select>
                        </div>
                        
                        <?php if (in_array($report_type, ['course_performance', 'exam_statistics'])): ?>
                            <div class="form-group">
                                <label for="course_id">Curso</label>
                                <select id="course_id" name="course_id">
                                    <option value="">Todos los cursos</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>"
                                                <?php echo $parameters['course_id'] == $course['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($course['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($report_type === 'enrollment_trends'): ?>
                            <div class="form-group">
                                <label for="period">Período</label>
                                <select id="period" name="period">
                                    <option value="daily" <?php echo $parameters['period'] === 'daily' ? 'selected' : ''; ?>>
                                        Diario
                                    </option>
                                    <option value="weekly" <?php echo $parameters['period'] === 'weekly' ? 'selected' : ''; ?>>
                                        Semanal
                                    </option>
                                    <option value="monthly" <?php echo $parameters['period'] === 'monthly' ? 'selected' : ''; ?>>
                                        Mensual
                                    </option>
                                </select>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (in_array($report_type, ['course_performance', 'user_activity'])): ?>
                            <div class="form-group">
                                <label for="start_date">Fecha Inicio</label>
                                <input type="date" id="start_date" name="start_date" 
                                       value="<?php echo $parameters['start_date']; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="end_date">Fecha Fin</label>
                                <input type="date" id="end_date" name="end_date" 
                                       value="<?php echo $parameters['end_date']; ?>">
                            </div>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filtrar
                        </button>
                    </form>
                </div>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php else: ?>
                <div class="report-content">
                    <div class="report-header">
                        <h3><?php echo $report['title']; ?></h3>
                        <button onclick="exportReport()" class="btn btn-secondary">
                            <i class="fas fa-download"></i> Exportar
                        </button>
                    </div>
                    
                    <div id="report-chart"></div>
                    
                    <div class="report-table">
                        <?php require_once "tables/{$report_type}.php"; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/reports.js"></script>

<?php require_once '../../includes/footer.php'; ?> 
<?php
class ReportManager {
    private $conn;
    private $outputPath;
    
    public function __construct($conn, $config = []) {
        $this->conn = $conn;
        $this->outputPath = $config['output_path'] ?? 'reports/';
    }
    
    public function generateReport($reportId, $parameters = [], $userId = null, $format = 'csv') {
        // Registrar ejecución
        $executionId = $this->createExecution($reportId, $userId, $parameters);
        
        try {
            // Obtener definición del reporte
            $report = $this->getReport($reportId);
            if (!$report) {
                throw new Exception("Reporte no encontrado");
            }
            
            // Validar parámetros
            $this->validateParameters($report['parameters'], $parameters);
            
            // Preparar y ejecutar consulta
            $stmt = $this->conn->prepare($report['query']);
            $stmt->execute($parameters);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Generar archivo
            $filename = $this->generateFilename($report['name'], $format);
            $filePath = $this->outputPath . $filename;
            
            switch ($format) {
                case 'csv':
                    $this->generateCSV($data, $filePath);
                    break;
                case 'excel':
                    $this->generateExcel($data, $filePath);
                    break;
                case 'pdf':
                    $this->generatePDF($data, $filePath, $report);
                    break;
                case 'html':
                    $this->generateHTML($data, $filePath, $report);
                    break;
                default:
                    throw new Exception("Formato no soportado");
            }
            
            // Actualizar ejecución como completada
            $this->updateExecution($executionId, 'completed', $filename);
            
            return $filename;
            
        } catch (Exception $e) {
            // Registrar error
            $this->updateExecution($executionId, 'failed', null, $e->getMessage());
            throw $e;
        }
    }
    
    public function scheduleReport($reportId, $schedule) {
        $stmt = $this->conn->prepare("
            UPDATE reports
            SET schedule = ?,
                next_run = ?
            WHERE id = ?
        ");
        
        $nextRun = $this->calculateNextRun($schedule);
        return $stmt->execute([$schedule, $nextRun, $reportId]);
    }
    
    public function runScheduledReports() {
        $stmt = $this->conn->prepare("
            SELECT id, parameters, format
            FROM reports
            WHERE is_active = TRUE
            AND schedule IS NOT NULL
            AND next_run <= NOW()
        ");
        
        $stmt->execute();
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($reports as $report) {
            try {
                $this->generateReport(
                    $report['id'],
                    json_decode($report['parameters'], true),
                    null,
                    $report['format']
                );
                
                // Actualizar próxima ejecución
                $this->updateNextRun($report['id'], $report['schedule']);
                
            } catch (Exception $e) {
                // Log error pero continuar con el siguiente
                error_log("Error en reporte programado: " . $e->getMessage());
            }
        }
    }
    
    private function generateCSV($data, $filePath) {
        if (empty($data)) {
            throw new Exception("No hay datos para generar el reporte");
        }
        
        $fp = fopen($filePath, 'w');
        
        // Escribir encabezados
        fputcsv($fp, array_keys($data[0]));
        
        // Escribir datos
        foreach ($data as $row) {
            fputcsv($fp, $row);
        }
        
        fclose($fp);
    }
    
    private function generateExcel($data, $filePath) {
        // Implementar generación de Excel
        // Requiere librería como PhpSpreadsheet
    }
    
    private function generatePDF($data, $filePath, $report) {
        // Implementar generación de PDF
        // Requiere librería como TCPDF o FPDF
    }
    
    private function generateHTML($data, $filePath, $report) {
        $html = '<html><head><style>';
        $html .= 'table {border-collapse: collapse; width: 100%;}';
        $html .= 'th, td {border: 1px solid #ddd; padding: 8px; text-align: left;}';
        $html .= 'th {background-color: #f2f2f2;}';
        $html .= '</style></head><body>';
        
        $html .= "<h1>{$report['name']}</h1>";
        if ($report['description']) {
            $html .= "<p>{$report['description']}</p>";
        }
        
        $html .= '<table><tr>';
        foreach (array_keys($data[0]) as $header) {
            $html .= "<th>$header</th>";
        }
        $html .= '</tr>';
        
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $value) {
                $html .= "<td>$value</td>";
            }
            $html .= '</tr>';
        }
        
        $html .= '</table></body></html>';
        
        file_put_contents($filePath, $html);
    }
    
    private function createExecution($reportId, $userId, $parameters) {
        $stmt = $this->conn->prepare("
            INSERT INTO report_executions (
                report_id, user_id, parameters,
                status, started_at
            ) VALUES (?, ?, ?, 'processing', NOW())
        ");
        
        $stmt->execute([
            $reportId,
            $userId,
            json_encode($parameters)
        ]);
        
        return $this->conn->lastInsertId();
    }
    
    private function updateExecution($executionId, $status, $filePath = null, $errorMessage = null) {
        $stmt = $this->conn->prepare("
            UPDATE report_executions
            SET status = ?,
                file_path = ?,
                error_message = ?,
                completed_at = NOW()
            WHERE id = ?
        ");
        
        return $stmt->execute([
            $status,
            $filePath,
            $errorMessage,
            $executionId
        ]);
    }
    
    private function validateParameters($required, $provided) {
        $required = json_decode($required, true);
        
        foreach ($required as $param => $config) {
            if ($config['required'] && !isset($provided[$param])) {
                throw new Exception("Falta el parámetro requerido: {$config['label']}");
            }
        }
    }
    
    private function generateFilename($reportName, $format) {
        $basename = strtolower(str_replace(' ', '_', $reportName));
        return sprintf(
            '%s_%s.%s',
            $basename,
            date('Y-m-d_His'),
            $format
        );
    }
    
    private function calculateNextRun($schedule) {
        // Implementar lógica para calcular próxima ejecución
        // basado en el formato del schedule (cron, daily, weekly, etc.)
        return date('Y-m-d H:i:s', strtotime('+1 day'));
    }
} 
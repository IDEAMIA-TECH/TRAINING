<?php
require_once '../../includes/header.php';
require_once '../../includes/Reports.php';
require_once '../../vendor/autoload.php'; // Requiere PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!is_admin()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-t');
    
    $reports = new Reports($conn);
    
    // Crear nuevo documento Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Título
    $sheet->setCellValue('A1', 'Reporte de Actividad');
    $sheet->mergeCells('A1:E1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    
    // Período
    $sheet->setCellValue('A3', 'Período:');
    $sheet->setCellValue('B3', date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date)));
    
    // Resumen
    $sheet->setCellValue('A5', 'Resumen General');
    $sheet->getStyle('A5')->getFont()->setBold(true);
    
    $sheet->setCellValue('A6', 'Ingresos Totales:');
    $sheet->setCellValue('B6', $reports->getTotalRevenue($start_date, $end_date));
    
    $sheet->setCellValue('A7', 'Nuevas Inscripciones:');
    $sheet->setCellValue('B7', $reports->getNewEnrollments($start_date, $end_date));
    
    $sheet->setCellValue('A8', 'Cursos Activos:');
    $sheet->setCellValue('B8', $reports->getActiveCourses($start_date, $end_date));
    
    // Transacciones
    $sheet->setCellValue('A10', 'Transacciones Recientes');
    $sheet->getStyle('A10')->getFont()->setBold(true);
    
    // Cabecera de tabla
    $sheet->setCellValue('A11', 'Fecha');
    $sheet->setCellValue('B11', 'Usuario');
    $sheet->setCellValue('C11', 'Curso');
    $sheet->setCellValue('D11', 'Monto');
    $sheet->setCellValue('E11', 'Estado');
    
    // Datos
    $row = 12;
    foreach ($reports->getRecentTransactions(50) as $transaction) {
        $sheet->setCellValue('A' . $row, date('d/m/Y', strtotime($transaction['created_at'])));
        $sheet->setCellValue('B' . $row, $transaction['user_name']);
        $sheet->setCellValue('C' . $row, $transaction['course_title']);
        $sheet->setCellValue('D' . $row, $transaction['amount']);
        $sheet->setCellValue('E' . $row, ucfirst($transaction['status']));
        $row++;
    }
    
    // Formato de columnas
    foreach (range('A', 'E') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Crear archivo
    $writer = new Xlsx($spreadsheet);
    
    // Enviar archivo
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="reporte.xlsx"');
    $writer->save('php://output');
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
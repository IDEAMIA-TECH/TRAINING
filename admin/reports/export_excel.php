<?php
require_once '../../includes/init.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!$is_admin) {
    redirect('/login.php');
}

try {
    // Obtener datos para el reporte
    $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_GET['end_date'] ?? date('Y-m-d');

    // Consulta de datos
    $stmt = $db->prepare("
        SELECT 
            c.title,
            c.start_date,
            c.capacity,
            COUNT(cr.id) as registrations,
            SUM(CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END) as revenue,
            COUNT(CASE WHEN p.status = 'completed' THEN 1 END) as completed_payments,
            COUNT(CASE WHEN p.status = 'pending' THEN 1 END) as pending_payments
        FROM courses c
        LEFT JOIN course_registrations cr ON c.id = cr.course_id
        LEFT JOIN payments p ON cr.payment_id = p.id
        WHERE p.created_at BETWEEN ? AND ?
        GROUP BY c.id
        ORDER BY c.start_date DESC
    ");
    $stmt->execute([$start_date, $end_date . ' 23:59:59']);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Crear nuevo documento Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Establecer títulos de columnas
    $sheet->setCellValue('A1', 'Curso');
    $sheet->setCellValue('B1', 'Fecha Inicio');
    $sheet->setCellValue('C1', 'Capacidad');
    $sheet->setCellValue('D1', 'Inscripciones');
    $sheet->setCellValue('E1', 'Pagos Completados');
    $sheet->setCellValue('F1', 'Pagos Pendientes');
    $sheet->setCellValue('G1', 'Ingresos');

    // Estilo para los títulos
    $sheet->getStyle('A1:G1')->getFont()->setBold(true);
    $sheet->getStyle('A1:G1')->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFCCCCCC');

    // Llenar datos
    $row = 2;
    foreach ($courses as $course) {
        $sheet->setCellValue('A' . $row, $course['title']);
        $sheet->setCellValue('B' . $row, date('d/m/Y', strtotime($course['start_date'])));
        $sheet->setCellValue('C' . $row, $course['capacity']);
        $sheet->setCellValue('D' . $row, $course['registrations']);
        $sheet->setCellValue('E' . $row, $course['completed_payments']);
        $sheet->setCellValue('F' . $row, $course['pending_payments']);
        $sheet->setCellValue('G' . $row, $course['revenue']);

        // Formato para la columna de ingresos
        $sheet->getStyle('G' . $row)->getNumberFormat()
            ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);

        $row++;
    }

    // Ajustar ancho de columnas
    foreach(range('A','G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Crear archivo
    $writer = new Xlsx($spreadsheet);
    $filename = 'reporte_cursos_' . date('Y-m-d') . '.xlsx';

    // Configurar headers
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    // Enviar archivo al navegador
    $writer->save('php://output');

} catch (Exception $e) {
    die('Error al generar el reporte: ' . $e->getMessage());
} 
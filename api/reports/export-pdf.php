<?php
require_once '../../includes/header.php';
require_once '../../includes/Reports.php';
require_once '../../vendor/autoload.php'; // Requiere TCPDF

if (!is_admin()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-t');
    
    $reports = new Reports($conn);
    
    // Crear PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Configurar documento
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Sistema de Entrenamientos');
    $pdf->SetTitle('Reporte de Actividad');
    
    // Configurar márgenes
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Agregar página
    $pdf->AddPage();
    
    // Título
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->Cell(0, 10, 'Reporte de Actividad', 0, 1, 'C');
    $pdf->Ln(10);
    
    // Período
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Período: ' . date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date)), 0, 1, 'L');
    $pdf->Ln(5);
    
    // Resumen
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Resumen General', 0, 1, 'L');
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(100, 10, 'Ingresos Totales:', 0, 0, 'L');
    $pdf->Cell(0, 10, '$' . number_format($reports->getTotalRevenue($start_date, $end_date), 2), 0, 1, 'L');
    
    $pdf->Cell(100, 10, 'Nuevas Inscripciones:', 0, 0, 'L');
    $pdf->Cell(0, 10, $reports->getNewEnrollments($start_date, $end_date), 0, 1, 'L');
    
    $pdf->Cell(100, 10, 'Cursos Activos:', 0, 0, 'L');
    $pdf->Cell(0, 10, $reports->getActiveCourses($start_date, $end_date), 0, 1, 'L');
    
    // Transacciones Recientes
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Transacciones Recientes', 0, 1, 'L');
    
    $pdf->SetFont('helvetica', '', 10);
    
    // Cabecera de tabla
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(35, 7, 'Fecha', 1, 0, 'C', true);
    $pdf->Cell(50, 7, 'Usuario', 1, 0, 'C', true);
    $pdf->Cell(50, 7, 'Curso', 1, 0, 'C', true);
    $pdf->Cell(30, 7, 'Monto', 1, 0, 'C', true);
    $pdf->Cell(25, 7, 'Estado', 1, 1, 'C', true);
    
    // Datos de la tabla
    foreach ($reports->getRecentTransactions(20) as $transaction) {
        $pdf->Cell(35, 6, date('d/m/Y', strtotime($transaction['created_at'])), 1, 0, 'L');
        $pdf->Cell(50, 6, $transaction['user_name'], 1, 0, 'L');
        $pdf->Cell(50, 6, $transaction['course_title'], 1, 0, 'L');
        $pdf->Cell(30, 6, '$' . number_format($transaction['amount'], 2), 1, 0, 'R');
        $pdf->Cell(25, 6, ucfirst($transaction['status']), 1, 1, 'C');
    }
    
    // Enviar PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="reporte.pdf"');
    echo $pdf->Output('reporte.pdf', 'S');
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
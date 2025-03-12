<?php
require_once '../../includes/init.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!$is_admin) {
    redirect('/login.php');
}

try {
    // Obtener datos para el reporte
    $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_GET['end_date'] ?? date('Y-m-d');

    // Consulta de datos
    $stmt = $db->prepare("
        SELECT c.title, 
               COUNT(cr.id) as registrations,
               SUM(CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END) as revenue
        FROM courses c
        LEFT JOIN course_registrations cr ON c.id = cr.course_id
        LEFT JOIN payments p ON cr.payment_id = p.id
        WHERE p.created_at BETWEEN ? AND ?
        GROUP BY c.id
        ORDER BY revenue DESC
    ");
    $stmt->execute([$start_date, $end_date . ' 23:59:59']);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Configurar DOMPDF
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', true);

    $dompdf = new Dompdf($options);

    // Generar contenido HTML
    $html = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f4f4f4; }
            h1 { color: #333; }
            .total { font-weight: bold; margin-top: 20px; }
        </style>
    </head>
    <body>
        <h1>Reporte de Cursos</h1>
        <p>Período: ' . date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date)) . '</p>
        
        <table>
            <thead>
                <tr>
                    <th>Curso</th>
                    <th>Inscripciones</th>
                    <th>Ingresos</th>
                </tr>
            </thead>
            <tbody>';

    $total_revenue = 0;
    $total_registrations = 0;

    foreach ($courses as $course) {
        $html .= '<tr>
            <td>' . htmlspecialchars($course['title']) . '</td>
            <td>' . number_format($course['registrations']) . '</td>
            <td>$' . number_format($course['revenue'], 2) . '</td>
        </tr>';
        
        $total_revenue += $course['revenue'];
        $total_registrations += $course['registrations'];
    }

    $html .= '</tbody></table>
        <div class="total">
            <p>Total de Inscripciones: ' . number_format($total_registrations) . '</p>
            <p>Total de Ingresos: $' . number_format($total_revenue, 2) . '</p>
        </div>
    </body>
    </html>';

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Generar nombre del archivo
    $filename = 'reporte_cursos_' . date('Y-m-d') . '.pdf';

    // Enviar al navegador
    $dompdf->stream($filename, ['Attachment' => true]);

} catch (Exception $e) {
    die('Error al generar el reporte: ' . $e->getMessage());
} 
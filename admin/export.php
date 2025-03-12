<?php
require_once '../includes/init.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

if (!$is_admin) {
    redirect('/login.php');
}

try {
    $type = $_GET['type'] ?? '';
    $format = $_GET['format'] ?? 'excel';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';

    // Validar tipo de exportación
    if (!in_array($type, ['users', 'courses', 'payments', 'registrations'])) {
        throw new Exception('Tipo de exportación no válido');
    }

    // Validar formato
    if (!in_array($format, ['excel', 'csv'])) {
        throw new Exception('Formato no válido');
    }

    // Preparar consulta según el tipo
    switch ($type) {
        case 'users':
            $query = "
                SELECT u.name, u.email, u.phone, u.status,
                       COUNT(cr.id) as total_registrations,
                       u.created_at
                FROM users u
                LEFT JOIN course_registrations cr ON u.id = cr.user_id
                WHERE u.role = 'client'
                GROUP BY u.id
                ORDER BY u.created_at DESC
            ";
            $headers = ['Nombre', 'Email', 'Teléfono', 'Estado', 'Total Inscripciones', 'Fecha Registro'];
            break;

        case 'courses':
            $query = "
                SELECT c.title, c.description, c.start_date, c.end_date,
                       c.capacity, c.price, c.status,
                       COUNT(cr.id) as total_registrations,
                       cat.name as category
                FROM courses c
                LEFT JOIN course_registrations cr ON c.id = cr.course_id
                LEFT JOIN categories cat ON c.category_id = cat.id
                GROUP BY c.id
                ORDER BY c.start_date DESC
            ";
            $headers = ['Título', 'Descripción', 'Fecha Inicio', 'Fecha Fin', 'Capacidad', 'Precio', 'Estado', 'Total Inscripciones', 'Categoría'];
            break;

        case 'payments':
            $query = "
                SELECT u.name as user_name, c.title as course_title,
                       p.amount, p.payment_method, p.transaction_id,
                       p.status, p.created_at
                FROM payments p
                JOIN users u ON p.user_id = u.id
                JOIN courses c ON p.course_id = c.id
                ORDER BY p.created_at DESC
            ";
            $headers = ['Usuario', 'Curso', 'Monto', 'Método de Pago', 'ID Transacción', 'Estado', 'Fecha'];
            break;

        case 'registrations':
            $query = "
                SELECT u.name as user_name, c.title as course_title,
                       cr.status, cr.created_at,
                       COALESCE(p.amount, 0) as amount,
                       COALESCE(p.status, 'pending') as payment_status
                FROM course_registrations cr
                JOIN users u ON cr.user_id = u.id
                JOIN courses c ON cr.course_id = c.id
                LEFT JOIN payments p ON cr.payment_id = p.id
                ORDER BY cr.created_at DESC
            ";
            $headers = ['Usuario', 'Curso', 'Estado', 'Fecha Registro', 'Monto', 'Estado Pago'];
            break;
    }

    // Aplicar filtros de fecha si existen
    if ($date_from && $date_to) {
        $query = preg_replace('/ORDER BY/', "WHERE created_at BETWEEN '$date_from' AND '$date_to' ORDER BY", $query);
    }

    // Obtener datos
    $stmt = $db->query($query);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Crear documento
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Agregar encabezados
    foreach ($headers as $col => $header) {
        $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
    }

    // Agregar datos
    foreach ($data as $row => $record) {
        foreach (array_values($record) as $col => $value) {
            $sheet->setCellValueByColumnAndRow($col + 1, $row + 2, $value);
        }
    }

    // Autoajustar columnas
    foreach (range('A', $sheet->getHighestColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Configurar encabezados HTTP
    $filename = $type . '_' . date('Y-m-d');
    
    if ($format === 'excel') {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        $writer = new Xlsx($spreadsheet);
    } else {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename="' . $filename . '.csv"');
        $writer = new Csv($spreadsheet);
    }

    // Enviar archivo
    $writer->save('php://output');

} catch (Exception $e) {
    error_log("Error en exportación: " . $e->getMessage());
    redirect('/admin/reports?error=' . urlencode('Error al exportar datos: ' . $e->getMessage()));
} 
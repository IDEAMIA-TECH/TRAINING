<?php
require_once '../../includes/header.php';
require_once '../../includes/Reports.php';

if (!is_admin()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-t');
    
    $reports = new Reports($conn);
    $data = $reports->getPaymentMethods($start_date, $end_date);
    
    echo json_encode($data);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
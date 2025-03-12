<?php
require_once '../includes/header.php';
require_once '../includes/CertificateManager.php';

if (!isset($_GET['id'])) {
    header('HTTP/1.0 404 Not Found');
    exit('Certificado no especificado');
}

try {
    $certificate_manager = new CertificateManager($conn);
    $pdf = $certificate_manager->generatePDF($_GET['id']);
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="certificado.pdf"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    echo $pdf;
    
} catch (Exception $e) {
    header('HTTP/1.0 404 Not Found');
    exit($e->getMessage());
} 
<?php
require_once '../includes/header.php';
require_once '../vendor/autoload.php';

if (!is_logged_in()) {
    header("Location: ../login.php");
    exit();
}

if (empty($_GET['id'])) {
    header("Location: billing.php");
    exit();
}

try {
    // Obtener información de la transacción y factura
    $stmt = $conn->prepare("
        SELECT 
            t.*, i.*, 
            u.name, u.email,
            p.name as plan_name
        FROM transactions t
        JOIN invoices i ON t.id = i.transaction_id
        JOIN users u ON t.user_id = u.id
        LEFT JOIN subscriptions s ON t.subscription_id = s.id
        LEFT JOIN subscription_plans p ON s.plan_id = p.id
        WHERE t.id = ? AND t.user_id = ?
    ");
    $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        throw new Exception("Factura no encontrada");
    }
    
    // Generar PDF
    $mpdf = new \Mpdf\Mpdf([
        'margin_left' => 20,
        'margin_right' => 20,
        'margin_top' => 20,
        'margin_bottom' => 20
    ]);
    
    // Contenido del PDF
    $html = '
    <style>
        body { font-family: Arial, sans-serif; }
        .header { text-align: center; margin-bottom: 30px; }
        .logo { max-width: 200px; }
        .invoice-info { margin-bottom: 30px; }
        .invoice-details { margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .total { font-weight: bold; }
    </style>
    
    <div class="header">
        <img src="' . BASE_URL . '/assets/img/logo.png" class="logo">
        <h1>FACTURA</h1>
    </div>
    
    <div class="invoice-info">
        <p><strong>Número de Factura:</strong> ' . $invoice['invoice_number'] . '</p>
        <p><strong>Fecha:</strong> ' . date('d/m/Y', strtotime($invoice['created_at'])) . '</p>
    </div>
    
    <div class="invoice-details">
        <h3>Detalles de Facturación</h3>
        <p><strong>Nombre:</strong> ' . htmlspecialchars($invoice['billing_name']) . '</p>
        <p><strong>Email:</strong> ' . htmlspecialchars($invoice['billing_email']) . '</p>
        ' . ($invoice['billing_address'] ? '<p><strong>Dirección:</strong> ' . htmlspecialchars($invoice['billing_address']) . '</p>' : '') . '
        ' . ($invoice['tax_id'] ? '<p><strong>ID Fiscal:</strong> ' . htmlspecialchars($invoice['tax_id']) . '</p>' : '') . '
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Descripción</th>
                <th>Cantidad</th>
                <th>Precio</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>' . ($invoice['plan_name'] ?? 'Suscripción') . '</td>
                <td>1</td>
                <td>$' . number_format($invoice['subtotal'], 2) . '</td>
                <td>$' . number_format($invoice['subtotal'], 2) . '</td>
            </tr>
            <tr>
                <td colspan="3" style="text-align: right;"><strong>Subtotal:</strong></td>
                <td>$' . number_format($invoice['subtotal'], 2) . '</td>
            </tr>
            <tr>
                <td colspan="3" style="text-align: right;"><strong>IVA (16%):</strong></td>
                <td>$' . number_format($invoice['tax'], 2) . '</td>
            </tr>
            <tr class="total">
                <td colspan="3" style="text-align: right;"><strong>Total:</strong></td>
                <td>$' . number_format($invoice['total'], 2) . '</td>
            </tr>
        </tbody>
    </table>
    ';
    
    $mpdf->WriteHTML($html);
    
    // Generar nombre del archivo
    $filename = 'factura-' . $invoice['invoice_number'] . '.pdf';
    
    // Enviar PDF al navegador
    $mpdf->Output($filename, 'D');
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: billing.php");
    exit();
} 
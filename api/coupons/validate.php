<?php
require_once '../../includes/header.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['code']) || empty($data['plan_id'])) {
        throw new Exception("Datos incompletos");
    }
    
    // Obtener información del plan
    $stmt = $conn->prepare("
        SELECT * FROM subscription_plans WHERE id = ?
    ");
    $stmt->execute([$data['plan_id']]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$plan) {
        throw new Exception("Plan no encontrado");
    }
    
    // Validar cupón
    $stmt = $conn->prepare("
        SELECT * FROM coupons 
        WHERE code = ? 
        AND is_active = TRUE
        AND (max_uses IS NULL OR times_used < max_uses)
        AND start_date <= NOW()
        AND (end_date IS NULL OR end_date >= NOW())
    ");
    $stmt->execute([$data['code']]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$coupon) {
        throw new Exception("Cupón no válido o expirado");
    }
    
    // Verificar si el usuario ya usó este cupón
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM coupon_usage
        WHERE coupon_id = ? AND user_id = ?
    ");
    $stmt->execute([$coupon['id'], $_SESSION['user_id']]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("Ya has usado este cupón");
    }
    
    // Calcular descuento
    $discount = 0;
    if ($coupon['discount_type'] === 'percentage') {
        $discount = $plan['price'] * ($coupon['discount_value'] / 100);
    } else {
        $discount = $coupon['discount_value'];
    }
    
    // Asegurar que el descuento no exceda el precio
    $discount = min($discount, $plan['price']);
    $final_price = $plan['price'] - $discount;
    
    echo json_encode([
        'success' => true,
        'coupon' => [
            'id' => $coupon['id'],
            'code' => $coupon['code'],
            'discount_type' => $coupon['discount_type'],
            'discount_value' => $coupon['discount_value'],
            'discount_amount' => $discount,
            'final_price' => $final_price
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
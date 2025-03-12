<?php
require_once '../../includes/header.php';
require_once '../../config/paypal.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

// Verificar método y acción
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

$action = $_POST['action'] ?? '';
$course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
$user_id = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'create_order':
            // Obtener información del curso
            $stmt = $conn->prepare("
                SELECT title, price 
                FROM courses 
                WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$course_id]);
            $course = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$course) {
                throw new Exception("Curso no encontrado");
            }
            
            // Crear orden en PayPal
            $ch = curl_init();
            
            $payload = [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'amount' => [
                        'currency_code' => PAYPAL_CURRENCY,
                        'value' => number_format($course['price'], 2, '.', '')
                    ],
                    'description' => "Inscripción al curso: " . $course['title']
                ]],
                'application_context' => [
                    'return_url' => BASE_URL . '/api/payments/paypal.php?action=capture',
                    'cancel_url' => BASE_URL . '/client/courses.php'
                ]
            ];
            
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api-m.' . (PAYPAL_MODE === 'sandbox' ? 'sandbox.' : '') . 'paypal.com/v2/checkout/orders',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . get_paypal_access_token()
                ]
            ]);
            
            $response = curl_exec($ch);
            $err = curl_error($ch);
            
            curl_close($ch);
            
            if ($err) {
                throw new Exception("Error al crear la orden: " . $err);
            }
            
            $result = json_decode($response, true);
            
            if (!isset($result['id'])) {
                throw new Exception("Error en la respuesta de PayPal");
            }
            
            // Guardar orden temporal
            $stmt = $conn->prepare("
                INSERT INTO enrollments (user_id, course_id, payment_status, payment_method, payment_amount, transaction_id)
                VALUES (?, ?, 'pending', 'paypal', ?, ?)
            ");
            $stmt->execute([$user_id, $course_id, $course['price'], $result['id']]);
            
            echo json_encode([
                'order_id' => $result['id'],
                'links' => $result['links']
            ]);
            break;
            
        case 'capture':
            $token = $_GET['token'] ?? '';
            
            if (empty($token)) {
                throw new Exception("Token no proporcionado");
            }
            
            // Capturar pago
            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api-m.' . (PAYPAL_MODE === 'sandbox' ? 'sandbox.' : '') . 'paypal.com/v2/checkout/orders/' . $token . '/capture',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . get_paypal_access_token()
                ]
            ]);
            
            $response = curl_exec($ch);
            $err = curl_error($ch);
            
            curl_close($ch);
            
            if ($err) {
                throw new Exception("Error al capturar el pago: " . $err);
            }
            
            $result = json_decode($response, true);
            
            if ($result['status'] === 'COMPLETED') {
                // Actualizar inscripción
                $stmt = $conn->prepare("
                    UPDATE enrollments 
                    SET payment_status = 'completed', updated_at = NOW()
                    WHERE transaction_id = ?
                ");
                $stmt->execute([$token]);
                
                // Redirigir a página de éxito
                header("Location: " . BASE_URL . "/client/payment_success.php");
                exit();
            } else {
                throw new Exception("El pago no se completó correctamente");
            }
            break;
            
        default:
            throw new Exception("Acción no válida");
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

// Función para obtener token de acceso de PayPal
function get_paypal_access_token() {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api-m.' . (PAYPAL_MODE === 'sandbox' ? 'sandbox.' : '') . 'paypal.com/v1/oauth2/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => PAYPAL_CLIENT_ID . ":" . PAYPAL_CLIENT_SECRET,
        CURLOPT_POSTFIELDS => "grant_type=client_credentials",
        CURLOPT_HTTPHEADER => ['Accept: application/json']
    ]);
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    
    curl_close($ch);
    
    if ($err) {
        throw new Exception("Error al obtener token de acceso: " . $err);
    }
    
    $result = json_decode($response, true);
    
    if (!isset($result['access_token'])) {
        throw new Exception("Error en la autenticación con PayPal");
    }
    
    return $result['access_token'];
} 
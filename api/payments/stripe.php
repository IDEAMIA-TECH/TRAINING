<?php
require_once '../../includes/header.php';
require_once '../../config/stripe.php';
require_once '../../vendor/autoload.php';

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? '';
            
            switch ($action) {
                case 'create_session':
                    $course_id = (int)$input['course_id'];
                    
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
                    
                    // Crear sesión de pago en Stripe
                    $session = \Stripe\Checkout\Session::create([
                        'payment_method_types' => ['card'],
                        'line_items' => [[
                            'price_data' => [
                                'currency' => STRIPE_CURRENCY,
                                'unit_amount' => $course['price'] * 100, // Stripe usa centavos
                                'product_data' => [
                                    'name' => $course['title'],
                                    'description' => 'Inscripción al curso'
                                ],
                            ],
                            'quantity' => 1,
                        ]],
                        'mode' => 'payment',
                        'success_url' => BASE_URL . '/client/payment_success.php?session_id={CHECKOUT_SESSION_ID}',
                        'cancel_url' => BASE_URL . '/client/courses.php',
                        'metadata' => [
                            'course_id' => $course_id,
                            'user_id' => $_SESSION['user_id']
                        ]
                    ]);
                    
                    // Guardar orden temporal
                    $stmt = $conn->prepare("
                        INSERT INTO enrollments (
                            user_id, course_id, payment_status, payment_method, 
                            payment_amount, transaction_id
                        ) VALUES (?, ?, 'pending', 'stripe', ?, ?)
                    ");
                    $stmt->execute([
                        $_SESSION['user_id'],
                        $course_id,
                        $course['price'],
                        $session->id
                    ]);
                    
                    echo json_encode(['sessionId' => $session->id]);
                    break;
                    
                default:
                    throw new Exception("Acción no válida");
            }
            break;
            
        case 'POST':
            if ($_SERVER['REQUEST_URI'] === '/api/payments/stripe/webhook') {
                $payload = @file_get_contents('php://input');
                $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
                
                try {
                    $event = \Stripe\Webhook::constructEvent(
                        $payload, $sig_header, STRIPE_WEBHOOK_SECRET
                    );
                } catch(\UnexpectedValueException $e) {
                    http_response_code(400);
                    exit();
                } catch(\Stripe\Exception\SignatureVerificationException $e) {
                    http_response_code(400);
                    exit();
                }
                
                if ($event->type === 'checkout.session.completed') {
                    $session = $event->data->object;
                    
                    // Actualizar inscripción
                    $stmt = $conn->prepare("
                        UPDATE enrollments 
                        SET payment_status = 'completed', updated_at = NOW()
                        WHERE transaction_id = ?
                    ");
                    $stmt->execute([$session->id]);
                }
                
                http_response_code(200);
            }
            break;
            
        default:
            throw new Exception("Método no permitido");
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 
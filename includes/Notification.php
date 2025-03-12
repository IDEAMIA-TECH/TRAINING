<?php
class Notification {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function send($user_id, $course_id, $title, $message, $type = 'email') {
        try {
            // Guardar notificación en la base de datos
            $stmt = $this->conn->prepare("
                INSERT INTO notifications (user_id, course_id, title, message, type, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$user_id, $course_id, $title, $message, $type]);
            
            $notification_id = $this->conn->lastInsertId();
            
            // Enviar notificación según el tipo
            switch ($type) {
                case 'email':
                    $success = $this->sendEmail($notification_id);
                    break;
                    
                case 'whatsapp':
                    $success = $this->sendWhatsApp($notification_id);
                    break;
                    
                default:
                    throw new Exception("Tipo de notificación no válido");
            }
            
            // Actualizar estado de la notificación
            $status = $success ? 'sent' : 'failed';
            $stmt = $this->conn->prepare("
                UPDATE notifications 
                SET status = ?, sent_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$status, $notification_id]);
            
            return $success;
        } catch (Exception $e) {
            error_log("Error al enviar notificación: " . $e->getMessage());
            return false;
        }
    }
    
    private function sendEmail($notification_id) {
        try {
            // Obtener datos de la notificación
            $stmt = $this->conn->prepare("
                SELECT n.*, u.email, u.name
                FROM notifications n
                JOIN users u ON n.user_id = u.id
                WHERE n.id = ?
            ");
            $stmt->execute([$notification_id]);
            $notification = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Configurar cabeceras del email
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: " . SITE_EMAIL . "\r\n";
            
            // Crear plantilla HTML
            $html = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: #f8f9fa; padding: 20px; text-align: center; }
                        .content { padding: 20px; }
                        .footer { text-align: center; padding: 20px; color: #6c757d; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>{$notification['title']}</h2>
                        </div>
                        <div class='content'>
                            <p>Hola {$notification['name']},</p>
                            {$notification['message']}
                        </div>
                        <div class='footer'>
                            <p>Este es un mensaje automático, por favor no responder.</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            // Enviar email
            return mail(
                $notification['email'],
                $notification['title'],
                $html,
                $headers
            );
        } catch (Exception $e) {
            error_log("Error al enviar email: " . $e->getMessage());
            return false;
        }
    }
    
    private function sendWhatsApp($notification_id) {
        try {
            // Obtener datos de la notificación
            $stmt = $this->conn->prepare("
                SELECT n.*, u.phone, u.name
                FROM notifications n
                JOIN users u ON n.user_id = u.id
                WHERE n.id = ?
            ");
            $stmt->execute([$notification_id]);
            $notification = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Configurar cliente de WhatsApp
            $client = new \GuzzleHttp\Client();
            
            // Preparar mensaje
            $message = "*{$notification['title']}*\n\n";
            $message .= "Hola {$notification['name']},\n\n";
            $message .= $notification['message'];
            
            // Enviar mensaje a través de la API de WhatsApp Business
            $response = $client->post('https://graph.facebook.com/v12.0/YOUR_PHONE_NUMBER_ID/messages', [
                'headers' => [
                    'Authorization' => 'Bearer ' . WHATSAPP_ACCESS_TOKEN,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to' => $notification['phone'],
                    'type' => 'text',
                    'text' => [
                        'body' => $message
                    ]
                ]
            ]);
            
            return $response->getStatusCode() === 200;
        } catch (Exception $e) {
            error_log("Error al enviar WhatsApp: " . $e->getMessage());
            return false;
        }
    }
} 
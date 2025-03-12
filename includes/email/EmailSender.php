<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailSender {
    private $mailer;

    public function __construct() {
        $this->mailer = new PHPMailer(true);
        
        // Configuración del servidor SMTP
        $this->mailer->isSMTP();
        $this->mailer->Host = SMTP_HOST;
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = SMTP_USER;
        $this->mailer->Password = SMTP_PASS;
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = SMTP_PORT;
        
        // Configuración general
        $this->mailer->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $this->mailer->CharSet = 'UTF-8';
        $this->mailer->isHTML(true);
    }

    public function sendRegistrationConfirmation($user, $course) {
        try {
            $this->mailer->addAddress($user['email'], $user['name']);
            $this->mailer->Subject = "Confirmación de Inscripción - {$course['title']}";
            
            $body = $this->getEmailTemplate('registration_confirmation', [
                'user_name' => $user['name'],
                'course_title' => $course['title'],
                'course_start_date' => date('d/m/Y H:i', strtotime($course['start_date'])),
                'course_end_date' => date('d/m/Y H:i', strtotime($course['end_date'])),
                'support_email' => SUPPORT_EMAIL
            ]);
            
            $this->mailer->Body = $body;
            $this->mailer->send();
            
            return true;
        } catch (Exception $e) {
            error_log("Error enviando email de confirmación: " . $e->getMessage());
            return false;
        }
    }

    public function sendPaymentConfirmation($user, $payment_data, $course) {
        try {
            $this->mailer->addAddress($user['email'], $user['name']);
            $this->mailer->Subject = "Confirmación de Pago - {$course['title']}";
            
            $body = $this->getEmailTemplate('payment_confirmation', [
                'user_name' => $user['name'],
                'course_title' => $course['title'],
                'amount' => number_format($payment_data['amount'], 2),
                'transaction_id' => $payment_data['transaction_id'],
                'payment_date' => date('d/m/Y H:i', strtotime($payment_data['created_at'])),
                'support_email' => SUPPORT_EMAIL
            ]);
            
            $this->mailer->Body = $body;
            $this->mailer->send();
            
            return true;
        } catch (Exception $e) {
            error_log("Error enviando email de pago: " . $e->getMessage());
            return false;
        }
    }

    public function sendCourseReminder($user, $course) {
        try {
            $this->mailer->addAddress($user['email'], $user['name']);
            $this->mailer->Subject = "Recordatorio de Curso - {$course['title']}";
            
            $body = $this->getEmailTemplate('course_reminder', [
                'user_name' => $user['name'],
                'course_title' => $course['title'],
                'course_start_date' => date('d/m/Y H:i', strtotime($course['start_date'])),
                'support_email' => SUPPORT_EMAIL
            ]);
            
            $this->mailer->Body = $body;
            $this->mailer->send();
            
            return true;
        } catch (Exception $e) {
            error_log("Error enviando recordatorio: " . $e->getMessage());
            return false;
        }
    }

    private function getEmailTemplate($template_name, $data) {
        $template_file = __DIR__ . "/templates/{$template_name}.html";
        
        if (!file_exists($template_file)) {
            throw new Exception("Template de email no encontrado: {$template_name}");
        }
        
        $template = file_get_contents($template_file);
        
        // Reemplazar variables en el template
        foreach ($data as $key => $value) {
            $template = str_replace("{{" . $key . "}}", $value, $template);
        }
        
        return $template;
    }

    public function clearAddresses() {
        $this->mailer->clearAddresses();
    }
} 
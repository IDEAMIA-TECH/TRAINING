<?php
class EmailSender {
    private $mailer;
    private $from_email;
    private $from_name;

    public function __construct() {
        // Configurar PHPMailer
        $this->mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        $this->mailer->isSMTP();
        $this->mailer->Host = SMTP_HOST;
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = SMTP_USER;
        $this->mailer->Password = SMTP_PASS;
        $this->mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = SMTP_PORT;
        $this->mailer->CharSet = 'UTF-8';

        $this->from_email = MAIL_FROM_ADDRESS;
        $this->from_name = MAIL_FROM_NAME;
    }

    public function sendRegistrationConfirmation($user, $course) {
        try {
            $this->mailer->setFrom($this->from_email, $this->from_name);
            $this->mailer->addAddress($user['email'], $user['name']);
            $this->mailer->Subject = 'Confirmación de Registro - ' . $course['title'];

            $body = $this->getTemplate('registration_confirmation', [
                'user_name' => $user['name'],
                'course_title' => $course['title'],
                'course_date' => date('d/m/Y H:i', strtotime($course['start_date'])),
                'course_location' => 'Nuestra Sede',
                'login_url' => BASE_URL . '/login.php'
            ]);

            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags($body);

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Error enviando email de confirmación: " . $e->getMessage());
            return false;
        }
    }

    public function sendPaymentConfirmation($user, $payment, $course) {
        try {
            $this->mailer->setFrom($this->from_email, $this->from_name);
            $this->mailer->addAddress($user['email'], $user['name']);
            $this->mailer->Subject = 'Confirmación de Pago - ' . $course['title'];

            $body = $this->getTemplate('payment_confirmation', [
                'user_name' => $user['name'],
                'course_title' => $course['title'],
                'amount' => number_format($payment['amount'], 2),
                'transaction_id' => $payment['transaction_id'],
                'payment_date' => date('d/m/Y H:i', strtotime($payment['created_at'])),
                'course_url' => BASE_URL . '/courses.php?id=' . $course['id']
            ]);

            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags($body);

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Error enviando email de pago: " . $e->getMessage());
            return false;
        }
    }

    public function sendRegistrationCancellation($user, $course) {
        try {
            $this->mailer->setFrom($this->from_email, $this->from_name);
            $this->mailer->addAddress($user['email'], $user['name']);
            $this->mailer->Subject = 'Cancelación de Registro - ' . $course['title'];

            $body = $this->getTemplate('registration_cancellation', [
                'user_name' => $user['name'],
                'course_title' => $course['title'],
                'support_email' => SUPPORT_EMAIL
            ]);

            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags($body);

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Error enviando email de cancelación: " . $e->getMessage());
            return false;
        }
    }

    private function getTemplate($template_name, $data) {
        $template_file = __DIR__ . '/templates/' . $template_name . '.html';
        if (!file_exists($template_file)) {
            throw new Exception("Template no encontrado: " . $template_name);
        }

        $template = file_get_contents($template_file);
        foreach ($data as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }

        return $template;
    }
} 
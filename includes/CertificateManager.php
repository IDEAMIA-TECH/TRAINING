<?php
require_once 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

class CertificateManager {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function generateCertificate($userId, $courseId) {
        try {
            $this->conn->beginTransaction();
            
            // Verificar si el usuario ha completado el curso
            $stmt = $this->conn->prepare("
                SELECT c.*, u.name as student_name, u.email,
                       i.name as instructor_name
                FROM courses c
                JOIN users u ON u.id = ?
                LEFT JOIN users i ON i.id = c.instructor_id
                WHERE c.id = ?
            ");
            $stmt->execute([$userId, $courseId]);
            $courseData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$courseData) {
                throw new Exception("Curso o usuario no encontrado");
            }
            
            // Verificar si ya existe un certificado
            $stmt = $this->conn->prepare("
                SELECT id FROM certificates 
                WHERE user_id = ? AND course_id = ? AND status = 'active'
            ");
            $stmt->execute([$userId, $courseId]);
            
            if ($stmt->fetch()) {
                throw new Exception("Ya existe un certificado activo para este curso");
            }
            
            // Obtener plantilla activa
            $stmt = $this->conn->prepare("
                SELECT * FROM certificate_templates 
                WHERE is_active = TRUE 
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute();
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$template) {
                throw new Exception("No hay plantillas de certificado disponibles");
            }
            
            // Generar número de certificado y código de verificación
            $certificateNumber = $this->generateCertificateNumber();
            $verificationCode = $this->generateVerificationCode();
            
            // Preparar datos del certificado
            $data = [
                'student_name' => $courseData['student_name'],
                'course_name' => $courseData['title'],
                'course_duration' => $courseData['duration'],
                'issue_date' => date('d/m/Y'),
                'location' => 'Ciudad, País',
                'instructor_name' => $courseData['instructor_name'],
                'logo_url' => BASE_URL . '/assets/img/logo.png',
                'signature_url' => BASE_URL . '/assets/img/signature.png',
                'verification_url' => BASE_URL . '/verify/' . $verificationCode,
                'verification_code' => $verificationCode
            ];
            
            // Insertar certificado
            $stmt = $this->conn->prepare("
                INSERT INTO certificates (
                    user_id, course_id, template_id, certificate_number,
                    verification_code, data
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $courseId,
                $template['id'],
                $certificateNumber,
                $verificationCode,
                json_encode($data)
            ]);
            
            $certificateId = $this->conn->lastInsertId();
            
            $this->conn->commit();
            return $certificateId;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    public function generatePDF($certificateId) {
        // Obtener datos del certificado
        $stmt = $this->conn->prepare("
            SELECT c.*, ct.html_template, ct.css_styles
            FROM certificates c
            JOIN certificate_templates ct ON c.template_id = ct.id
            WHERE c.id = ?
        ");
        $stmt->execute([$certificateId]);
        $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$certificate) {
            throw new Exception("Certificado no encontrado");
        }
        
        $data = json_decode($certificate['data'], true);
        
        // Reemplazar variables en la plantilla
        $html = $certificate['html_template'];
        foreach ($data as $key => $value) {
            $html = str_replace('{{'.$key.'}}', $value, $html);
        }
        
        // Configurar DOMPDF
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml('
            <html>
            <head>
                <style>'.$certificate['css_styles'].'</style>
            </head>
            <body>'.$html.'</body>
            </html>
        ');
        
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        
        return $dompdf->output();
    }
    
    private function generateCertificateNumber() {
        return 'CERT-' . date('Y') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    private function generateVerificationCode() {
        return bin2hex(random_bytes(16));
    }
    
    public function verifyCertificate($code) {
        $stmt = $this->conn->prepare("
            SELECT c.*, u.name as student_name, co.title as course_name
            FROM certificates c
            JOIN users u ON c.user_id = u.id
            JOIN courses co ON c.course_id = co.id
            WHERE c.verification_code = ?
        ");
        $stmt->execute([$code]);
        $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($certificate) {
            // Registrar verificación
            $stmt = $this->conn->prepare("
                INSERT INTO certificate_verifications (
                    certificate_id, verifier_ip, verifier_user_agent
                ) VALUES (?, ?, ?)
            ");
            
            $stmt->execute([
                $certificate['id'],
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);
        }
        
        return $certificate;
    }
} 
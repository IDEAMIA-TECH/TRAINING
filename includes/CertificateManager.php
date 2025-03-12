<?php
require_once 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

class CertificateManager {
    private $conn;
    private $outputPath;
    private $dompdf;
    
    public function __construct($conn, $config = []) {
        $this->conn = $conn;
        $this->outputPath = $config['output_path'] ?? 'certificates/';
        $this->dompdf = new \Dompdf\Dompdf();
    }
    
    public function generateCertificate($userId, $courseId) {
        $this->conn->beginTransaction();
        
        try {
            // Verificar si ya existe
            if ($this->certificateExists($userId, $courseId)) {
                throw new Exception("El certificado ya existe");
            }
            
            // Verificar finalización del curso
            if (!$this->hasCompletedCourse($userId, $courseId)) {
                throw new Exception("El curso no está completado");
            }
            
            // Obtener datos necesarios
            $data = $this->gatherCertificateData($userId, $courseId);
            
            // Obtener plantilla
            $template = $this->getDefaultTemplate();
            
            // Generar número de certificado
            $certificateNumber = $this->generateCertificateNumber();
            
            // Crear registro
            $stmt = $this->conn->prepare("
                INSERT INTO certificates (
                    user_id, course_id,
                    template_id, certificate_number,
                    completion_date, metadata
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $courseId,
                $template['id'],
                $certificateNumber,
                date('Y-m-d'),
                json_encode($data)
            ]);
            
            $certificateId = $this->conn->lastInsertId();
            
            // Generar PDF
            $filePath = $this->generatePDF($template, array_merge(
                $data,
                ['certificate_number' => $certificateNumber]
            ));
            
            // Actualizar ruta del archivo
            $stmt = $this->conn->prepare("
                UPDATE certificates
                SET file_path = ?,
                    status = 'generated'
                WHERE id = ?
            ");
            
            $stmt->execute([$filePath, $certificateId]);
            
            $this->conn->commit();
            return $certificateId;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    public function getCertificate($userId, $courseId) {
        $stmt = $this->conn->prepare("
            SELECT 
                c.*,
                ct.name as template_name,
                u.name as student_name,
                co.title as course_name
            FROM certificates c
            JOIN certificate_templates ct ON c.template_id = ct.id
            JOIN users u ON c.user_id = u.id
            JOIN courses co ON c.course_id = co.id
            WHERE c.user_id = ?
            AND c.course_id = ?
        ");
        
        $stmt->execute([$userId, $courseId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function verifyCertificate($certificateNumber) {
        $stmt = $this->conn->prepare("
            SELECT 
                c.*,
                u.name as student_name,
                co.title as course_name
            FROM certificates c
            JOIN users u ON c.user_id = u.id
            JOIN courses co ON c.course_id = co.id
            WHERE c.certificate_number = ?
            AND c.status = 'generated'
        ");
        
        $stmt->execute([$certificateNumber]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function certificateExists($userId, $courseId) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM certificates
            WHERE user_id = ?
            AND course_id = ?
        ");
        
        $stmt->execute([$userId, $courseId]);
        return $stmt->fetchColumn() > 0;
    }
    
    private function hasCompletedCourse($userId, $courseId) {
        $stmt = $this->conn->prepare("
            SELECT 
                (SELECT COUNT(*) FROM lessons WHERE course_id = ?) as total_lessons,
                COUNT(cl.id) as completed_lessons
            FROM completed_lessons cl
            WHERE cl.user_id = ?
            AND cl.lesson_id IN (SELECT id FROM lessons WHERE course_id = ?)
        ");
        
        $stmt->execute([$courseId, $userId, $courseId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['total_lessons'] > 0 && 
               $result['total_lessons'] == $result['completed_lessons'];
    }
    
    private function gatherCertificateData($userId, $courseId) {
        $stmt = $this->conn->prepare("
            SELECT 
                u.name as student_name,
                c.title as course_name,
                c.duration as course_duration,
                i.name as instructor_name
            FROM users u
            JOIN courses c ON c.id = ?
            LEFT JOIN users i ON c.instructor_id = i.id
            WHERE u.id = ?
        ");
        
        $stmt->execute([$courseId, $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getDefaultTemplate() {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM certificate_templates
            WHERE is_default = TRUE
            AND is_active = TRUE
            LIMIT 1
        ");
        
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function generateCertificateNumber() {
        return sprintf(
            'CERT-%s-%s',
            date('Ymd'),
            substr(uniqid(), -8)
        );
    }
    
    private function generatePDF($template, $data) {
        // Procesar plantillas
        $html = $this->processTemplate($template['html_template'], $data);
        $css = $template['css_template'];
        
        // Configurar DOMPDF
        $this->dompdf->setPaper(
            $template['page_size'],
            $template['orientation']
        );
        
        // Generar PDF
        $this->dompdf->loadHtml("
            <style>$css</style>
            $html
        ");
        $this->dompdf->render();
        
        // Guardar archivo
        $filename = "certificate_{$data['certificate_number']}.pdf";
        $filepath = $this->outputPath . $filename;
        
        file_put_contents(
            $filepath,
            $this->dompdf->output()
        );
        
        return $filepath;
    }
    
    private function processTemplate($template, $data) {
        return preg_replace_callback(
            '/\{([^}]+)\}/',
            function($matches) use ($data) {
                return $data[$matches[1]] ?? '';
            },
            $template
        );
    }
} 
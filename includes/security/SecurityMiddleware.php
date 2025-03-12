<?php
class SecurityMiddleware {
    private $db;
    private $logger;
    private $settings;

    public function __construct($db, $logger, $settings) {
        if (!$db) {
            throw new Exception('Database connection is required for SecurityMiddleware');
        }
        $this->db = $db;
        $this->logger = $logger;
        $this->settings = $settings;
    }

    public function validateRequest() {
        // Verificar si estamos en una ruta pública
        if ($this->isPublicRoute()) {
            return true;
        }

        $this->validateRateLimit();
        $this->validateCSRF();
        $this->validateSession();
        return true;
    }

    private function validateCSRF() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if (!$token || $token !== $_SESSION['csrf_token']) {
                $this->logger->log('security_warning', 'csrf', null, [
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    'uri' => $_SERVER['REQUEST_URI']
                ]);
                throw new Exception('Invalid CSRF token');
            }
        }
    }

    private function validateRateLimit() {
        if (!$this->db) {
            throw new Exception('No database connection available');
        }

        $ip = $_SERVER['REMOTE_ADDR'];
        $endpoint = $_SERVER['REQUEST_URI'];
        $now = date('Y-m-d H:i:s');

        // Limpiar registros antiguos
        $stmt = $this->db->prepare("
            DELETE FROM rate_limits 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        $stmt->execute();

        // Contar solicitudes recientes
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM rate_limits 
            WHERE ip_address = ? 
            AND endpoint = ?
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        $stmt->execute([$ip, $endpoint]);
        $result = $stmt->fetch();

        if ($result['count'] > 60) { // 60 solicitudes por minuto
            throw new Exception('Rate limit exceeded');
        }

        // Registrar nueva solicitud
        $stmt = $this->db->prepare("
            INSERT INTO rate_limits (ip_address, endpoint, created_at) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$ip, $endpoint, $now]);
    }

    private function validateInputs() {
        $input = array_merge($_GET, $_POST);
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                // Detectar posibles ataques XSS
                if (preg_match('/<script\b[^>]*>(.*?)<\/script>/is', $value)) {
                    $this->logger->log('security_warning', 'xss', null, [
                        'ip' => $_SERVER['REMOTE_ADDR'],
                        'input' => $key
                    ]);
                    throw new Exception('Invalid input detected');
                }

                // Detectar posibles inyecciones SQL
                if (preg_match('/\b(union|select|insert|update|delete|drop|alter)\b/i', $value)) {
                    $this->logger->log('security_warning', 'sql_injection', null, [
                        'ip' => $_SERVER['REMOTE_ADDR'],
                        'input' => $key
                    ]);
                    throw new Exception('Invalid input detected');
                }
            }
        }
    }

    private function setSecurityHeaders() {
        // Prevenir clickjacking
        header('X-Frame-Options: DENY');
        
        // Habilitar protección XSS en navegadores modernos
        header('X-XSS-Protection: 1; mode=block');
        
        // Prevenir MIME sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Política de seguridad de contenido
        header("Content-Security-Policy: default-src 'self' https:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: https:; font-src 'self' https: data:;");
        
        // Política de referrer
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // HSTS (solo en producción y con SSL)
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }

    public function validateFileUpload($file, $allowed_types = ['image/jpeg', 'image/png'], $max_size = 5242880) {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new Exception('Invalid file parameter');
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new Exception('File too large');
            default:
                throw new Exception('Unknown error');
        }

        if ($file['size'] > $max_size) {
            throw new Exception('File too large');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($file['tmp_name']);

        if (!in_array($mime_type, $allowed_types)) {
            throw new Exception('Invalid file type');
        }

        return true;
    }

    public function sanitizeFilename($filename) {
        // Eliminar caracteres especiales y espacios
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        
        // Prevenir directory traversal
        $filename = basename($filename);
        
        return $filename;
    }

    private function isPublicRoute() {
        $publicRoutes = [
            '/login.php',
            '/register.php',
            '/forgot-password.php',
            '/reset-password.php',
            '/install.php',
            '/assets/',
            '/public/'
        ];

        $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        foreach ($publicRoutes as $route) {
            if (strpos($currentPath, $route) === 0) {
                return true;
            }
        }

        return false;
    }
} 
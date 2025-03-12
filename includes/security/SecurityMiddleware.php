<?php
class SecurityMiddleware {
    private $db;
    private $logger;
    private $settings;
    private $publicRoutes = [
        '/public/login.php',
        '/public/register.php',
        '/public/forgot-password.php',
        '/public/reset-password.php',
        '/public/maintenance.php',
        '/install.php',
        '/public/assets/',
        '/public/api/'
    ];

    public function __construct($db = null, $logger = null, $settings = null) {
        $this->db = $db;
        $this->logger = $logger;
        $this->settings = $settings;
    }

    public function validateRequest() {
        // Si estamos en una ruta pública, no validar
        if ($this->isPublicRoute()) {
            return true;
        }

        // Si no hay conexión a la base de datos, redirigir al instalador
        if (!$this->db) {
            if (!file_exists('install.php')) {
                die('El sistema no está instalado y no se encuentra el instalador.');
            }
            header('Location: /install.php');
            exit;
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
        $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        foreach ($this->publicRoutes as $route) {
            if (strpos($currentPath, $route) === 0) {
                return true;
            }
        }

        return false;
    }

    private function validateSession() {
        // Verificar si la sesión está iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Verificar si el usuario está autenticado
        if (!isset($_SESSION['user_id'])) {
            // Si no está autenticado y no es una ruta pública, redirigir al login
            if (!$this->isPublicRoute()) {
                $_SESSION['error'] = 'Debes iniciar sesión para acceder a esta página';
                $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
                header('Location: /public/login.php');
                exit;
            }
        } else {
            // Si el usuario está autenticado, verificar si la sesión ha expirado
            if (isset($_SESSION['last_activity'])) {
                $session_lifetime = 3600; // 1 hora en segundos
                if (time() - $_SESSION['last_activity'] > $session_lifetime) {
                    // La sesión ha expirado
                    session_unset();
                    session_destroy();
                    $_SESSION['error'] = 'Tu sesión ha expirado. Por favor, inicia sesión nuevamente.';
                    header('Location: /public/login.php');
                    exit;
                }
            }
            
            // Actualizar el tiempo de última actividad
            $_SESSION['last_activity'] = time();

            // Verificar si el usuario sigue activo en la base de datos
            if ($this->db) {
                $stmt = $this->db->prepare("
                    SELECT status 
                    FROM users 
                    WHERE id = ? 
                    AND status = 'active'
                ");
                $stmt->execute([$_SESSION['user_id']]);
                
                if (!$stmt->fetch()) {
                    // El usuario ya no está activo
                    session_unset();
                    session_destroy();
                    $_SESSION['error'] = 'Tu cuenta ha sido desactivada.';
                    header('Location: /public/login.php');
                    exit;
                }
            }
        }

        // Regenerar el ID de sesión periódicamente para prevenir session fixation
        if (!isset($_SESSION['last_regeneration']) || 
            time() - $_SESSION['last_regeneration'] > 300) { // Cada 5 minutos
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
} 
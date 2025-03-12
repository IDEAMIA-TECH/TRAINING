-- Tabla de configuraciones del sistema
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category VARCHAR(50) NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    type ENUM('string', 'integer', 'float', 'boolean', 'json', 'array') NOT NULL,
    is_public BOOLEAN DEFAULT FALSE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_setting (category, setting_key)
);

-- Insertar configuraciones por defecto
INSERT INTO settings (category, setting_key, setting_value, type, is_public, description) VALUES
-- Configuraciones del sitio
('site', 'site_name', 'Mi Plataforma de Cursos', 'string', TRUE, 'Nombre del sitio'),
('site', 'site_description', 'Plataforma de aprendizaje en línea', 'string', TRUE, 'Descripción del sitio'),
('site', 'site_keywords', 'cursos,educación,aprendizaje,online', 'string', TRUE, 'Palabras clave del sitio'),
('site', 'maintenance_mode', 'false', 'boolean', TRUE, 'Modo de mantenimiento'),

-- Configuraciones de email
('email', 'smtp_host', 'smtp.example.com', 'string', FALSE, 'Servidor SMTP'),
('email', 'smtp_port', '587', 'integer', FALSE, 'Puerto SMTP'),
('email', 'smtp_user', 'user@example.com', 'string', FALSE, 'Usuario SMTP'),
('email', 'smtp_pass', '', 'string', FALSE, 'Contraseña SMTP'),
('email', 'from_email', 'no-reply@example.com', 'string', FALSE, 'Email remitente'),
('email', 'from_name', 'Mi Plataforma', 'string', FALSE, 'Nombre remitente'),

-- Configuraciones de pagos
('payment', 'currency', 'USD', 'string', TRUE, 'Moneda por defecto'),
('payment', 'stripe_public_key', '', 'string', FALSE, 'Clave pública de Stripe'),
('payment', 'stripe_secret_key', '', 'string', FALSE, 'Clave secreta de Stripe'),
('payment', 'paypal_client_id', '', 'string', FALSE, 'Client ID de PayPal'),
('payment', 'paypal_secret', '', 'string', FALSE, 'Secret de PayPal'),

-- Configuraciones de archivos
('files', 'max_upload_size', '5242880', 'integer', TRUE, 'Tamaño máximo de archivo (bytes)'),
('files', 'allowed_extensions', '["jpg","jpeg","png","gif","pdf","doc","docx"]', 'json', TRUE, 'Extensiones permitidas'); 
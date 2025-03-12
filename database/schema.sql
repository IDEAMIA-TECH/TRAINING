-- Tabla de usuarios
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'client') DEFAULT 'client',
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active',
    UNIQUE INDEX idx_email (email),
    INDEX idx_status (status)
);

-- Tabla de categorías (mover antes de courses)
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_name (name)
);

-- Tabla de cursos
CREATE TABLE courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    category_id INT,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    capacity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    INDEX idx_status_date (status, start_date)
);

-- Tabla de pagos
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('paypal', 'stripe') NOT NULL,
    transaction_id VARCHAR(100),
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (course_id) REFERENCES courses(id),
    INDEX idx_user (user_id),
    INDEX idx_status (status)
);

-- Tabla de registros de cursos
CREATE TABLE course_registrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    user_id INT NOT NULL,
    payment_id INT,
    status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (payment_id) REFERENCES payments(id),
    INDEX idx_user_course (user_id, course_id)
);

-- Tabla de imágenes de cursos
CREATE TABLE course_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    is_main BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id),
    INDEX idx_course_main (course_id, is_main)
);

-- Tabla de logs de email
CREATE TABLE email_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(50) NOT NULL,
    user_id INT NOT NULL,
    reference_id INT NOT NULL,
    status ENUM('sent', 'failed') NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_type_ref (type, reference_id),
    INDEX idx_user (user_id)
);

-- Tabla de backups
CREATE TABLE database_backups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    filename VARCHAR(255) NOT NULL,
    size INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_created_at (created_at)
);

-- Tabla de logs del sistema
CREATE TABLE system_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created_at (created_at)
);

-- Tabla de configuraciones
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    description TEXT,
    type ENUM('text', 'number', 'boolean', 'email', 'json') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_key (setting_key)
);

-- Configuraciones iniciales
INSERT INTO system_settings (setting_key, setting_value, description, type) VALUES
('site_name', 'Sistema de Cursos', 'Nombre del sitio', 'text'),
('site_email', 'admin@example.com', 'Email principal del sistema', 'email'),
('enable_registrations', '1', 'Permitir registro de usuarios', 'boolean'),
('payment_methods', '["paypal","stripe"]', 'Métodos de pago habilitados', 'json'),
('max_course_capacity', '50', 'Capacidad máxima por defecto para cursos', 'number'),
('reminder_days', '1', 'Días antes para enviar recordatorio de curso', 'number'),
('maintenance_mode', '0', 'Modo mantenimiento', 'boolean');

-- Tabla de rate limits
CREATE TABLE rate_limits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_endpoint (ip_address, endpoint),
    INDEX idx_created_at (created_at)
); 
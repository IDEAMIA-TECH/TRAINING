-- Tabla de notificaciones
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    data JSON,
    is_read BOOLEAN DEFAULT FALSE,
    is_email_sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created_at (created_at)
);

-- Tabla de preferencias de notificaciones
CREATE TABLE notification_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    email_enabled BOOLEAN DEFAULT TRUE,
    web_enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_user_type (user_id, type)
);

-- Tabla de plantillas de notificaciones
CREATE TABLE notification_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(50) NOT NULL,
    title_template TEXT NOT NULL,
    message_template TEXT NOT NULL,
    email_subject VARCHAR(255),
    email_template TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_type (type)
);

-- Insertar tipos de notificaciones por defecto
INSERT INTO notification_preferences (user_id, type, email_enabled, web_enabled)
SELECT 
    u.id,
    t.type,
    TRUE,
    TRUE
FROM users u
CROSS JOIN (
    SELECT 'course_update' as type
    UNION SELECT 'new_comment'
    UNION SELECT 'comment_reply'
    UNION SELECT 'course_completed'
    UNION SELECT 'certificate_available'
) t; 
-- Tabla de mensajes de contacto
CREATE TABLE contact_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'replied', 'spam', 'archived') DEFAULT 'new',
    ip_address VARCHAR(45),
    user_agent TEXT,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_email (email)
);

-- Tabla de respuestas a mensajes
CREATE TABLE contact_replies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    message_id INT NOT NULL,
    subject VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    sent_by INT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES contact_messages(id),
    FOREIGN KEY (sent_by) REFERENCES users(id)
);

-- Tabla de plantillas de respuesta
CREATE TABLE contact_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insertar plantillas por defecto
INSERT INTO contact_templates (name, subject, content) VALUES
('Confirmación de Recepción', 'Hemos recibido tu mensaje', 'Estimado/a {name},\n\nHemos recibido tu mensaje y lo estamos revisando. Te responderemos lo antes posible.\n\nSaludos cordiales,\nEquipo de Soporte'),
('Solicitud de Información', 'Necesitamos más información', 'Estimado/a {name},\n\nPara poder ayudarte mejor, necesitamos que nos proporciones más información sobre:\n\n{details}\n\nGracias por tu comprensión.\n\nSaludos cordiales,\nEquipo de Soporte'); 
-- Tabla de logs del sistema
CREATE TABLE system_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    level ENUM('debug', 'info', 'warning', 'error', 'critical') NOT NULL,
    channel VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    context JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_level_channel (level, channel),
    INDEX idx_created_at (created_at)
);

-- Tabla de logs de acceso
CREATE TABLE access_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action ENUM('login', 'logout', 'failed_login', 'password_reset', 'token_refresh') NOT NULL,
    status ENUM('success', 'failed') NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_action (user_id, action),
    INDEX idx_created_at (created_at)
);

-- Tabla de logs de auditoría
CREATE TABLE audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    action ENUM('create', 'update', 'delete', 'restore') NOT NULL,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created_at (created_at)
); 
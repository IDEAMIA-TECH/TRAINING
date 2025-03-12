-- Tabla de registros de backups
CREATE TABLE backup_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    filename VARCHAR(255) NOT NULL,
    type ENUM('full', 'database', 'files') NOT NULL,
    size BIGINT NOT NULL,
    status ENUM('success', 'failed') NOT NULL,
    started_at TIMESTAMP NOT NULL,
    completed_at TIMESTAMP NULL,
    error_message TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Tabla de configuración de backups automáticos
CREATE TABLE backup_schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type ENUM('full', 'database', 'files') NOT NULL,
    frequency ENUM('daily', 'weekly', 'monthly') NOT NULL,
    retention_days INT NOT NULL DEFAULT 30,
    last_run TIMESTAMP NULL,
    next_run TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insertar configuraciones por defecto
INSERT INTO backup_schedules (type, frequency, retention_days) VALUES
('database', 'daily', 7),
('files', 'weekly', 30),
('full', 'monthly', 90); 
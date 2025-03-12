-- Tabla de métricas diarias
CREATE TABLE daily_metrics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    date DATE NOT NULL,
    metric_key VARCHAR(50) NOT NULL,
    metric_value DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_daily_metric (date, metric_key)
);

-- Tabla de métricas en tiempo real
CREATE TABLE realtime_metrics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    metric_key VARCHAR(50) NOT NULL,
    metric_value INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_metric (metric_key)
);

-- Tabla de eventos de usuario
CREATE TABLE user_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    event_type VARCHAR(50) NOT NULL,
    event_data JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at)
);

-- Insertar métricas en tiempo real por defecto
INSERT INTO realtime_metrics (metric_key, metric_value) VALUES
('active_users', 0),
('active_sessions', 0),
('current_visitors', 0),
('server_load', 0); 
-- Tabla de estadísticas de uso
CREATE TABLE usage_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    date DATE NOT NULL,
    metric VARCHAR(50) NOT NULL,
    value INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_daily_metric (date, metric)
);

-- Tabla de reportes programados
CREATE TABLE scheduled_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL,
    frequency ENUM('daily', 'weekly', 'monthly') NOT NULL,
    recipients TEXT NOT NULL,
    parameters JSON,
    last_sent TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de reportes generados
CREATE TABLE generated_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    scheduled_report_id INT,
    type VARCHAR(50) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    parameters JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (scheduled_report_id) REFERENCES scheduled_reports(id)
);

-- Tabla de reportes
CREATE TABLE reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    query TEXT NOT NULL,
    parameters JSON,
    format ENUM('csv', 'pdf', 'excel', 'html') DEFAULT 'csv',
    schedule VARCHAR(50),
    last_run TIMESTAMP NULL,
    next_run TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Tabla de ejecuciones de reportes
CREATE TABLE report_executions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    report_id INT NOT NULL,
    user_id INT NOT NULL,
    parameters JSON,
    status ENUM('pending', 'processing', 'completed', 'failed') NOT NULL,
    file_path VARCHAR(255),
    error_message TEXT,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES reports(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insertar reportes predefinidos
INSERT INTO reports (type, name, description, query, parameters, created_by) VALUES
('students', 'Progreso de Estudiantes', 'Reporte de progreso de estudiantes por curso', 
'SELECT 
    u.name as student_name,
    c.title as course_title,
    COUNT(DISTINCT cl.id) as completed_lessons,
    (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as total_lessons,
    ROUND((COUNT(DISTINCT cl.id) / (SELECT COUNT(*) FROM lessons WHERE course_id = c.id)) * 100, 2) as progress
FROM users u
JOIN course_enrollments ce ON u.id = ce.user_id
JOIN courses c ON ce.course_id = c.id
LEFT JOIN completed_lessons cl ON u.id = cl.user_id AND cl.lesson_id IN (SELECT id FROM lessons WHERE course_id = c.id)
WHERE c.id = :course_id
GROUP BY u.id, c.id
ORDER BY progress DESC',
'{"course_id": {"type": "integer", "required": true, "label": "ID del Curso"}}',
1),

('financial', 'Reporte de Ventas', 'Reporte de ventas por período',
'SELECT 
    DATE_FORMAT(p.created_at, "%Y-%m-%d") as date,
    COUNT(*) as total_sales,
    SUM(p.amount) as total_amount,
    p.currency,
    p.payment_method
FROM payments p
WHERE p.status = "completed"
AND p.created_at BETWEEN :start_date AND :end_date
GROUP BY DATE_FORMAT(p.created_at, "%Y-%m-%d"), p.currency, p.payment_method
ORDER BY date DESC',
'{"start_date": {"type": "date", "required": true, "label": "Fecha Inicio"}, "end_date": {"type": "date", "required": true, "label": "Fecha Fin"}}',
1); 
-- Tabla de plantillas de certificados
CREATE TABLE certificate_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    html_template TEXT NOT NULL,
    css_template TEXT,
    page_size VARCHAR(20) DEFAULT 'A4',
    orientation ENUM('portrait', 'landscape') DEFAULT 'landscape',
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de certificados generados
CREATE TABLE certificates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    template_id INT NOT NULL,
    certificate_number VARCHAR(50) UNIQUE,
    completion_date DATE NOT NULL,
    expiration_date DATE,
    status ENUM('pending', 'generated', 'revoked') DEFAULT 'pending',
    metadata JSON,
    file_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (course_id) REFERENCES courses(id),
    FOREIGN KEY (template_id) REFERENCES certificate_templates(id),
    INDEX idx_user_course (user_id, course_id),
    INDEX idx_certificate_number (certificate_number)
);

-- Tabla de registro de verificaciones
CREATE TABLE certificate_verifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    certificate_id INT NOT NULL,
    verification_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verifier_ip VARCHAR(45),
    verifier_user_agent TEXT,
    FOREIGN KEY (certificate_id) REFERENCES certificates(id) ON DELETE CASCADE
);

-- Insertar plantilla por defecto
INSERT INTO certificate_templates (name, description, html_template, css_template, is_default) VALUES
('Plantilla Estándar', 'Plantilla de certificado por defecto', 
'<div class="certificate">
    <div class="header">
        <img src="{logo_url}" class="logo">
        <h1>Certificado de Finalización</h1>
    </div>
    <div class="content">
        <p class="student">Se certifica que</p>
        <h2>{student_name}</h2>
        <p class="description">ha completado satisfactoriamente el curso</p>
        <h3>{course_name}</h3>
        <p class="details">con una duración de {course_duration} horas</p>
    </div>
    <div class="footer">
        <p class="date">Completado el {completion_date}</p>
        <p class="certificate-number">Certificado Nº: {certificate_number}</p>
        <div class="signature">
            <img src="{signature_url}" class="signature-img">
            <p>{instructor_name}</p>
            <p class="title">Instructor</p>
        </div>
    </div>
</div>',
'body {
    font-family: "Arial", sans-serif;
    margin: 0;
    padding: 0;
}
.certificate {
    width: 1100px;
    height: 750px;
    padding: 40px;
    border: 2px solid #gold;
    text-align: center;
}
.header {
    margin-bottom: 50px;
}
.logo {
    width: 150px;
}
h1 {
    color: #333;
    font-size: 48px;
    margin: 20px 0;
}
.content {
    margin: 40px 0;
}
.student {
    font-size: 24px;
    margin: 10px 0;
}
h2 {
    color: #000;
    font-size: 36px;
    margin: 20px 0;
}
.description {
    font-size: 24px;
    margin: 10px 0;
}
h3 {
    color: #333;
    font-size: 30px;
    margin: 20px 0;
}
.details {
    font-size: 20px;
    margin: 10px 0;
}
.footer {
    margin-top: 50px;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
}
.date, .certificate-number {
    font-size: 18px;
}
.signature {
    text-align: center;
}
.signature-img {
    width: 200px;
    margin-bottom: 10px;
}
.title {
    font-style: italic;
    margin: 5px 0;
}',
TRUE); 
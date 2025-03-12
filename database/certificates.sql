-- Tabla de plantillas de certificados
CREATE TABLE certificate_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    html_template TEXT NOT NULL,
    css_styles TEXT,
    variables JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de certificados emitidos
CREATE TABLE certificates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    exam_id INT NOT NULL,
    score INT NOT NULL,
    certificate_number VARCHAR(50) UNIQUE,
    issued_date TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (course_id) REFERENCES courses(id),
    FOREIGN KEY (exam_id) REFERENCES exams(id),
    UNIQUE KEY unique_certificate (user_id, course_id, exam_id)
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
INSERT INTO certificate_templates (name, description, html_template, css_styles, variables) VALUES (
    'Plantilla Básica',
    'Plantilla básica para certificados de finalización de curso',
    '
    <div class="certificate">
        <div class="header">
            <img src="{{logo_url}}" class="logo">
            <h1>Certificado de Finalización</h1>
        </div>
        
        <div class="content">
            <p class="declaration">Se certifica que</p>
            <h2 class="student-name">{{student_name}}</h2>
            <p class="completion">ha completado satisfactoriamente el curso</p>
            <h3 class="course-name">{{course_name}}</h3>
            <p class="duration">con una duración de {{course_duration}} horas</p>
            
            <div class="date-location">
                <p>{{issue_date}}</p>
                <p>{{location}}</p>
            </div>
        </div>
        
        <div class="footer">
            <div class="signature">
                <img src="{{signature_url}}" class="signature-img">
                <p>{{instructor_name}}</p>
                <p class="title">Instructor</p>
            </div>
            
            <div class="verification">
                <p>Verificar en: {{verification_url}}</p>
                <p>Código: {{verification_code}}</p>
            </div>
        </div>
    </div>
    ',
    '
    .certificate {
        width: 1000px;
        height: 700px;
        padding: 40px;
        border: 20px solid #091f2f;
        background: #fff;
        color: #333;
        font-family: "Times New Roman", serif;
    }

    .header {
        text-align: center;
        margin-bottom: 50px;
    }

    .logo {
        width: 150px;
        margin-bottom: 20px;
    }

    .content {
        text-align: center;
        margin: 50px 0;
    }

    .student-name {
        font-size: 48px;
        color: #091f2f;
        margin: 20px 0;
    }

    .course-name {
        font-size: 32px;
        color: #1a5f7a;
        margin: 20px 0;
    }

    .date-location {
        margin: 40px 0;
    }

    .footer {
        display: flex;
        justify-content: space-between;
        margin-top: 60px;
    }

    .signature {
        text-align: center;
    }

    .signature-img {
        width: 200px;
        margin-bottom: 10px;
    }

    .verification {
        text-align: right;
        font-size: 12px;
    }
    ',
    '{
        "logo_url": "URL del logo",
        "student_name": "Nombre del estudiante",
        "course_name": "Nombre del curso",
        "course_duration": "Duración del curso",
        "issue_date": "Fecha de emisión",
        "location": "Ubicación",
        "signature_url": "URL de la firma",
        "instructor_name": "Nombre del instructor",
        "verification_url": "URL de verificación",
        "verification_code": "Código de verificación"
    }'
); 
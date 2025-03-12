-- Tabla de páginas estáticas
CREATE TABLE pages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    meta_description TEXT,
    meta_keywords VARCHAR(255),
    status ENUM('draft', 'published', 'private') DEFAULT 'draft',
    layout VARCHAR(50) DEFAULT 'default',
    order_index INT DEFAULT 0,
    show_in_menu BOOLEAN DEFAULT FALSE,
    created_by INT NOT NULL,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id),
    UNIQUE KEY unique_slug (slug)
);

-- Tabla de revisiones de páginas
CREATE TABLE page_revisions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    page_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    meta_description TEXT,
    meta_keywords VARCHAR(255),
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (page_id) REFERENCES pages(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Insertar páginas por defecto
INSERT INTO pages (title, slug, content, status, show_in_menu, created_by) VALUES
('Términos y Condiciones', 'terminos-y-condiciones', '<h1>Términos y Condiciones</h1><p>Contenido de los términos y condiciones...</p>', 'published', TRUE, 1),
('Política de Privacidad', 'politica-de-privacidad', '<h1>Política de Privacidad</h1><p>Contenido de la política de privacidad...</p>', 'published', TRUE, 1),
('Sobre Nosotros', 'sobre-nosotros', '<h1>Sobre Nosotros</h1><p>Información sobre la plataforma...</p>', 'published', TRUE, 1); 
-- Tabla de recursos
CREATE TABLE resources (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    type ENUM('document', 'video', 'audio', 'link', 'file') NOT NULL,
    file_id INT,
    external_url VARCHAR(255),
    category_id INT,
    downloads INT DEFAULT 0,
    is_premium BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES files(id),
    FOREIGN KEY (category_id) REFERENCES resource_categories(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_type_status (type, status)
);

-- Tabla de categorías de recursos
CREATE TABLE resource_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT,
    parent_id INT,
    order_index INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES resource_categories(id),
    UNIQUE KEY unique_slug (slug)
);

-- Tabla de descargas de recursos
CREATE TABLE resource_downloads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    resource_id INT NOT NULL,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (resource_id) REFERENCES resources(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_resource_user (resource_id, user_id)
);

-- Insertar categorías por defecto
INSERT INTO resource_categories (name, slug, description) VALUES
('Documentación', 'documentacion', 'Manuales y guías de referencia'),
('Plantillas', 'plantillas', 'Plantillas y ejemplos de código'),
('Herramientas', 'herramientas', 'Software y utilidades'),
('Material Complementario', 'material-complementario', 'Recursos adicionales para cursos'); 
-- Tabla de posts del blog
CREATE TABLE blog_posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    excerpt TEXT,
    featured_image_id INT,
    meta_description TEXT,
    meta_keywords VARCHAR(255),
    status ENUM('draft', 'published', 'private') DEFAULT 'draft',
    views INT DEFAULT 0,
    allow_comments BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    updated_by INT,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (featured_image_id) REFERENCES images(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id),
    UNIQUE KEY unique_slug (slug),
    INDEX idx_status (status)
);

-- Tabla de categorías del blog
CREATE TABLE blog_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT,
    parent_id INT,
    order_index INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES blog_categories(id),
    UNIQUE KEY unique_slug (slug)
);

-- Tabla pivote posts-categorías
CREATE TABLE blog_post_categories (
    post_id INT NOT NULL,
    category_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (post_id, category_id),
    FOREIGN KEY (post_id) REFERENCES blog_posts(id),
    FOREIGN KEY (category_id) REFERENCES blog_categories(id)
);

-- Tabla de etiquetas del blog
CREATE TABLE blog_tags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_slug (slug)
);

-- Tabla pivote posts-etiquetas
CREATE TABLE blog_post_tags (
    post_id INT NOT NULL,
    tag_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (post_id, tag_id),
    FOREIGN KEY (post_id) REFERENCES blog_posts(id),
    FOREIGN KEY (tag_id) REFERENCES blog_tags(id)
);

-- Insertar categorías por defecto
INSERT INTO blog_categories (name, slug, description) VALUES
('Tutoriales', 'tutoriales', 'Guías paso a paso para aprender'),
('Noticias', 'noticias', 'Últimas novedades y actualizaciones'),
('Recursos', 'recursos', 'Material útil para estudiantes'); 
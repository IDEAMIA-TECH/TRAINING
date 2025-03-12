-- Tabla de banners
CREATE TABLE banners (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    image_id INT NOT NULL,
    link VARCHAR(255),
    position VARCHAR(50) NOT NULL,
    start_date TIMESTAMP NULL,
    end_date TIMESTAMP NULL,
    status ENUM('active', 'inactive', 'scheduled') DEFAULT 'inactive',
    priority INT DEFAULT 0,
    clicks INT DEFAULT 0,
    views INT DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (image_id) REFERENCES images(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_status_position (status, position),
    INDEX idx_dates (start_date, end_date)
);

-- Tabla de estadísticas de banners
CREATE TABLE banner_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    banner_id INT NOT NULL,
    date DATE NOT NULL,
    views INT DEFAULT 0,
    clicks INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (banner_id) REFERENCES banners(id),
    UNIQUE KEY unique_banner_date (banner_id, date)
);

-- Tabla de posiciones de banners
CREATE TABLE banner_positions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    max_banners INT DEFAULT 1,
    dimensions VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_position (name)
);

-- Insertar posiciones por defecto
INSERT INTO banner_positions (name, description, max_banners, dimensions) VALUES
('home_top', 'Banner superior en la página de inicio', 1, '1200x300'),
('home_sidebar', 'Banner lateral en la página de inicio', 3, '300x250'),
('course_sidebar', 'Banner lateral en páginas de cursos', 2, '300x600'),
('footer', 'Banner en el pie de página', 4, '728x90'); 
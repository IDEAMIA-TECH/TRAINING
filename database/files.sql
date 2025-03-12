-- Tabla de archivos
CREATE TABLE files (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size INT NOT NULL,
    path VARCHAR(255) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    is_public BOOLEAN DEFAULT FALSE,
    downloads INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_path (path)
);

-- Tabla de imágenes
CREATE TABLE images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    file_id INT NOT NULL,
    width INT NOT NULL,
    height INT NOT NULL,
    alt_text VARCHAR(255),
    title VARCHAR(255),
    thumbnail_path VARCHAR(255),
    medium_path VARCHAR(255),
    large_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES files(id)
);

-- Tabla de metadatos de archivos
CREATE TABLE file_metadata (
    id INT PRIMARY KEY AUTO_INCREMENT,
    file_id INT NOT NULL,
    meta_key VARCHAR(50) NOT NULL,
    meta_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES files(id),
    UNIQUE KEY unique_metadata (file_id, meta_key)
); 
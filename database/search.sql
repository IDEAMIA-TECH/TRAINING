-- Tabla de índice de búsqueda
CREATE TABLE search_index (
    id INT PRIMARY KEY AUTO_INCREMENT,
    entity_type ENUM('course', 'lesson', 'blog_post', 'resource') NOT NULL,
    entity_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    keywords TEXT,
    category VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    relevance INT DEFAULT 0,
    last_indexed TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_status (status),
    FULLTEXT INDEX idx_search (title, content, keywords)
);

-- Tabla de historial de búsquedas
CREATE TABLE search_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    query VARCHAR(255) NOT NULL,
    results_count INT NOT NULL,
    filters JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_query (query)
);

-- Tabla de sugerencias de búsqueda
CREATE TABLE search_suggestions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    query VARCHAR(255) NOT NULL,
    suggestion VARCHAR(255) NOT NULL,
    weight INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_suggestion (query, suggestion)
); 
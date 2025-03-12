-- Tabla de comentarios
CREATE TABLE comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    entity_type ENUM('course', 'lesson', 'blog_post') NOT NULL,
    entity_id INT NOT NULL,
    parent_id INT,
    content TEXT NOT NULL,
    status ENUM('pending', 'approved', 'spam', 'deleted') DEFAULT 'pending',
    likes INT DEFAULT 0,
    reports INT DEFAULT 0,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (parent_id) REFERENCES comments(id),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_status (status)
);

-- Tabla de reacciones a comentarios
CREATE TABLE comment_reactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    comment_id INT NOT NULL,
    user_id INT NOT NULL,
    type ENUM('like', 'dislike') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (comment_id) REFERENCES comments(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_reaction (comment_id, user_id)
);

-- Tabla de reportes de comentarios
CREATE TABLE comment_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    comment_id INT NOT NULL,
    user_id INT NOT NULL,
    reason ENUM('spam', 'offensive', 'inappropriate', 'other') NOT NULL,
    details TEXT,
    status ENUM('pending', 'reviewed', 'ignored') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (comment_id) REFERENCES comments(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
); 
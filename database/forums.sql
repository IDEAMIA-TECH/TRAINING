-- Tabla de categorías de foro
CREATE TABLE forum_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    slug VARCHAR(100) UNIQUE NOT NULL,
    icon VARCHAR(50),
    order_index INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de temas
CREATE TABLE forum_topics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    slug VARCHAR(200) UNIQUE NOT NULL,
    views INT DEFAULT 0,
    is_pinned BOOLEAN DEFAULT FALSE,
    is_locked BOOLEAN DEFAULT FALSE,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES forum_categories(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Tabla de respuestas
CREATE TABLE forum_replies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    topic_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    is_solution BOOLEAN DEFAULT FALSE,
    parent_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (topic_id) REFERENCES forum_topics(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (parent_id) REFERENCES forum_replies(id)
);

-- Tabla de reacciones
CREATE TABLE forum_reactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    target_type ENUM('topic', 'reply') NOT NULL,
    target_id INT NOT NULL,
    reaction_type ENUM('like', 'helpful', 'thanks') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_reaction (user_id, target_type, target_id)
);

-- Tabla de reportes
CREATE TABLE forum_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    target_type ENUM('topic', 'reply') NOT NULL,
    target_id INT NOT NULL,
    reason ENUM('spam', 'offensive', 'inappropriate', 'other') NOT NULL,
    description TEXT,
    status ENUM('pending', 'resolved', 'dismissed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    resolved_by INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (resolved_by) REFERENCES users(id)
);

-- Tabla de notificaciones del foro
CREATE TABLE forum_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type ENUM('reply', 'mention', 'solution', 'reaction') NOT NULL,
    target_type ENUM('topic', 'reply') NOT NULL,
    target_id INT NOT NULL,
    actor_id INT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (actor_id) REFERENCES users(id)
);

-- Insertar categorías por defecto
INSERT INTO forum_categories (name, description, slug, icon) VALUES
('Anuncios', 'Anuncios importantes y actualizaciones del sistema', 'anuncios', 'fa-bullhorn'),
('Discusión General', 'Espacio para discusiones generales sobre cursos y aprendizaje', 'discusion-general', 'fa-comments'),
('Ayuda Técnica', 'Soporte técnico y resolución de problemas', 'ayuda-tecnica', 'fa-life-ring'),
('Sugerencias', 'Comparte tus ideas para mejorar la plataforma', 'sugerencias', 'fa-lightbulb'); 
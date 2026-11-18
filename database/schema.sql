-- MySQL 8.4; all Russian text is stored as UTF-8 (utf8mb4).
CREATE DATABASE IF NOT EXISTS cicada_lab4 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cicada_lab4;

CREATE TABLE IF NOT EXISTS books (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL CHECK (price >= 0),
    image VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    genre VARCHAR(100) NOT NULL,
    language VARCHAR(50) NOT NULL DEFAULT 'Русский',
    binding VARCHAR(50) NOT NULL DEFAULT 'Твёрдый',
    featured BOOLEAN NOT NULL DEFAULT FALSE,
    sort_order INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS feedback (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    book_id INT UNSIGNED NOT NULL,
    book_title VARCHAR(255) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL CHECK (unit_price >= 0),
    quantity TINYINT UNSIGNED NOT NULL CHECK (quantity BETWEEN 1 AND 99),
    total DECIMAL(12,2) GENERATED ALWAYS AS (unit_price * quantity) STORED,
    customer_name VARCHAR(80) NOT NULL,
    email VARCHAR(254) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    delivery ENUM('pickup','courier') NOT NULL,
    address VARCHAR(200) NULL,
    message TEXT NOT NULL,
    request_token CHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_feedback_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

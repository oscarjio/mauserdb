-- Migration: user_favoriter — snabblänkar/favoriter per användare
-- Datum: 2026-03-12

CREATE TABLE IF NOT EXISTS user_favoriter (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    route VARCHAR(255) NOT NULL,
    label VARCHAR(100) NOT NULL,
    icon VARCHAR(50) NOT NULL DEFAULT 'fas fa-star',
    color VARCHAR(20) NOT NULL DEFAULT '#4299e1',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_favoriter_user (user_id),
    UNIQUE KEY uq_user_route (user_id, route)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

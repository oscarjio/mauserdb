-- Skapa rebotling_products om den inte redan finns
-- Används av RebotlingProductController och RebotlingController (statistik LEFT JOIN)
CREATE TABLE IF NOT EXISTS rebotling_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    cycle_time_minutes DECIMAL(5,2) NOT NULL DEFAULT 3.00,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

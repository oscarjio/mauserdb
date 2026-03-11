-- Migration: 2026-03-11_underhallslogg.sql
-- Skapar tabeller för underhållslogg

CREATE TABLE IF NOT EXISTS `underhallslogg` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `kategori` VARCHAR(50) NOT NULL,
    `typ` ENUM('planerat','oplanerat') NOT NULL,
    `varaktighet_min` INT NOT NULL,
    `kommentar` TEXT,
    `maskin` VARCHAR(100) NOT NULL DEFAULT 'Rebotling',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_kategori` (`kategori`),
    KEY `idx_typ` (`typ`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `underhall_kategorier` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `namn` VARCHAR(50) NOT NULL,
    `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `underhall_kategorier` (`namn`, `aktiv`) VALUES
    ('Mekaniskt', 1),
    ('Elektriskt', 1),
    ('Hydraulik', 1),
    ('Pneumatik', 1),
    ('Rengöring', 1),
    ('Kalibrering', 1),
    ('Annat', 1);

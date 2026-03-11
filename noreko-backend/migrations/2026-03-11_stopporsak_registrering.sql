-- Migration: 2026-03-11_stopporsak_registrering.sql
-- Tabeller för snabbregistrering av stopporsaker

CREATE TABLE IF NOT EXISTS `stopporsak_kategorier` (
    `id` int NOT NULL AUTO_INCREMENT,
    `namn` varchar(100) NOT NULL,
    `ikon` varchar(10) NOT NULL,
    `sort_order` int NOT NULL DEFAULT 0,
    `active` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stopporsak_registreringar` (
    `id` int NOT NULL AUTO_INCREMENT,
    `kategori_id` int NOT NULL,
    `linje` varchar(50) NOT NULL DEFAULT 'rebotling',
    `kommentar` text,
    `user_id` int DEFAULT NULL,
    `start_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `end_time` datetime DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_kategori` (`kategori_id`),
    KEY `idx_linje` (`linje`),
    KEY `idx_user` (`user_id`),
    KEY `idx_start` (`start_time`),
    CONSTRAINT `fk_stopp_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `stopporsak_kategorier` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Standardkategorier
INSERT INTO `stopporsak_kategorier` (`namn`, `ikon`, `sort_order`) VALUES
('Underhåll', '🔧', 1),
('Materialbrist', '📦', 2),
('Kvalitetskontroll', '🔍', 3),
('Rast', '☕', 4),
('Rengöring', '🧹', 5),
('Maskinhaveri', '⚠️', 6),
('Verktygsbyte', '🔄', 7),
('Övrigt', '📝', 8);

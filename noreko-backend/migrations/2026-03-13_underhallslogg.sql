-- Migration: 2026-03-13_underhallslogg.sql
-- Skapar tabell for Rebotling-specifik underhallslogg kopplad till stationer

CREATE TABLE IF NOT EXISTS `rebotling_underhallslogg` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `station_id` INT NOT NULL DEFAULT 0,
    `typ` ENUM('planerat','oplanerat') NOT NULL,
    `beskrivning` TEXT,
    `varaktighet_min` INT NOT NULL DEFAULT 0,
    `stopporsak` VARCHAR(255) DEFAULT NULL,
    `utford_av` VARCHAR(100) DEFAULT NULL,
    `datum` DATETIME NOT NULL,
    `skapad` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_station_id` (`station_id`),
    KEY `idx_typ` (`typ`),
    KEY `idx_datum` (`datum`),
    KEY `idx_skapad` (`skapad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration: Driftstopp-tabell för PLC-kommando
-- Datum: 2026-03-14

CREATE TABLE IF NOT EXISTS rebotling_driftstopp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    datum DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    driftstopp_status TINYINT(1) NOT NULL DEFAULT 0,
    skiftraknare INT DEFAULT NULL,
    KEY idx_datum (datum),
    KEY idx_skiftraknare (skiftraknare)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ny kolumn på skiftrapport för att lagra driftstopptid per skift
ALTER TABLE rebotling_skiftrapport ADD COLUMN driftstopptime INT DEFAULT NULL AFTER rasttime;

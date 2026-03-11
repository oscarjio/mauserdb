-- Migration: 2026-03-11_skiftoverlamning.sql
-- Skiftöverlämningsmall — fritext-noteringar kopplade till PLC-skiftraknare
-- Kompletterar shift_handover (som är skift-nr-baserat) med IBC-produktionsdata.

CREATE TABLE IF NOT EXISTS skiftoverlamning_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    skiftraknare INT NOT NULL COMMENT 'PLC-skiftraknare (unikt per skift)',
    linje VARCHAR(50) NOT NULL DEFAULT 'rebotling',
    note_text TEXT NOT NULL,
    user_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_skift_linje (skiftraknare, linje),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

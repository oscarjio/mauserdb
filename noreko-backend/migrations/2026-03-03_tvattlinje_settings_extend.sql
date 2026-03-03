-- Migration: Utöka tvattlinje_settings med timtakt och skiftlangd
-- Datum: 2026-03-03

ALTER TABLE tvattlinje_settings
  ADD COLUMN IF NOT EXISTS timtakt INT NOT NULL DEFAULT 20
    COMMENT 'Förväntad timtakt (tankar/h)',
  ADD COLUMN IF NOT EXISTS skiftlangd DECIMAL(4,1) NOT NULL DEFAULT 8.0
    COMMENT 'Skiftlängd i timmar';

-- Sätt standardvärden om tabellen redan har rader
UPDATE tvattlinje_settings SET timtakt = 20, skiftlangd = 8.0 WHERE timtakt IS NULL OR timtakt = 0;

-- Migration 006: Lägg till operator_id i users-tabellen
-- Kopplar en systemanvändare till ett operatörsnummer i rebotling-systemet

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS operator_id INT NULL DEFAULT NULL COMMENT 'Kopplar användaren till ett operatörsnummer i rebotling_ibc';

-- Index för snabb lookup
CREATE INDEX IF NOT EXISTS idx_users_operator_id ON users (operator_id);

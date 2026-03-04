-- Migration: 2026-03-04_shift_handover_acknowledge.sql
-- Kvittensfunktion för skiftöverlämningsanteckningar
-- Operatörer kan markera en anteckning som "sedd"

ALTER TABLE shift_handover
  ADD COLUMN IF NOT EXISTS acknowledged_by INT NULL,
  ADD COLUMN IF NOT EXISTS acknowledged_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS audience ENUM('alla','ansvarig','teknik') DEFAULT 'alla' NULL;

-- Index för snabb filtrering på okvitterade
ALTER TABLE shift_handover
  ADD INDEX IF NOT EXISTS idx_ack (acknowledged_at);

-- Notera: FOREIGN KEY på acknowledged_by hanteras manuellt om users-tabellen finns
-- ADD FOREIGN KEY IF NOT EXISTS (acknowledged_by) REFERENCES users(id) ON DELETE SET NULL;

-- Migration: Lagg till operator_id-kolumn i users-tabellen
-- LoginController refererar operator_id i SELECT men kolumnen saknades
-- Datum: 2026-03-15

ALTER TABLE users ADD COLUMN IF NOT EXISTS operator_id INT DEFAULT NULL AFTER role;

-- Migration: Lägg till sent_inskickad-kolumn i tvattlinje_skiftrapport
-- Skapad: 2026-06-04
-- Syfte: Flagga om en skiftrapport skickades in i efterhand (dagen efter produktionsdatum)
ALTER TABLE tvattlinje_skiftrapport
  ADD COLUMN sent_inskickad TINYINT(1) NOT NULL DEFAULT 0 AFTER inlagd;

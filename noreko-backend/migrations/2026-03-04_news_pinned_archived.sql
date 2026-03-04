-- Migration: Lägg till arkivering-kolumner i news-tabellen
-- Datum: 2026-03-04

-- Auto-arkivering: NULL=aldrig, N=arkivera efter N dagar
ALTER TABLE news
    ADD COLUMN IF NOT EXISTS arkiveras_efter_dagar INT NULL DEFAULT NULL COMMENT 'Auto-arkivering: NULL=aldrig, N=efter N dagar' AFTER priority;

-- Arkiverad-flagga
ALTER TABLE news
    ADD COLUMN IF NOT EXISTS arkiverad TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=arkiverad, döljs från publikt flöde' AFTER arkiveras_efter_dagar;

-- Index för snabbare filtrering på arkiverad
CREATE INDEX IF NOT EXISTS idx_arkiverad ON news (arkiverad);

-- Migration: Lägg till priority och published-kolumner i news-tabellen
-- Lägg även till nya kategori-typer och published-kolumn
-- Datum: 2026-03-04

-- Lägg till published-kolumn om den inte finns
ALTER TABLE news
    ADD COLUMN IF NOT EXISTS published TINYINT(1) NOT NULL DEFAULT 1 AFTER pinned;

-- Lägg till priority-kolumn (1=låg, 5=hög) om den inte finns
ALTER TABLE news
    ADD COLUMN IF NOT EXISTS priority TINYINT UNSIGNED NOT NULL DEFAULT 3 AFTER published;

-- Uppdatera category-enum för att inkludera nya typer
-- MySQL stöder inte ADD IF NOT EXISTS på ENUM, men vi kan utöka med MODIFY
ALTER TABLE news
    MODIFY COLUMN category ENUM(
        'produktion','bonus','system','info','viktig',
        'rekord','hog_oee','certifiering','urgent'
    ) NOT NULL DEFAULT 'info';

-- Index för snabbare filtrering
CREATE INDEX IF NOT EXISTS idx_priority ON news (priority);
CREATE INDEX IF NOT EXISTS idx_published ON news (published);

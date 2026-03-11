-- Migration: 2026-03-11_skiftrapport_export.sql
-- Skiftrapport PDF-export — inga nya tabeller behövs.
-- Lägger till index som förbättrar prestandan för de aggregeringsfrågor
-- som SkiftrapportExportController kör mot rebotling_ibc.

-- Index för DATE(created_at)-filter (vanligaste filtret i export-controllern)
-- MySQL kan inte använda index direkt på DATE(created_at), men ett index på
-- created_at hjälper range-skanningen avsevärt.
ALTER TABLE rebotling_ibc
    ADD INDEX IF NOT EXISTS idx_reibc_created_at (created_at);

-- Sammansatt index för cykeltidsberäkning per datum + skiftraknare
ALTER TABLE rebotling_ibc
    ADD INDEX IF NOT EXISTS idx_reibc_created_skift (created_at, skiftraknare, datum);

-- Index för operatörsfiltrering vid top-operatörs-aggregering
-- (op1/op2/op3 + datum kombineras i UNION-frågan)
ALTER TABLE rebotling_ibc
    ADD INDEX IF NOT EXISTS idx_reibc_op1_created (op1, created_at);

ALTER TABLE rebotling_ibc
    ADD INDEX IF NOT EXISTS idx_reibc_op2_created (op2, created_at);

ALTER TABLE rebotling_ibc
    ADD INDEX IF NOT EXISTS idx_reibc_op3_created (op3, created_at);

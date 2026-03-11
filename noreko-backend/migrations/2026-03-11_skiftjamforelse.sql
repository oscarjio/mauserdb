-- Migration: 2026-03-11_skiftjamforelse.sql
-- Skapar index för skiftjämförelse-dashboard (filtrering på created_at + ibc_ok kolumner)

-- Index på rebotling_ibc för snabb filtrering per datum och skiftraknare
-- (används i skiftjämförelse för att aggregera per skift)
CREATE INDEX IF NOT EXISTS idx_rebotling_ibc_created_skift
    ON rebotling_ibc (created_at, skiftraknare);

-- Index för filtrering per datum + ibc_ok (kvalitetsberäkning)
CREATE INDEX IF NOT EXISTS idx_rebotling_ibc_created_ok
    ON rebotling_ibc (created_at, ibc_ok);

-- Index på stopporsak_registreringar för filtrering per linje + starttid
-- (används för stopptidsaggregering per skift)
CREATE INDEX IF NOT EXISTS idx_stopporsak_reg_linje_start
    ON stopporsak_registreringar (linje, start_time);

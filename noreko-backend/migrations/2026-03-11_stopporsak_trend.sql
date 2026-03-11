-- Migration: 2026-03-11_stopporsak_trend.sql
-- Beskrivning: Index för stopporsak-trendanalys (veckovis aggregering per orsak)
-- Tabeller: stoppage_log, stopporsak_registreringar

-- Index för snabba veckoaggregering i stoppage_log
CREATE INDEX IF NOT EXISTS idx_stoppage_log_created_at_reason
    ON stoppage_log(created_at, reason_id);

-- Index för stopporsak_registreringar: veckovis aggregering per kategori
CREATE INDEX IF NOT EXISTS idx_stopporsak_reg_start_kategori
    ON stopporsak_registreringar(start_time, kategori_id);

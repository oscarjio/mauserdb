-- Migration: Live Ranking admin-konfiguration
-- Datum: 2026-03-04
-- Beskrivning: Lägger till default-config för live-ranking KPI-val, sortering och refresh-intervall

INSERT INTO rebotling_settings (`key`, `value`) VALUES
    ('live_ranking_config', '{"columns":{"ibc_per_hour":true,"quality_pct":true,"bonus_level":false,"goal_progress":true,"ibc_today":true},"sort_by":"ibc_per_hour","refresh_interval":30}')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

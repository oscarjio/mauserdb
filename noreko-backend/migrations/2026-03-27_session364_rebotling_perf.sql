-- Session #364: Performance indexes for rebotling queries
-- Covering index for the most common aggregation pattern:
-- GROUP BY DATE(datum), COALESCE(skiftraknare, 0) with MAX(ibc_ok), MAX(ibc_ej_ok), etc.

-- Index for today's data queries (used by today-snapshot, all-lines-status, exec-dashboard)
-- Already exists: idx_rebotling_ibc_datum covers datum-only lookups

-- Index for rebotling_onoff ORDER BY datum DESC LIMIT 1 (used in is_running check)
CREATE INDEX IF NOT EXISTS idx_rebotling_onoff_datum_desc ON rebotling_onoff (datum DESC);

-- Index for rebotling_skiftrapport lookups by datum
CREATE INDEX IF NOT EXISTS idx_skiftrapport_datum ON rebotling_skiftrapport (datum);

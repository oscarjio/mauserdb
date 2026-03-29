-- Session #395: Performance indexes for slow endpoints
-- operator-ranking (5.4s), morgonrapport (1.7s), statistikdashboard (1.6s),
-- alarm-historik (1.0s), ranking-historik (1.1s)

-- Covering index for the most common rebotling_ibc query pattern:
-- GROUP BY DATE(datum), skiftraknare → MAX(ibc_ok), MAX(ibc_ej_ok)
-- This index covers datum+skiftraknare+ibc_ok+ibc_ej_ok so the query
-- can be answered entirely from the index (no table lookups).
ALTER TABLE rebotling_ibc
  ADD INDEX IF NOT EXISTS idx_datum_skift_ibc_cover (datum, skiftraknare, ibc_ok, ibc_ej_ok);

-- Covering index for operator ranking queries that scan op1+datum
-- and need COUNT(*) per operator (each row = 1 IBC cycle)
ALTER TABLE rebotling_ibc
  ADD INDEX IF NOT EXISTS idx_op1_datum_cover (op1, datum, skiftraknare);

ALTER TABLE rebotling_ibc
  ADD INDEX IF NOT EXISTS idx_op2_datum_cover (op2, datum, skiftraknare);

ALTER TABLE rebotling_ibc
  ADD INDEX IF NOT EXISTS idx_op3_datum_cover (op3, datum, skiftraknare);

-- Index for stoppage_log queries in alarm-historik (DATE + duration)
ALTER TABLE stoppage_log
  ADD INDEX IF NOT EXISTS idx_start_duration (start_time, duration_minutes);

-- Index for stopporsak_registreringar covering linje+start_time
ALTER TABLE stopporsak_registreringar
  ADD INDEX IF NOT EXISTS idx_linje_start (linje, start_time);

-- Session #360 Worker A: Covering indexes for heavy GROUP BY queries
-- 2026-03-27

-- Covering index on rebotling_ibc for the most common pattern:
-- GROUP BY skiftraknare, DATE(datum) with SELECT ibc_ok, ibc_ej_ok, runtime_plc, op1
-- Eliminates full table scan (5026 rows -> ~996 rows via covering index scan)
ALTER TABLE rebotling_ibc ADD INDEX IF NOT EXISTS idx_ibc_covering_datum_skift (datum, skiftraknare, ibc_ok, ibc_ej_ok, runtime_plc, op1);

-- Covering index on stopporsak_registreringar for active stoppages + time-range queries
-- Covers: WHERE linje = ? AND start_time >= ? AND end_time IS NULL
ALTER TABLE stopporsak_registreringar ADD INDEX IF NOT EXISTS idx_linje_start_end (linje, start_time, end_time, kategori_id);

-- Covering index on maskin_oee_daglig for date range queries with OEE
-- Fixes full table scan on 180 rows
ALTER TABLE maskin_oee_daglig ADD INDEX IF NOT EXISTS idx_datum_maskin_oee (datum, maskin_id, oee_pct);

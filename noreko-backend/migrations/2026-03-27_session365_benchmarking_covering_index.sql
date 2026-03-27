-- Session #365: Covering index for benchmarking/month-compare queries
-- Includes rasttime which was missing from idx_ibc_covering_datum_skift
-- This enables index-only scans for the CTE per_shift subqueries
ALTER TABLE rebotling_ibc
ADD INDEX idx_ibc_bench_covering (datum, skiftraknare, ibc_ok, ibc_ej_ok, runtime_plc, rasttime);

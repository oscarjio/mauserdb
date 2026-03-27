-- Session #368: Covering indexes for month-compare operator ranking subqueries
-- op2 and op3 subqueries were doing full table scans (5187 rows)
-- These covering indexes enable index-only scans (1060 rows, Using index)

CREATE INDEX IF NOT EXISTS idx_ibc_op2_covering
    ON rebotling_ibc (datum, op2, skiftraknare, ibc_ok, ibc_ej_ok, runtime_plc);

CREATE INDEX IF NOT EXISTS idx_ibc_op3_covering
    ON rebotling_ibc (datum, op3, skiftraknare, ibc_ok, ibc_ej_ok, runtime_plc);

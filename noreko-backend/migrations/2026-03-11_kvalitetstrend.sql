-- 2026-03-11_kvalitetstrend.sql
-- Index för Kvalitetstrend per operatör
-- Optimerar sökning per datum+operatör+skiftraknare i rebotling_ibc

-- Index för datum-filtrering + aggregering per skiftraknare
CREATE INDEX IF NOT EXISTS idx_rebotling_ibc_datum_op1
    ON rebotling_ibc (datum, op1, skiftraknare);

CREATE INDEX IF NOT EXISTS idx_rebotling_ibc_datum_op2
    ON rebotling_ibc (datum, op2, skiftraknare);

CREATE INDEX IF NOT EXISTS idx_rebotling_ibc_datum_op3
    ON rebotling_ibc (datum, op3, skiftraknare);

-- Index på operators för aktiva operatörer
CREATE INDEX IF NOT EXISTS idx_operators_active_number
    ON operators (active, number);

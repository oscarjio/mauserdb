-- Migration: 2026-03-11_ranking_historik.sql
-- Feature: Operatörsranking historik — leaderboard-trender vecka för vecka
--
-- Inga nya tabeller behövs — data hämtas från befintlig rebotling_ibc + operators.
-- Lägger till sammansatta index för snabba aggregeringar per operatör, vecka och ok-status.
--
-- Befintliga index (från 2026-03-11_cykeltid_heatmap.sql):
--   idx_rebotling_ibc_op1_datum (op1, datum)
--   idx_rebotling_ibc_op2_datum (op2, datum)
--   idx_rebotling_ibc_op3_datum (op3, datum)
--
-- Nya index inkluderar ok-flaggan för att snabba upp WHERE ok=1 + GROUP BY vecka.

CREATE INDEX IF NOT EXISTS idx_rebotling_ibc_op1_datum_ok
    ON rebotling_ibc(op1, datum, ok);

CREATE INDEX IF NOT EXISTS idx_rebotling_ibc_op2_datum_ok
    ON rebotling_ibc(op2, datum, ok);

CREATE INDEX IF NOT EXISTS idx_rebotling_ibc_op3_datum_ok
    ON rebotling_ibc(op3, datum, ok);

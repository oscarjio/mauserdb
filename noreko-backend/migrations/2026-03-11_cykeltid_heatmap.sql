-- Migration: 2026-03-11_cykeltid_heatmap.sql
-- Feature: Cykeltids-heatmap per operatör per timme på dygnet
--
-- Inga nya tabeller behövs — all data hämtas från befintlig rebotling_ibc-tabell.
-- Cykeltid beräknas via LAG(datum) OVER (PARTITION BY skiftraknare ORDER BY datum).
--
-- Index för att snabba upp heatmap-frågor som filtrerar på datum och läser op1/op2/op3.
-- Kolumner bekräftade: datum (DATETIME), op1/op2/op3 (INT operatörs-nummer), skiftraknare (INT).

CREATE INDEX IF NOT EXISTS idx_rebotling_ibc_op1_datum
    ON rebotling_ibc(op1, datum);

CREATE INDEX IF NOT EXISTS idx_rebotling_ibc_op2_datum
    ON rebotling_ibc(op2, datum);

CREATE INDEX IF NOT EXISTS idx_rebotling_ibc_op3_datum
    ON rebotling_ibc(op3, datum);

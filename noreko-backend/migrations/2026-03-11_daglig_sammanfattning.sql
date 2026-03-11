-- Migration: 2026-03-11_daglig_sammanfattning.sql
-- Feature: Daglig sammanfattning — VD-dashboard med daglig KPI-oversikt
--
-- Inga nya tabeller behovs. All data hamtas fran befintliga tabeller:
--   rebotling_ibc, rebotling_onoff, stopporsak_registreringar, stopporsak_kategorier, operators
--
-- Befintliga index (relevanta):
--   idx_rebotling_ibc_op1_datum_ok (op1, datum, ok) — fran ranking_historik
--   idx_rebotling_ibc_op2_datum_ok (op2, datum, ok)
--   idx_rebotling_ibc_op3_datum_ok (op3, datum, ok)
--
-- Nya index for snabba dagliga aggregeringar pa created_at.
-- created_at anvands i getReportData och getProduktionsdata (DATE(created_at) = ?).

CREATE INDEX IF NOT EXISTS idx_rebotling_ibc_created_at
    ON rebotling_ibc(created_at);

-- Index for stopporsak_registreringar pa (linje, start_time) — anvands for dagens stopp
CREATE INDEX IF NOT EXISTS idx_stopporsak_reg_linje_start
    ON stopporsak_registreringar(linje, start_time);

-- Index for rebotling_onoff pa start_time — anvands for OEE-berakhning
CREATE INDEX IF NOT EXISTS idx_rebotling_onoff_start_time
    ON rebotling_onoff(start_time);

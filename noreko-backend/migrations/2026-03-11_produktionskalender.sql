-- Migration: 2026-03-11_produktionskalender.sql
-- Feature: Produktionskalender — månadsvy med per-dag KPI:er
--
-- Inga nya tabeller behövs — data hämtas från:
--   rebotling_ibc   (datum, ok, op1, op2, op3, lopnummer, skiftraknare)
--   rebotling_onoff (datum, running)
--   rebotling_stopp (datum, orsak, sekunder)
--   operators       (number, name)
--
-- Lägger till index för snabba aggregeringar per dag och ok-status.

-- Index för månadsvy: snabb GROUP BY DATE(datum)
CREATE INDEX IF NOT EXISTS idx_rebotling_ibc_datum_ok_lopnr
    ON rebotling_ibc(datum, ok, lopnummer);

-- Index för stopporsaker per dag
CREATE INDEX IF NOT EXISTS idx_rebotling_stopp_datum_orsak
    ON rebotling_stopp(datum, orsak);

-- Index för drifttid (onoff per dag)
CREATE INDEX IF NOT EXISTS idx_rebotling_onoff_datum_running
    ON rebotling_onoff(datum, running);

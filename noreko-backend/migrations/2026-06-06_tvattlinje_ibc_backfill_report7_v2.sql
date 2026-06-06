-- 2026-06-06: Återställ rapport id=7 till D4004-baserade värden (v2 — korrigerar fel backfill)
-- Felaktig backfill satte antal_ok=245 (ibc_count-baserat). Korrekt källa är D4004 (ibc_ok).
--
-- PLC-logg för perioden:
--   Baseline ibc_ok (före 4/6 09:30): 14
--   4/6 MAX(ibc_ok) = 29  → delta = 29-14 = 15
--   5/6 MAX(ibc_ok) = 32  → delta = 32-29 =  3
--   Total delta = 18, rapport D4004 = 32 IBC OK
--
-- Per-dag andel av 32 OK:
--   4/6: round(32 * 15/18) = 27
--   5/6: 32 - 27 = 5

-- Återställ _daglig per dag
UPDATE tvattlinje_skiftrapport_daglig
SET antal_ok = 27
WHERE skiftrapport_id = 7 AND dag = '2026-06-04';

UPDATE tvattlinje_skiftrapport_daglig
SET antal_ok = 5
WHERE skiftrapport_id = 7 AND dag = '2026-06-05';

-- Återställ huvudrapporten (antal_ej_ok=3, omtvaatt=0 — originaldatan från PLC)
UPDATE tvattlinje_skiftrapport
SET antal_ok = 32,
    totalt   = 32 + COALESCE(antal_ej_ok, 0) + COALESCE(omtvaatt, 0)
WHERE id = 7;

-- Kontroll
SELECT id, antal_ok, antal_ej_ok, omtvaatt, totalt FROM tvattlinje_skiftrapport WHERE id = 7;
SELECT skiftrapport_id, dag, antal_ok, drifttid_min, rast_min FROM tvattlinje_skiftrapport_daglig WHERE skiftrapport_id = 7;

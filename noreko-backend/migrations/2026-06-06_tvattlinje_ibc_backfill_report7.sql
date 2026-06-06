-- 2026-06-06: Backfill rapport id=7 — IBC från ibc_count (D4004/ibc_ok fruset)
-- Kontrollerat av Oscar mot PLC-events:
--   4/6: 165 events, MAX(ibc_count) = 170 fysiska IBC
--   5/6:  71 events, MAX(ibc_count) =  75 fysiska IBC
--   Totalt: 245 IBC (rapport hade 32 från fruset D4004-register)

-- Uppdatera _daglig per dag
UPDATE tvattlinje_skiftrapport_daglig
SET antal_ok = 170
WHERE skiftrapport_id = 7 AND dag = '2026-06-04';

UPDATE tvattlinje_skiftrapport_daglig
SET antal_ok = 75
WHERE skiftrapport_id = 7 AND dag = '2026-06-05';

-- Uppdatera huvudrapporten
UPDATE tvattlinje_skiftrapport
SET antal_ok = 245,
    totalt   = 245 + COALESCE(antal_ej_ok, 0) + COALESCE(omtvaatt, 0)
WHERE id = 7;

-- Kontroll
SELECT id, antal_ok, antal_ej_ok, omtvaatt, totalt FROM tvattlinje_skiftrapport WHERE id = 7;
SELECT skiftrapport_id, dag, antal_ok, drifttid_min, rast_min FROM tvattlinje_skiftrapport_daglig WHERE skiftrapport_id = 7;

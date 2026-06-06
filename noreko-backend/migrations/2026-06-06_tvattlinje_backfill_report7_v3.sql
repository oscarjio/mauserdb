-- 2026-06-06: Slutgiltigt korrekt värde rapport id=7 — ibc_count (fysisk genomströmning) är sanning
-- Källa: puck/shelly-räknare per dag (resettas dagligen):
--   4/6: MAX(ibc_count) = 170
--   5/6: MAX(ibc_count) =  75
--   Total = 245
--
-- D4004=32 är ett underrapporterat PLC-registervärde (fryst) — PLC-programmeraren
-- kan verifiera detta i plc_raw-tabellen (ändra ALDRIG plc_raw).
--
-- antal_ej_ok = 2 (D4005), omtvaatt = 0 → totalt = 245+2+0 = 247

UPDATE tvattlinje_skiftrapport_daglig
SET antal_ok = 170
WHERE skiftrapport_id = 7 AND dag = '2026-06-04';

UPDATE tvattlinje_skiftrapport_daglig
SET antal_ok = 75
WHERE skiftrapport_id = 7 AND dag = '2026-06-05';

UPDATE tvattlinje_skiftrapport
SET antal_ok = 245,
    totalt   = 245 + COALESCE(antal_ej_ok, 0) + COALESCE(omtvaatt, 0)
WHERE id = 7;

-- Kontroll
SELECT id, antal_ok, antal_ej_ok, omtvaatt, totalt FROM tvattlinje_skiftrapport WHERE id = 7;
SELECT skiftrapport_id, dag, antal_ok, drifttid_min, rast_min FROM tvattlinje_skiftrapport_daglig WHERE skiftrapport_id = 7;

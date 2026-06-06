-- 2026-06-06: SLUTGILTIGT — lagra PLC-råvärden rakt av för rapport id=7
-- Arkitekturändring: inga DB-sidiga omräkningar. D4004/D4005 är sanningen.
--
-- Från plc_raw (tvattlinje_plc_raw, event_type='skiftrapport', närmast rapport 7):
--   D4004 (ibc_ok)    = 32   — PLC-programmeraren noteras om underrapportering
--   D4005 (ibc_ej_ok) = 2
--   D4006 (omtvaatt)  = 0
--   totalt             = 34
--
-- OBS: Rör ALDRIG tvattlinje_plc_raw — den är ren append-only logg för PLC-programmeraren.

-- Rapport-rad: PLC-råvärden
UPDATE tvattlinje_skiftrapport
SET antal_ok    = 32,
    antal_ej_ok = 2,
    omtvaatt    = 0,
    totalt      = 34
WHERE id = 7;

-- Ta bort stale daglig-fördelning (beräknades av gamla backend-logiken, används ej längre)
DELETE FROM tvattlinje_skiftrapport_daglig WHERE skiftrapport_id = 7;

-- OBS: drifttid bör verifieras mot plc_raw D4007-värdet för rapport 7.
-- Kör: SELECT registers FROM tvattlinje_plc_raw WHERE event_type='skiftrapport' ORDER BY id DESC LIMIT 5;
-- och hämta D4007-värdet (index 7 i registers-arrayen).

-- Kontroll
SELECT id, antal_ok, antal_ej_ok, omtvaatt, totalt, drifttid FROM tvattlinje_skiftrapport WHERE id = 7;
SELECT COUNT(*) AS daglig_rows FROM tvattlinje_skiftrapport_daglig WHERE skiftrapport_id = 7;

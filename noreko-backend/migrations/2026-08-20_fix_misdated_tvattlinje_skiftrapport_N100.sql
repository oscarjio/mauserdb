-- =============================================================================
-- N100 — Feldaterad tvattlinje-skiftrapport (carryover 08-10 -> 08-11)
-- Datum: 2026-08-20
-- =============================================================================
-- SYMPTOM (rapporterat live):
--   /tvattlinje/statistik dag 2026-08-11 visade 291 IBC (ska vara 140),
--   kortid ~17.4h (omöjligt, cap 10h) och "151 förlorade signaler".
--
-- FAKTISK ROTORSAK (verifierad mot dev-API, EJ LAG/PARTITION-mönstret):
--   PLC-datan (tvattlinje_ibc) är REN — 08-10: 152 IBC (skift 2, ibc_count 1..152),
--   08-11: 140 IBC (skift 3, ibc_count 1..140), ingen carryover över midnatt.
--   Felet är EN skiftrapport: 08-10:s skift auto-rapporterades 08-11 07:00:38 och
--   lagrades med datum=2026-08-11 (skift 2, totalt=151, drifttid=1390min=23h korrupt).
--   DATE(datum)-bucketing gav då 08-11: SR-golv max(PLC140, SR 140+151)=291,
--   drifttids-query summerade båda skiften, missed = 291-140 = 151.
--   Skapades av PLC-kod FÖRE skiftdatum-fixen (commit a7f89941, 2026-08-06) hann
--   deployas till Pi:n. Nyare rapporter (08-03/08-04, samma morgon-auto-mönster)
--   fick korrekt produktionsdatum → återfall är redan förhindrat vid källan.
--
-- Denna migrering rättar ENBART historisk data. Ingen kodändring krävs för framtiden.
--
-- KÖR FÖRE (kontroll):
--   SELECT id, datum, created_at, skiftraknare, totalt, drifttid
--   FROM tvattlinje_skiftrapport
--   WHERE created_at BETWEEN '2026-08-11 06:00:00' AND '2026-08-11 08:00:00';
-- =============================================================================

-- 1) Flytta den feldaterade rapporten till rätt produktionsdag (2026-08-10).
--    Targetad på exakt rad (created_at + skiftraknare + totalt) → rör inget annat.
--    sent_inskickad=1 = efterregistrerad (rapport inskickad annan dag än skiftet).
UPDATE tvattlinje_skiftrapport
SET datum = '2026-08-10',
    sent_inskickad = 1
WHERE created_at = '2026-08-11 07:00:38'
  AND skiftraknare = 2
  AND totalt = 151
  AND datum = '2026-08-11';

-- 2) Sanera korrupta drifttider (> 600 min = > 10h, omöjligt per domänregel
--    "max ~10h/skift"). Artefakt av runtime_plc ackumulerad över idle vid
--    morgon-auto-rapporter (drabbar 08-03=1393, 08-04=1378, 08-10/phantom=1390).
--    Sätt till FAKTISK PLC-nettotid (MAX(runtime_plc)) för rapportens produktionsdag.
--    Uppdaterar bara rader där en giltig PLC-tid finns för dagen → aldrig -> 0.
UPDATE tvattlinje_skiftrapport sr
JOIN (
    SELECT DATE(datum) AS dag,
           LEAST(GREATEST(0, MAX(runtime_plc)), 600) AS real_min
    FROM tvattlinje_ibc
    WHERE runtime_plc IS NOT NULL AND runtime_plc > 0
    GROUP BY DATE(datum)
) r ON r.dag = DATE(sr.datum)
SET sr.drifttid = r.real_min
WHERE sr.drifttid > 600
  AND r.real_min > 0;

-- =============================================================================
-- KÖR EFTER (verifiering — förväntat):
--   08-10: 1 rapport (skift 2, totalt 151, drifttid ~434min/7.2h)
--   08-11: 1 rapport (skift 3, totalt 140, drifttid 498min/8.3h)
--   SELECT datum, COUNT(*) n, SUM(totalt) sum_ibc, SUM(drifttid) sum_drift
--   FROM tvattlinje_skiftrapport WHERE datum IN ('2026-08-10','2026-08-11')
--   GROUP BY datum;
--
-- OBS statistik-cache: dagsvyn cachar avslutade dagar 7 dygn per CODE_VERSION.
-- Efter migrering, rensa cache eller vänta på nästa deploy (ny CODE_VERSION):
--   rm -f <backend>/cache/tvattlinje_statistics_*_2026-08-1*.json
-- =============================================================================

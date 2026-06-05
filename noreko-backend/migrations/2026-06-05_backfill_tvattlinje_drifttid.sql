-- Backfill: korrigerar rapporten från 2026-06-05 (inskickad ~12:56)
-- Problemet: drifttid=1629 min (råvärde ackumulerat PLC-räknare D4007 över flera dygn).
-- Fixar: beräknar delta-drifttid inom rapportfönstret + populerar _daglig.
--
-- Kör EFTER: 2026-06-05_tvattlinje_flerdagars_skiftrapport.sql
-- OBS: CREATE TABLE IF NOT EXISTS inkluderas nedan för säkerhets skull.

-- Säkra att _daglig-tabellen finns (idempotent)
CREATE TABLE IF NOT EXISTS tvattlinje_skiftrapport_daglig (
  id               INT NOT NULL AUTO_INCREMENT,
  skiftrapport_id  INT NOT NULL,
  dag              DATE NOT NULL,
  antal_ok         INT NOT NULL DEFAULT 0,
  antal_ej_ok      INT NOT NULL DEFAULT 0,
  omtvaatt         INT NOT NULL DEFAULT 0,
  drifttid_min     INT NOT NULL DEFAULT 0,
  rast_min         INT NOT NULL DEFAULT 0,
  kalla            ENUM('plc_event','pro_rata') NOT NULL DEFAULT 'plc_event',
  PRIMARY KEY (id),
  UNIQUE KEY uq_rapport_dag (skiftrapport_id, dag),
  KEY idx_dag (dag),
  KEY idx_skiftrapport_id (skiftrapport_id),
  CONSTRAINT fk_tvattlinje_daglig_rapport
    FOREIGN KEY (skiftrapport_id) REFERENCES tvattlinje_skiftrapport(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Identifiera rapporten
SET @rid = (SELECT id    FROM tvattlinje_skiftrapport WHERE datum = '2026-06-05' ORDER BY id DESC LIMIT 1);
SET @rca = (SELECT created_at FROM tvattlinje_skiftrapport WHERE id = @rid);

-- Föregående rapports created_at (fönstrets startgräns)
SET @pca = (SELECT COALESCE(
    (SELECT MAX(created_at) FROM tvattlinje_skiftrapport WHERE id < @rid),
    DATE_SUB(@rca, INTERVAL 24 HOUR)
));

-- Baseline: max PLC-värden PRECIS INNAN fönstret (för korrekt dag-1-delta)
SET @b_rt   = (SELECT COALESCE(MAX(COALESCE(runtime_plc, 0)), 0) FROM tvattlinje_ibc WHERE datum <= @pca);
SET @b_rast = (SELECT COALESCE(MAX(COALESCE(rasttime,    0)), 0) FROM tvattlinje_ibc WHERE datum <= @pca);
SET @b_ibc  = (SELECT COALESCE(MAX(ibc_count),               0) FROM tvattlinje_ibc WHERE datum <= @pca);

-- Max PLC-värden INOM rapportfönstret
SET @e_rt   = (SELECT COALESCE(MAX(COALESCE(runtime_plc, 0)), @b_rt)   FROM tvattlinje_ibc WHERE datum > @pca AND datum <= @rca);
SET @e_rast = (SELECT COALESCE(MAX(COALESCE(rasttime,    0)), @b_rast) FROM tvattlinje_ibc WHERE datum > @pca AND datum <= @rca);

-- Delta-värden (tid i minuter inom fönstret)
SET @d_rt   = GREATEST(0, @e_rt   - @b_rt);
SET @d_rast = GREATEST(0, @e_rast - @b_rast);

-- Period-gränser (första och sista PLC-event i fönstret)
SET @ps = (SELECT MIN(datum) FROM tvattlinje_ibc WHERE datum > @pca AND datum <= @rca);
SET @pe = (SELECT MAX(datum) FROM tvattlinje_ibc WHERE datum > @pca AND datum <= @rca);

-- Uppdatera rapporten: drifttid/rasttime till korrekta delta-värden + period-metadata
UPDATE tvattlinje_skiftrapport SET
    drifttid     = @d_rt,
    rasttime     = @d_rast,
    period_start = @ps,
    period_end   = @pe,
    flerdagars   = IF(DATE(@ps) <> DATE(@pe), 1, 0),
    antal_dagar  = DATEDIFF(DATE(@pe), DATE(@ps)) + 1
WHERE id = @rid;

-- Populera _daglig: fördela IBC/drifttid per dag via ibc_count-delta
-- Baseline-dag (dag innan fönstret) ingår för att ge LAG ett korrekt startvärde.
INSERT INTO tvattlinje_skiftrapport_daglig
  (skiftrapport_id, dag, antal_ok, antal_ej_ok, omtvaatt, drifttid_min, rast_min, kalla)
WITH
daily_agg AS (
  SELECT
    DATE(datum)                           AS dag,
    MAX(ibc_count)                        AS max_ibc,
    MAX(COALESCE(runtime_plc, 0))         AS max_rt,
    MAX(COALESCE(rasttime,    0))         AS max_rast
  FROM tvattlinje_ibc
  WHERE datum > @pca AND datum <= @rca
  GROUP BY DATE(datum)
),
with_baseline AS (
  SELECT DATE_SUB(MIN(dag), INTERVAL 1 DAY) AS dag,
         @b_ibc AS max_ibc, @b_rt AS max_rt, @b_rast AS max_rast
  FROM daily_agg
  UNION ALL
  SELECT dag, max_ibc, max_rt, max_rast FROM daily_agg
),
deltas AS (
  SELECT dag,
    GREATEST(0, max_ibc  - LAG(max_ibc)  OVER (ORDER BY dag)) AS d_ibc,
    GREATEST(0, max_rt   - LAG(max_rt)   OVER (ORDER BY dag)) AS d_rt,
    GREATEST(0, max_rast - LAG(max_rast) OVER (ORDER BY dag)) AS d_rast
  FROM with_baseline
),
period_deltas AS (
  SELECT * FROM deltas WHERE dag >= DATE(@ps) AND d_ibc IS NOT NULL
),
totals AS (SELECT NULLIF(SUM(d_ibc), 0) AS tot_ibc FROM period_deltas)
SELECT
  @rid,
  pd.dag,
  GREATEST(0, ROUND(r.antal_ok    * pd.d_ibc / t.tot_ibc)) AS antal_ok,
  GREATEST(0, ROUND(r.antal_ej_ok * pd.d_ibc / t.tot_ibc)) AS antal_ej_ok,
  GREATEST(0, ROUND(r.omtvaatt    * pd.d_ibc / t.tot_ibc)) AS omtvaatt,
  GREATEST(0, pd.d_rt)   AS drifttid_min,
  GREATEST(0, pd.d_rast) AS rast_min,
  'plc_event' AS kalla
FROM period_deltas pd, totals t
CROSS JOIN (SELECT antal_ok, antal_ej_ok, omtvaatt FROM tvattlinje_skiftrapport WHERE id = @rid) r
ON DUPLICATE KEY UPDATE
  antal_ok     = VALUES(antal_ok),
  antal_ej_ok  = VALUES(antal_ej_ok),
  omtvaatt     = VALUES(omtvaatt),
  drifttid_min = VALUES(drifttid_min),
  rast_min     = VALUES(rast_min),
  kalla        = VALUES(kalla);

-- Kontroll: visa resultatet
SELECT
  r.id, r.datum, r.drifttid AS drifttid_min_korr,
  CONCAT(FLOOR(r.drifttid/60),'h ', MOD(r.drifttid,60),'m') AS drifttid_hr,
  r.rasttime, r.period_start, r.period_end, r.flerdagars, r.antal_dagar,
  d.dag, d.antal_ok, d.drifttid_min, d.rast_min, d.kalla
FROM tvattlinje_skiftrapport r
LEFT JOIN tvattlinje_skiftrapport_daglig d ON d.skiftrapport_id = r.id
WHERE r.id = @rid;

-- Data-bugg: tvattlinje_skiftrapport id=8 har datum=2026-06-09 men PLC-data (plc_start/plc_end)
-- visar 2026-06-08. Resulterade i felaktigt 153 IBC + 24h runtime för 06-09 i statistik
-- samt fel bästa_dag. Korrigerar datum och drifttid till faktiska värden.
UPDATE tvattlinje_skiftrapport
SET datum = '2026-06-08',
    drifttid = 376
WHERE id = 8
  AND datum = '2026-06-09'
  AND drifttid = 1523;

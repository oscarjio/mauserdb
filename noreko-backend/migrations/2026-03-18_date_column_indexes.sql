-- Session #172: Index pa datumkolumner for att optimera date-range queries
-- Manga queries anvander DATE(datum) BETWEEN ... som forhindrar index-anvandning.
-- Dessa index hjalper nar queries skrivs om till datum >= X AND datum < Y.

-- rebotling_ibc.datum — anvands i DagligSammanfattning, KassationsDrilldown,
-- ProduktionsDashboard, VdDashboard, WeeklyReport, MaskinhistorikController m.fl.
ALTER TABLE rebotling_ibc ADD INDEX idx_datum (datum);

-- rebotling_underhallslogg.datum — UnderhallsloggController manadschart
ALTER TABLE rebotling_underhallslogg ADD INDEX idx_datum (datum);

-- rebotling_skiftrapport.datum — OperatorJamforelseController, SkiftrapportController
ALTER TABLE rebotling_skiftrapport ADD INDEX idx_datum (datum);

-- stoppage_log.start_time redan har index (idx_start), OK

-- stopporsak_registreringar.start_time — ParetoController
ALTER TABLE stopporsak_registreringar ADD INDEX idx_start_time (start_time);

-- kassationsorsak_registreringar.datum — KassationsDrilldownController
ALTER TABLE kassationsorsak_registreringar ADD INDEX idx_datum (datum);

-- Notera: Alla queries med DATE(datum) = ? bor skrivas om till
-- datum >= '2026-01-01 00:00:00' AND datum < '2026-01-02 00:00:00'
-- for att kunna utnyttja dessa index. Det ar en storre refaktor som
-- bor goras i en framtida session.

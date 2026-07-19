-- 2026-07-19: Index på tvattlinje_skiftrapport.created_at
--
-- Bakgrund: FIX D (RemoteAgg) flyttar run=rapportlista (LineSkiftrapportController::getReports)
-- från Raspberry Pi:n till den VPS-lokala DB:n. getReports använder en korrelerad subquery
-- av typen  MAX(r2.created_at) WHERE r2.created_at < r.created_at  (föregående skifts tidpunkt),
-- som med LIMIT 1000 kan bli upp till 1000 korrelerade lookups. Tabellen har idag bara
-- idx_datum + idx_user_id → created_at-subqueryn saknar täckande index.
--
-- Litet dataset i dagsläget, men detta index förhindrar full-scans som annars kan förvärra
-- 503/max_connections när rapportlist-queryn nu körs på VPS-DB:n.
--
-- OBS (ägare): ALTER ADD INDEX kan låsa/kopiera tabellen kort. Kör vid lågtrafik.
-- Om indexet redan finns (annan session) kastar MySQL fel 1061 — ofarligt, ignorera i så fall.

ALTER TABLE `tvattlinje_skiftrapport`
  ADD INDEX `idx_created_at` (`created_at`);

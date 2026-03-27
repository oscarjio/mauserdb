-- Session #353: Composite index för att eliminera filesort i getLiveStats
-- rebotling_onoff: covering index för skiftraknare + datum + running
-- Eliminerar filesort i runtime-beräkningen och ORDER BY datum DESC-subselects
ALTER TABLE rebotling_onoff
  ADD INDEX idx_onoff_skift_datum_running (skiftraknare, datum, running);

-- rebotling_ibc: covering index för skiftraknare + datum (ibc_hour query)
-- Optimerar COUNT(*) med WHERE skiftraknare = X AND datum >= Y
ALTER TABLE rebotling_ibc
  ADD INDEX idx_ibc_skift_datum (skiftraknare, datum);

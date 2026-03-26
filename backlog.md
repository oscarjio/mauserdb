# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-26)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Nasta (session #350):
- [ ] **station_id-referens fix** — A: HistoriskSammanfattningController + SkiftjamforelseController anvander COALESCE(station_id,1) men kolumnen finns ej i rebotling_ibc — ta bort/hardkoda
- [ ] **Responsiv design-sweep** — B: alla sidor pa mobil/tablet
- [ ] **Prestandaoptimering** — A: identifiera langa queries, N+1-problem, indexering
- [ ] **Felhantering UI** — B: tomma tillstand, laddningsindikatorer, felmeddelanden
- [ ] **Chart.js enhetlig styling** — B: alla grafer med samma fargpalett och dark theme
- [ ] **Endpoint-svarstider** — A: logga och optimera endpoints over 1s

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer

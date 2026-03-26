# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-26)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Pagaende (session #349):
- [~] **15 ogranskade controllers djupgranskning** — A: SQL+ep+auth (Morgonrapport, DagligBriefing, Produktionspuls, ProduktionsPrognos, Favoriter, Skiftplanering, Underhallslogg, Skiftoverlamning, RebotlingStationsdetalj, HistoriskSammanfattning, StatistikOverblick, StatistikDashboard, Skiftjamforelse, MyStats, MinDag)
- [~] **End-to-end rebotling-flodet** — A: backend-data, B: UI-flode
- [~] **20+ ogranskade frontend-sidor** — B: VD/executive, rebotling UI, statistik, operator/personal
- [~] **produktion_procent undersok** — B: ar det kumulativt? Fixa berakning/visning

### Nasta (session #350):
- [ ] **Responsiv design-sweep** — alla sidor pa mobil/tablet
- [ ] **Prestandaoptimering** — identifiera langa queries, N+1-problem
- [ ] **Felhantering UI** — tomma tillstand, laddningsindikatorer, felmeddelanden
- [ ] **Chart.js enhetlig styling** — alla grafer med samma fargpalett och dark theme
- [ ] **Endpoint-svarstider** — logga och optimera endpoints over 1s

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer

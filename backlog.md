# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-26)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Pagaende (session #348):
- [~] **Operator-controllers djupgranskning (7st)** — A: SQL+endpoints+auth, B: 10 operator-UI-sidor
- [~] **Kvalitet-controllers (4st) + UI** — A: SQL+endpoints, B: kvalitetstrend+analytics UI
- [~] **Analytics-controllers (7st) + UI** — A: Heatmap/Pareto/Kassation/Drilldown/StopptidSQL+ep, B: UI
- [~] **Admin-sidor granskning** — B: bonus-admin, news-admin, vpn-admin, feature-flag-admin
- [~] **Rebotling-UI (ej live)** — B: sammanfattning, kvalitets-trendbrott, statistik-sidor

### Nasta (session #349):
- [ ] **End-to-end testning rebotling-flodet** — A+B: fran PLC-data till dashboard till rapport
- [ ] **Responsiv design-sweep** — B: alla sidor pa mobil/tablet
- [ ] **Resterande ogranskade controllers** — A: MorgonrapportController, DagligBriefingController, ProduktionspulsController, ProduktionsPrognosController, FavoriterController, SkiftplaneringController, UnderhallsloggController, SkiftoverlamningController, RebotlingStationsdetaljController, HistoriskSammanfattningController, StatistikOverblickController, StatistikDashboardController, SkiftjamforelseController
- [ ] **MyStatsController + MinDagController djupgranskning** — A: SQL+ep, B: UI

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer

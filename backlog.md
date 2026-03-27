# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-27)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Nasta (session #353):
- [ ] **Rebotling getLiveStats vidare opt** — A: 560ms fortfarande hog, undersok caching/async queries
- [ ] **PHP error_log audit** — A: koppla upp mot dev, granska PHP error logs for varningar/notices
- [ ] **Saknade DB-index** — A: EXPLAIN pa alla tunga queries, lagg till index dar det behövs
- [ ] **Formulärvalidering frontend** — B: required-fält, min/max, feedback vid felaktig input
- [ ] **Responsiv granskning 2.0** — B: testa alla sidor pa 320px/768px/1024px, fixa overflow/layout
- [ ] **Print-styling** — B: @media print for rapporter/statistik-sidor

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer

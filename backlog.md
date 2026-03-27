# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-27)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #355):
- [x] **SQL-query granskning** — 113 controllers granskade, 0 kritiska mismatches
- [x] **Endpoint-testning** — 52 endpoints testade, 0 st 500-fel
- [x] **WCAG 2.1 AA kontrast** — 216 filer fixade (#718096→#8fa3b8, #4a5568→#8fa3b8)
- [x] **Bundle-analys** — 67.8 KB initial load, alla tunga deps lazy-loadade
- [x] **Table-responsive** — 14 tabeller fixade i 11 HTML-filer
- [x] **Global ErrorHandler** — utokad med toast for okontrollerade fel

### Nasta (session #356):
- [ ] **PHP error_log access** — behover sudo eller alternativ loggvag, koordinera med agaren
- [ ] **E2E regressionstest** — kora alla 50 tester efter session #355 fixar
- [ ] **Lazy loading implementation** — baserat pa bundle-analys fran session #355
- [ ] **HTTP interceptor audit** — verifiera auth-headers och error handling globalt
- [ ] **Caching-strategi** — HTTP cache headers + Angular service caching for tunga endpoints

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer

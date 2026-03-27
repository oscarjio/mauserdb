# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-27)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #366):
- [x] Full endpoint-stresstest 129 tester 0x500 + PHP controller-audit 112 filer
- [x] Integration test API vs DB — perfekt match (ibc_today, historik mars)
- [x] Error/404-hantering — okand action→404, SQLi→400, XSS→404, konsistent JSON
- [x] Data-korrekthet UI vs DB — alla nyckel-endpoints verifierade, 0 diskrepanser
- [x] Angular kodgranskning 92 services + guards + interceptors — inga problem
- [x] Chart-datakorrekthet 115 filer — korrekt mappning, svenska etiketter, dark theme

### Nasta (session #367):
- [ ] **Performance-djupdyk** — EXPLAIN-audit pa resterande slow queries (month-compare 1032ms)
- [ ] **Rebotling operatorsbonus-granskning** — verifiera att bonus beraknas rattvist per operator
- [ ] **Admin-floden end-to-end** — testa CRUD for operatorer, mal, skift via API
- [ ] **Caching-strategi** — granska PHP file cache, identifiera endpoints som bor cachas
- [ ] **Frontend bundle-optimering** — analysera 8.8MB bundle, identifiera lazy loading-mojligheter

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer

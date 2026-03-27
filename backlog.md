# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-27)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Nasta (session #355):
- [ ] **PHP error_log access** — behover sudo eller alternativ loggvag, koordinera med agaren
- [ ] **SQL-query granskning mot prod_db_schema.sql** — verifiera alla controllers mot facit
- [ ] **Error boundary/global error handler** — Angular ErrorHandler + backend 500-svar
- [ ] **Performance audit** — Lighthouse, bundle-analys, lazy loading-granskning
- [ ] **WCAG 2.1 AA kontrast-ratios** — screen reader-test, aria-live regioner
- [ ] **Unused imports cleanup** — HostListener-imports i 4 komponenter (diagnostik-varning)

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer

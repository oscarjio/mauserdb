# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-27)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Pagaende (session #367):
- [~] **Performance-djupdyk** — EXPLAIN-audit month-compare 1032ms (Worker A)
- [~] **Rebotling operatorsbonus-granskning** — rattvis berakning per operator (Worker B)
- [~] **Admin-floden end-to-end** — CRUD operatorer/mal/skift via API (Worker A)
- [~] **Caching-strategi** — PHP file cache granskning (Worker A)
- [~] **Frontend bundle-optimering** — 8.8MB bundle, lazy loading (Worker B)

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer

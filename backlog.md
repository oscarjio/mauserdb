# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-27)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Nästa (session #354):
- [ ] **DATE()-fixar i övriga controllers** — A: 136 kvarvarande DATE(datum) BETWEEN → direktjämförelser (index-användning)
- [ ] **PHP error_log access** — A: behöver sudo-access eller alternativ loggväg, koordinera med ägaren
- [ ] **getLiveStats → <200ms** — A: undersök opcache, persistent DB-connections, query parallelism
- [ ] **Keyboard navigation audit** — B: Tab-ordning, focus-visible, skip-links
- [ ] **Loading states UX** — B: skeleton loaders/spinners vid dataladddning, tom-state meddelanden
- [ ] **Chart.js tooltip touch-stöd** — B: mobilgrafer, pinch-zoom, touch-tooltips

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer

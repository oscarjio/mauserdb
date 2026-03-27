# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-27)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Pågående (session #364):
- [WIP] **API vs DB diskrepans** — mars 946 vs 1058, undersök PHP-limit/filter (Worker A)
- [WIP] **Slow endpoints optimering** — exec-dashboard 1.5s, EXPLAIN + index (Worker A)
- [WIP] **Full endpoint-stresstest** — alla endpoints curl mot dev (Worker A)
- [WIP] **Mobile responsivitet** — alla sidor 375px/768px viewport (Worker B)
- [WIP] **VD Dashboard + UX-granskning** — alla sidor dark theme, lifecycle, grafer (Worker B)

### Nästa (session #365):
- [ ] **Integration test suite** — automatiserade tester för kritiska API-flöden
- [ ] **Error page / 404-hantering** — granska att alla routes har fallback
- [ ] **API rate limiting** — skydd mot överbelastning
- [ ] **Loggrotation** — granska PHP error logs storlek

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer

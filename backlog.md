# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-27)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Nästa (session #364):
- [ ] **API vs DB diskrepans** — mars visar 946 cykler via API men 1058 i DB. Undersök PHP output/JSON limit i rebotling-statistik endpoint
- [ ] **Slow endpoints optimering** — exec-dashboard 1.5s, all-lines-status 614ms, today-snapshot 513ms. Profil och optimera
- [ ] **Mobile responsivitet** — testa alla sidor på 375px/768px viewport, fixa layout-problem
- [ ] **Integration test suite** — automatiserade tester för kritiska API-flöden (auth, rebotling CRUD)
- [ ] **PHP dependency audit** — granska composer.json, uppdatera föråldrade paket, kolla CVE:er

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer

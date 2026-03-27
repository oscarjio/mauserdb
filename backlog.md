# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-27)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Nästa (session #363):
- [ ] **Integration test suite** — skapa automatiserade tester för kritiska API-flöden (auth, rebotling CRUD, operatörer)
- [ ] **Frontend lazy loading audit** — verifiera att alla routes lazy-loadar korrekt, mät initial load time
- [ ] **PHP dependency audit** — granska composer.json, uppdatera föråldrade paket, kolla CVE:er
- [ ] **Mobile responsivitet** — testa alla sidor på 375px/768px viewport, fixa layout-problem
- [ ] **API rate limiting** — implementera enkel rate limit per IP för att skydda mot missbruk

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer

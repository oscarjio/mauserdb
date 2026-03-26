# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-26)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Nasta (session #351):
- [ ] **Oanvanda variabler/funktioner** — A: rensa $prev, $stationId i HistoriskSammanfattning + getProduktionPerSkiftSingleDay i Skiftjamforelse
- [ ] **Rebotling E2E regressionstest** — A: automatiserat curl-testskript for alla kritiska endpoints
- [ ] **Operatorsbonus-berakning verifiering** — A: granska att bonuslogiken matchar prod-data korrekt
- [ ] **Mobil UX-test** — B: testa alla sidor pa riktig mobilupploesning (375px), verifiera responsiv design
- [ ] **Laddningstider frontend** — B: lazy loading, bundle size, initial load time
- [ ] **Navigationsmenyn** — B: granska att alla sidor ar atkomliga, inga trasiga lankar

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer

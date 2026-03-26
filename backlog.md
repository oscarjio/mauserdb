# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-26)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Nasta (session #344):
- [ ] **Granska dashboard/hem-sida** — A: endpoints+SQL, B: UI+widgets+realtidsdata
- [ ] **Granska rebotling-live adjacenta sidor** — B: UI runt live-vyn (EJ sjalva live-sidan)
- [ ] **Prestandaprofilering** — A: identifiera langsamma endpoints (>500ms), optimera SQL
- [ ] **Granska auth/anvandare/roller** — A: endpoints+SQL, B: UI+login+behorighetskontroll
- [ ] **Accessibility-sweep** — B: kontrast, aria-labels, keyboard-nav i alla sidor

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer

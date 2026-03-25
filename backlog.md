# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-25)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync backend+dist till dev.mauserdb.com (se memory/feedback_deploy_workflow.md)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Pagaende (session #328 — Worker A + Worker B):
- [ ] **Verifiera produktion%-fix pa dev** — deploy + curl, kontrollera 0-100% (Worker A)
- [ ] **Frontend deploy retry** — bygg + rsync dist (Worker B)
- [ ] **PHP date/timezone audit** — inkonsekvent datumhantering i controllers (Worker A)
- [ ] **PHP authorization audit** — saknade behoorighetskontroller (Worker A)
- [ ] **Angular form validation audit** — saknad validering i formularsidor (Worker B)
- [ ] **Frontend UX-genomgang** — dark theme, svenska, tomma tillstand, responsivitet (Worker B)
- [ ] **Full endpoint-test med curl** — testa ALLA endpoints, fixa 500-fel (Worker A)

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer

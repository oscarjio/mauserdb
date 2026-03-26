# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-26)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync backend+dist till dev.mauserdb.com (se memory/feedback_deploy_workflow.md)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Nasta (session #338):
- [ ] **Granska rebotling-statistik berakningar** — produktion_procent, OEE, trender — stammer data med prod DB?
- [ ] **Granska skiftrapport-UI** — korrekt data, formattering, PDF-export fungerar?
- [ ] **Granska admin-sidor UI** — alla formular fungerar, validering, CRUD-operationer
- [ ] **Performance-test** — langsammaste endpoints (leaderboard 1.08s), kan optimeras?
- [ ] **Granska Angular routing + guards** — alla rutter skyddade, lazy loading korrekt?

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer

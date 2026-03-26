# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-26)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync backend+dist till dev.mauserdb.com (se memory/feedback_deploy_workflow.md)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Nasta (session #332):
- [ ] **Granska alla Chart.js-konfigurationer** — dark theme farger, tooltips pa svenska, responsivt
- [ ] **Testa alla formularsidor** — validering, felmeddelanden, submit-flode
- [ ] **Granska skiftoverlamning end-to-end** — data fran PLC till visning, ratt berakningar
- [ ] **Verifiera att gamification/ranking visar korrekta poang** — berakningslogik i backend
- [ ] **Granska alla tabeller** — sortering, paginering, tomma-tillstand
- [ ] **Deploy alla session #331 fixar till dev** — backend rsync misslyckades pga SSH timeout

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer

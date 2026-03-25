# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-25)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync backend+dist till dev.mauserdb.com (se memory/feedback_deploy_workflow.md)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Hogsta prioritet (session #328):
- [ ] **Verifiera produktion%-fix pa dev** — Worker A fixade kumulativ->delta, kontrollera att graferna nu visar ratt varden (0-100%)
- [ ] **Frontend deploy retry** — Worker B:s deploy failade pga SSH timeout, deploy manuellt
- [ ] **PHP date/timezone audit** — inkonsekvent datumhantering i controllers
- [ ] **PHP authorization audit** — saknade behoorighetskontroller
- [ ] **Angular form validation audit** — saknad validering

### Klart (session #327):
- [x] Statistik produktion%-berakning — kumulativ->delta fix i RebotlingController + RebotlingAnalyticsController
- [x] Backend endpoint fulltest — 107 endpoints testade, 0 kvarstaende 500-fel
- [x] SQL vs schema audit — 31 refs till saknade tabeller (alla guardade), migration koord
- [x] Skiftrapport grundlig test — 3 UI-buggar fixade (duplicerad dropdown, aria-label, CSS)
- [x] Frontend UX-genomgang — dark theme, lifecycle, data bindings granskade

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer

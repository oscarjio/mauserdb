# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-25)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync backend+dist till dev.mauserdb.com (se memory/feedback_deploy_workflow.md)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Pagaende (session #327 — Worker A + B):
- [~] **Statistik produktion%-berakning** — Worker A understoker kumulativ data och fixar backend-berakning
- [~] **Backend endpoint fulltest** — Worker A curlar VARJE endpoint, fixar 500-fel
- [~] **SQL vs schema audit** — Worker A jamfor queries mot prod_db_schema.sql
- [~] **Skiftrapport grundlig test** — Worker B granskar alla flikar, fixar UI/data
- [~] **Frontend UX-genomgang** — Worker B granskar ALLA sidor, dark theme, navigation, data

### Nasta (session #328+):
- [ ] **PHP date/timezone audit** — inkonsekvent datumhantering
- [ ] **PHP authorization audit** — saknade behoorighetskontroller
- [ ] **Angular form validation audit** — saknad validering

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer

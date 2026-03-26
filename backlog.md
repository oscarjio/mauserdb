# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-26)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Nasta (session #341):
- [ ] **Granska gamification-UI** — badges, XP, niva, leaderboard, milestones korrekt?
- [ ] **Granska skiftoverlamning-UI** — formular, validering, historik, meddelandefunktion OK?
- [ ] **Granska alarm-historik** — larmdata, filtrering, tidsberakning, graf korrekt?
- [ ] **Granska produktionsmal-UI** — CRUD, berakningar, progress mot mal, grafer stammer?
- [ ] **Endpoint-stress: gamification+onboarding** — fortfarande >1.5s efter N+1 fix, undersok vidare

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer

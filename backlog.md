# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-26)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Nasta (session #346):
- [ ] **Optimera skiftrapport veckosammanstallning** — 6.6s svarstid (63 queries), behover batch-optimering
- [ ] **Granska gamification-systemet** — A: GamificationController+AchievementController SQL, B: gamification-UI+badges
- [ ] **Granska notifikationer** — A: NotifikationController SQL+endpoints, B: notifikation-UI+realtid
- [ ] **Granska maskin-admin** — A: MaskinController+MaskinRegisterController SQL, B: maskin-admin-UI
- [ ] **Granska energi-systemet djupare** — A: EnergiController berakningar, B: energi-grafer+trender

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer

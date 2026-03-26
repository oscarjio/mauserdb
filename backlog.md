# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-26)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Nasta (session #345):
- [ ] **Granska rapporter djupare** — A: veckorapport/morgonrapport/monthly-report SQL+endpoints, B: PDF-export UI+layout
- [ ] **Granska bonus-systemet** — A: BonusController+BonusAdminController SQL, B: bonus-dashboard+bonus-admin UI
- [ ] **Granska skiftrapporter** — A: SkiftrapportController+SkiftrapportExportController, B: alla skiftrapport-UI-sidor
- [ ] **Granska stopporsakssystemet** — A: StopporsakController+StopporsakTrendController SQL, B: stopporsak-UI+grafer
- [ ] **Ytterligare prestandaoptimering** — A: fler endpoints >500ms, B: lazy loading + bundle size

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer

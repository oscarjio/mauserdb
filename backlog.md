# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-27)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Nasta (session #362):
- [ ] **Backup-verifiering** — verifiera att DB-backups fungerar korrekt
- [ ] **API response-tid benchmark** — mät och dokumentera alla endpoints response-tider, identifiera >500ms
- [ ] **Unused code cleanup** — hitta och ta bort oanvänd PHP/Angular-kod (dead code)
- [ ] **Accessibility audit** — WCAG AA keyboard navigation + screen reader pa alla sidor
- [ ] **Error monitoring** — implementera custom error_log till skrivbar sökväg (alt till /var/log)

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer

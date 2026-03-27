# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-27)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #368):
- [x] Month-compare covering index + 30s cache — 600ms→100ms (cache HIT) (Worker A)
- [x] Write-through cache invalidering — 9 admin-CRUD-endpoints instrumenterade (Worker A)
- [x] Livestats buggfix — ibcToday COUNT→SUM(MAX) (KRITISK) (Worker A)
- [x] 145 endpoints 0x500 alla <2s (Worker A)
- [x] Heatmap UX — gradient, IBC-siffror, klickbar, dag/tim-summor (Worker B)
- [x] UX-granskning alla rebotling-sidor — dark theme OK (Worker B)
- [x] Data-verifiering mot prod DB — 0 diskrepanser (Worker B)
- [x] Uncommitted changes godkanda + committade (Worker B)

### Nasta (session #369):
- [ ] Error monitoring — centraliserad loggning
- [ ] E2E regressionstest — automatiserat testsvit
- [ ] PHP dead code audit — hitta oanvanda controllers/metoder
- [ ] Frontend accessibility audit — WCAG AA
- [ ] API response-format standardisering

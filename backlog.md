# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-27)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #370):
- [x] PHP dead code audit — 1 dead method borttagen (Worker A)
- [x] Error handling review — konsekvent format bekraftat (Worker A)
- [x] 115 endpoints stresstest — 0x500 alla <1s (Worker A)
- [x] SQL optimization review — covering indexes OK, 1 redundant index noterad (Worker A)
- [x] Driftstopp-timeline + effektivitet cap 150% (Worker B)
- [x] Icke-rebotling sidor UX-granskning — 6 sidor OK (Worker B)
- [x] Angular lifecycle audit — 0 memory leaks (Worker B)
- [x] WCAG AA granskning — heading/kontrast/aria OK (Worker B)
- [x] Deploy frontend + backend till dev OK (bada workers)

### Nasta (session #371):
- [ ] Ta bort redundant index idx_datum pa rebotling_ibc (Worker A noterade)
- [ ] E2E regressionstest — automatiserat testsvit
- [ ] API response-format standardisering
- [ ] Rebotling skiftrapport UX-forbattring
- [ ] Admin-sidor CRUD djuptest (skapa/redigera/ta bort)

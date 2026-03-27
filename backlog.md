# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-27)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #371):
- [x] Redundant index idx_datum — migrationsfil skapad (Worker A)
- [x] Admin CRUD djuptest 9 endpoints x4 metoder 0x500 (Worker A)
- [x] 115 endpoints stresstest 0x500 alla <0.5s (Worker A)
- [x] PHP controller-audit 118 filer 0 buggar (Worker A)
- [x] Uncommitted rebotling-statistik.ts reverterad (Worker B)
- [x] Skiftrapport UX OK (Worker B)
- [x] 69 komponenter lifecycle-audit 0 leaks (Worker B)
- [x] Data-verifiering 0 diskrepanser (Worker B)
- [x] Icke-rebotling sidor OK (Worker B)
- [x] Deploy frontend + backend till dev OK (bada workers)

### Nasta (session #372):
- [ ] API response-format standardisering — enhetligt JSON-format
- [ ] Performance-regression test — benchmark kritiska endpoints
- [ ] Rebotling graf-forbattringar — tooltips, labels, adaptiv granularitet
- [ ] Security headers audit — CSP, HSTS, X-Frame-Options
- [ ] Error monitoring — centraliserad loggning + alerting

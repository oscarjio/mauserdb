# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-27)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #369):
- [x] Livestats ibc_today fix: SUM(MAX)→MAX(ibc_count), ibc_hour COUNT(*) (Worker A)
- [x] SQL-audit 90+ tabeller mot prod_db_schema.sql — 0 mismatches (Worker A)
- [x] 171 endpoints stresstest — 0x500, alla <2s (Worker A)
- [x] API vs prod DB verifiering — 0 diskrepanser (Worker A)
- [x] summaryTotalIbc buggfix totalt→ibc_ok (Worker B)
- [x] produktion_procent bekraftad EJ kumulativ (Worker B)
- [x] UX djupgranskning alla rebotling-sidor OK (Worker B)
- [x] Data-verifiering frontend vs DB — 0 diskrepanser (Worker B)
- [x] Deploy frontend + backend till dev OK (bada workers)

### Nasta (session #370):
- [ ] Icke-rebotling sidor UX-granskning (tvattlinje, saglinje, klassificering)
- [ ] Error monitoring — centraliserad loggning
- [ ] E2E regressionstest — automatiserat testsvit
- [ ] PHP dead code audit — hitta oanvanda controllers/metoder
- [ ] Frontend accessibility audit — WCAG AA

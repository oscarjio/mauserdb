# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-29)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #395):
- [x] Slow endpoints: 5.4s→0.1s (operator-ranking), 1.7s→0.11s (morgonrapport), 1.6s→0.1s (statistikdashboard) — 6 nya index + 30s filcache
- [x] SQL-audit 11 controllers 0 mismatches (historik+kassation+stopporsak)
- [x] 120 endpoints testade 0x500, 0 endpoints >1s
- [x] 25 frontend-komp granskade 0 buggar: historik(6)+kvalitet(2)+stopporsak(4)+export(5)+ovriga(8)
- [x] ~43 charts destroy() OK, 5 exportfunktioner UTF-8 BOM+svenska OK

### Nasta (session #396):
- [ ] Lasttest: 100+ parallella requests mot optimerade endpoints — verifiera cache under last
- [ ] Rebotling-admin: djupgranskning backend CRUD + frontend (1504 rader, komplex)
- [ ] Operatorsportal: verifiera bonusberakningar end-to-end mot prod DB
- [ ] Executive/VD-dashboard: verifiera alla KPI-siffror mot prod DB
- [ ] Benchmarking/OEE: granska controllers + verifiera berakningar
- [ ] Mobilanpassning: testa alla sidor pa smal viewport (375px)

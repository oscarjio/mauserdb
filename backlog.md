# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-29)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #396):
- [x] Lasttest 100 parallella: 0x500, cache fungerar <150ms warm, rate limiter OK
- [x] Rebotling-admin: CRUD backend 0 buggar, frontend dark theme fix + mobilanpassning
- [x] Operatorsportal: 3 buggar fixade (trend faltnamn+snitt_bonus+days-param)
- [x] VD-dashboard: 2 KRITISKA buggar (COUNT→MAX topOperatorer+skiftstatus), KPI verifierad
- [x] OEE/Benchmarking: 7 controllers 0 SQL mismatches, berakningar korrekta
- [x] Mobilanpassning: 4 sidor fixade for 375px (executive+vd+rebotling-admin+operatorsbonus)

### Nasta (session #397):
- [ ] Skiftrapport: djupgranskning alla berakningar end-to-end mot prod DB
- [ ] Driftstopp-analys: verifiera alla controllers+frontend mot prod DB
- [ ] Gamification: granska badges/achievements berakningar
- [ ] Produktionsprognos: verifiera prognos-berakningar mot historisk data
- [ ] Alla controllers: systematisk COUNT(*) vs MAX(ibc_ok) audit (COUNT-buggen aterkommande)
- [ ] Responstest: alla sidor pa 375px, 768px, 1024px

# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-29)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #397):
- [x] COUNT(*) vs MAX(ibc_ok) audit: 26 queries fixade i 14 controllers (7.6% overcount i prod)
- [x] Skiftrapport+driftstopp: redan korrekt SQL, verifierat
- [x] Gamification: 3 buggar fixade (KRITISK kassationsrate 0%, min-profil, teamspelare)
- [x] Produktionsprognos: verifierad OK
- [x] Responstest: 3 sidor fixade (gamification+prognos+driftstopp) 375px
- [x] 99 endpoints 0x500, 0 >1s

### Nasta (session #398):
- [ ] Verifiera COUNT→MAX fixar mot prod DB (alla 14 controllers)
- [ ] Operatorsbonus end-to-end: ranking korrekt efter COUNT-fix?
- [ ] VD-dashboard: alla KPI-siffror korrekta efter COUNT-fix?
- [ ] Morgonrapport/daglig briefing: verifiera siffror efter fix
- [ ] Alla frontend-grafer: data korrekt efter backend COUNT-fix?
- [ ] Lasttest: 100 parallella efter alla fixar, 0x500?

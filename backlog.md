# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-29)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #393):
- [x] Operatorsbonus: verifierad efter GamificationController-fix — 793 IBC matchar prod DB
- [x] Skiftrapport: end-to-end test OK — korrekt MAX(ibc_ok) GROUP BY skiftraknare
- [x] Dashboard KPI: VD-vy + statistik + daglig sammanfattning — alla korrekta IBC-siffror
- [x] Lasttest: 80 parallella anrop (4 endpoints x 20), alla <250ms, 0x500
- [x] Rebotling cykeltider: AVG=169.6s, MIN=30s, MAX=1774s — matchar prod DB
- [x] 151 endpoints testade 0x500 + SQL-audit 7 controllers 0 mismatches
- [x] 30 frontend-komp granskade, 0 buggar, ~45 charts OK, lifecycle+dark theme+svenska OK

### Nasta (session #394):
- [ ] Alarm-historik: granska AlarmHistorikController + frontend mot prod DB
- [ ] Underhallsprognos: verifiera berakningar + UX-granskning
- [ ] Produktionskalender/prognos: granska endpoints + frontend
- [ ] Andon-board: verifiera realtidsdata + UX
- [ ] Shift-plan/tidrapport: end-to-end test

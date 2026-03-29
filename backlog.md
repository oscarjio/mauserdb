# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-29)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #398):
- [x] Verifiera COUNT→MAX fixar mot prod DB (7.7% overcount bekraftat, alla 14 controllers OK)
- [x] 7 nya COUNT(*)/SUM-buggar i 6 controllers fixade (Produktionspuls, Rebotling, Andon, RebotlingAdmin, DagligBriefing, BonusAdmin)
- [x] 115 endpoints 0x500, 0 >1s, lasttest 1000 parallella 0x500
- [x] VD-dashboard+operatorsbonus+morgonrapport KPI verifierade korrekt
- [x] 109 HTML-templates + 108 Chart.js grafer granskade OK
- [x] Mobilresponsivitet 5 sidor fixade (operator-ranking, morgonrapport, historisk-sammanfattning, statistik-overblick, veckorapport)

### Nasta (session #399):
- [ ] BonusAdminController.php rad 1821 — oanvand variabel '$_' (fixa lint-varning)
- [ ] End-to-end verifiering: surfa dev.mauserdb.com som VD — alla sidor laddar korrekt?
- [ ] Rebotling detaljvy — verifiera cykeldata, tider, operatorinfo mot prod DB
- [ ] Skiftrapport — verifiera berakningar end-to-end (skiftvis produktion, effektivitet, kvalitet)
- [ ] Driftstopp-analys — verifiera orsaker, tider, statistik mot prod DB
- [ ] Exportfunktioner — CSV/PDF korrekt data efter COUNT-fix?
- [ ] Sakerhetsgranskning — CSRF, rate limiting, auth pa alla endpoints

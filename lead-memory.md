# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-29 (session #393)*
*Fullstandig historik: lead-memory-archive.md*

---

## Projektoversikt

IBC-tvatteri (1000L plasttankar i metallbur). Systemet ger VD realtidsoerblick + rattvis operatorsbonus.

**Roller:** VD (KPI:er, 10-sek overblick), Operator (bonuslage live, motiverande), Admin (mal, regler, skift).
**Linjer:** Rebotling (AKTIV, bra data), Tvattlinje/Saglinje/Klassificeringslinje (EJ igang).
**Stack:** Angular 20+ -> PHP/PDO -> MySQL. PLC -> plcbackend (ror ALDRIG) -> DB.

## AGARENS DIREKTIV (2026-03-15)

- **FOKUS: BUGGJAKT** — koncentrera er pa att hitta och fixa buggar
- **Rebotling** — enda linjen med bra data
- **INGA NYA FEATURES** — prioritera kvalitet och stabilitet
- Granska controllers, services, templates systematiskt
- VD ska forsta laget pa 10 sekunder

## ABSOLUTA REGLER (bryt ALDRIG)

1. **Ror ALDRIG livesidorna**: `rebotling-live`, `tvattlinje-live`, `saglinje-live`, `klassificeringslinje-live`
2. **Ror ALDRIG plcbackend/** — PLC-datainsamling i produktion
3. **ALLTID bcrypt** — AuthHelper anvander password_hash/password_verify. Andra ALDRIG till sha1/md5
4. **ALDRIG rora dist/** — borttagen fran git, ska aldrig tillbaka
5. DB-andringar -> SQL-fil i `noreko-backend/migrations/YYYY-MM-DD_namn.sql` + `git add -f`
6. All UI-text pa **svenska**
7. Dark theme: `#1a202c` bg, `#2d3748` cards, `#e2e8f0` text, Bootstrap 5
8. Commit + push bara nar feature ar klar och bygger
9. Bygg: `cd noreko-frontend && npx ng build`

## AGARENS INSTRUKTIONER (dokumentera allt — agaren ska aldrig behova upprepa sig)

- Fokus rebotling. Ovriga linjer ej igang.
- Systemet ar for VD (overgripande koll) + rattvis individuell operatorsbonus.
- DB ligger INTE pa denna server — deployas manuellt. DB-andringar via SQL-migrering.
- Agenterna stannar aldrig — hall arbete igang.
- Ledaragenten driver projektet sjalvstandigt — sok internet, granska kod, uppfinn features.
- Lagg till nya funktioner i navigationsmenyn direkt.
- Kunden utvarderar efterat — jobba fritt och kreativt.
- Graferna behover detaljerade datapunkter, adaptiv granularitet.

## Tekniska monster

- **AuthService**: `loggedIn$` och `user$` BehaviorSubjects
- **Lifecycle**: `implements OnInit, OnDestroy` + `destroy$ = new Subject<void>()` + `takeUntil(this.destroy$)` + `clearInterval/clearTimeout` + `chart?.destroy()`
- **HTTP polling**: `setInterval` + `timeout(5000)` + `catchError` + `isFetching` guard
- **APP_INITIALIZER**: `firstValueFrom(auth.fetchStatus())`
- **Math i templates**: `Math = Math;` som class property
- **operators-tabell**: `id` (PK auto-increment) och `number` (synligt badgenummer). op1/op2/op3 i rebotling_ibc = operators.number. users.operator_id = operators.number.

## Bug Hunt Status

Bug Hunts #1-#50 genomforda. Kodbasen har genomgatt systematisk granskning.
Session #57-#104: Feature-utveckling. Se lead-memory-archive.md.
Session #105-#170: BUGGJAKT — ~2000+ buggar. Se lead-memory-archive.md.
Session #190-#244: BUGGJAKT — ~1100+ buggar. Se lead-memory-archive.md.
Session #245-#255: BUGGJAKT — 27 buggar. Kodbasen nara rent-status. Se lead-memory-archive.md.
Session #256-#354: BUGGJAKT — Se dev-log.md for detaljer.
Session #355-#366: Se lead-memory-archive.md.
Session #367: month-compare 1032ms→540ms + operatorsbonus RATTVIS + admin CRUD OK + caching 6 controllers OK + bundle 151 kB (redan optimerad) + 111 endpoints 0x500 + $cutoff dead code borttagen + deploy dev OK.
Session #368: KRITISK buggfix livestats COUNT→SUM(MAX) + month-compare 600ms→100ms (covering index+cache) + cache-invalidering 9 admin-endpoints + heatmap UX (gradient+klickbar+summor) + 145 endpoints 0x500 + 0 DB diskrepanser + deploy dev OK.
Session #369: Livestats ibc_today fix (overcounting) + summaryTotalIbc buggfix + SQL-audit 0 mismatches + 171 endpoints 0x500 + produktion_procent EJ kumulativ (bekraftad) + 0 DB diskrepanser + deploy dev OK.
Session #370: PHP dead code cleanup + error handling OK + 115 endpoints 0x500 alla <1s + driftstopp-timeline + effektivitet cap 150% + lifecycle audit 0 leaks + WCAG AA OK + deploy dev OK.
Session #371: idx_datum migrationsfil + admin CRUD 0x500 + 115 endpoints alla <0.5s + 118 controllers 0 buggar + skiftrapport OK + 69 komponenter 0 leaks + data 0 diskrepanser + deploy dev OK.
Session #372: API format audit 115/116 + security headers 9/9 OK + performance 0x500 <1s + rebotling grafer 7 charts + error monitoring + data 394 cykler 0 diskrepanser + deploy dev OK.
Session #373: Input-validering 44 OK + cache 10 OK + 114 endpoints 0x500 + maintenance_log migration + operatorsbonus UX + admin pagination + lifecycle 0 leaks + 5030 cykler 0 diskrepanser + deploy dev OK.
Session #374: PHP 8.2 kompatibel 0 issues + rate limiting implementerad 120 req/min + error recovery 1126 catch OK + accessibility 13 fixar + 114 endpoints 0x500 <1s + 0 diskrepanser + deploy dev OK.
Session #375: Skiftrapport KPI backend (operator-kpi-jamforelse endpoint) + trendgrafer frontend (dolda canvas fixade) + driftstopp orsaksfordelning+veckotrend endpoints + prestandafix ensureTables 2.4s->0.13s + 115 endpoints 0x500 <1s + 0 SQL mismatches + 5030 cykler 0 diskrepanser + deploy dev OK.
Session #376: Operator-KPI-jamforelse stapeldiagram i skiftrapport + driftstopp orsaksfordelning doughnut+veckotrend linje + operatorsbonus berakningar OK + admin CRUD-audit OK + 113 endpoints 0x500 <1s + 0 SQL mismatches + 5030 cykler 0 diskrepanser + deploy dev OK.
Session #377: Operatorsbonus KPI-detaljer backend+frontend. Historik daglig-endpoint filter/sortering/pagination. Statistik manads+kvartalsjamforelser. Navigationsfix. Prestandafix driftstopp 1.6s->0.23s. 169 endpoints 0x500. 0 SQL mismatches. 5030 cykler 0 diskrepanser. Deploy dev OK.
Session #378: Rebotling daglig historik frontend. Statistik manads/kvartalsjamforelser frontend. Operatorsbonus drilldown. Driftstopp filter. 115 endpoints 0x500. 0 SQL mismatches. 5030 cykler 0 diskrepanser. Deploy dev OK.
Session #379: Operatorsbonus trendgraf backend+frontend. Driftstopp vecko/manadsaggregat+datumnavigering. Statistik grafinteraktivitet. Admin granskning OK. 115 endpoints 0x500. 0 SQL mismatches. 41 komp 0 leaks. Deploy dev OK.
Session #380: Statistik export CSV/PDF + endpoint-test 115 0x500 + SQL-audit 0 mismatches + 5030 cykler 0 diskrepanser + skiftrapport KPI verifierad + 163 komp 0 lackor + bootstrap modal-fix + deploy dev OK.
Session #381: day-raw-data endpoint granskad + endpoint-test 123 0x500 <1.5s + SQL-audit 0 mismatches + skiftrapport KPI verifierad + admin CRUD OK + mobilanpassning skiftrapport + 179 komp 0 lackor + deploy dev OK.
Session #382: Rebotling live-data verifierad mot prod DB (exakt matchning) + operatorsbonus berakningar verifierade + 115 endpoints 0x500 <364ms + SQL-audit 0 mismatches + driftstopp UX-fix (svenska tecken) + historisk produktion UX-fix + 42 komp 0 lackor + deploy dev OK.
Session #383: Skiftrapport berakningar OK + statistik backend OK + admin CRUD OK + 115 endpoints 0x500 + SQL-audit 0 mismatches + SkiftrapportController CREATE TABLE buggfix + gamification UX OK + operatorsbonus 4 grafer OK + 7 svenska textfixar (Mal->Mål) + 170 komp 0 lackor + build+deploy dev OK.
Session #384: Dashboard UX OK + driftstopp UX OK + historisk produktion OK + navigering 80+ routes OK + error handling 606 catchError OK + 115 endpoints 0x500 + SQL-audit 0 mismatches + admin CRUD auth OK + 180 komp 0 lackor + build+deploy dev OK.
Session #385: Operatorsbonus end-to-end verifierad mot prod DB + skiftrapport OK + gamification OK + 92 endpoints 0x500 + SQL-audit 0 mismatches + mobilanpassning 11 sidor fixade + statistik grafer OK + 180+ komp 0 lackor + PLC-diagnostik endpoint + build+deploy dev OK.
Session #386: PLC-diagnostik verifierad mot prod DB + driftstopp 6 endpoints OK + admin CRUD auth+CSRF OK + 114 endpoints 0x500 + SQL-audit 570+ FROM 0 mismatches + sakerhet OK (CSRF+rate limiting+prepared statements) + rebotling-admin mobilfix + 180 komp 0 lackor + build+deploy dev OK.
Session #387: 114 endpoints 0x500 + edge cases 9 scenarier OK + lasttest 30 parallella 0x500 + rebotling datakvalitet verifierad + 21 HTML mobilfixar + 170 komp 0 lackor + 112 charts OK + PLC-diagnostik fix + build+deploy dev OK.
Session #388: Skiftrapport verifierad mot prod DB 0 diskrepanser + operatorsbonus 13 op stresstestade OK + admin CRUD 12 edge cases OK + daglig-sammanstallning 3x snabbare + 88 endpoints 0x500 + 162 routes OK + dark theme 0 avvikelser + 161 komp 0 lackor + 195 charts OK + svenska textfix + build+deploy dev OK.
Session #389: 404-endpoints fixade (3 controllers default GET) + unused code borttaget (93 rader PHP + 2 TS vars) + produktion_procent EJ kumulativ bekraftad + CSV-export 3 sidor forbattrad + driftstopp 90d historik + 115 endpoints 0x500 + 42 komp 0 lackor + build+deploy dev OK.
Session #390: 115 endpoints 0x500 <600ms + rebotling 0 diskrepanser + operatorsbonus 3 op OK + SQL-audit 10 controllers 0 mismatches + 187 frontend-filer granskade + dark theme fix + build+deploy dev OK.
Session #391: VeckorapportController SQL-fix (COUNT→MAX/GROUP BY) + 96 endpoints 0x500 + driftstopp/skiftrapport/dashboard/rapport verifierade mot prod DB + 0 frontend-buggar + build+deploy dev OK.
Session #392: KRITISK prestandafix manads-aggregat 12.8s→0.34s + HistorikController SQL-fix (GROUP BY skiftraknare, 650→793 IBC) + GamificationController SQL-fix (COUNT→MAX) + 107 endpoints 0x500 + 18 frontend-komp granskade 0 buggar + build+deploy dev OK.
Session #393: Verifiering av #392-fixar — operatorsbonus+skiftrapport+dashboard KPI alla korrekta IBC-siffror (793 matchar prod DB). Lasttest 80 parallella <250ms. 151 endpoints 0x500. SQL-audit 7 controllers 0 mismatches. 30 frontend-komp 0 buggar ~45 charts OK. Build+deploy dev OK.

## OPPEN BACKLOG (prioritetsordning)

GRUNDLIG GENOMGANG + FORBATTRING — vi har nu prod_db_schema.sql och deploy-pipeline.

### Verktyg:
- prod_db_schema.sql i projektroten = facit for SQL
- Deploy: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- Prod DB: ssh -p 32546 user@mauserdb.com + mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb
- mb_string polyfill i api.php (servern saknar php-mbstring)
- VIKTIGT: rsync --exclude='db_config.php' for backend deploy (fixat session #329)

### Nasta (session #394):
- Alarm-historik: granska AlarmHistorikController + frontend mot prod DB
- Underhallsprognos: verifiera berakningar + UX-granskning
- Produktionskalender/prognos: granska endpoints + frontend
- Andon-board: verifiera realtidsdata + UX
- Shift-plan/tidrapport: end-to-end test

## BESLUTSDAGBOK (senaste 3)

### 2026-03-28 — Session #391 (klar)
Worker A: VeckorapportController SQL-fix (COUNT→MAX/GROUP BY, 210 IBC matchar prod DB). 96 endpoints 0x500. SQL-audit 8 controllers 0 mismatches. Deploy dev OK.
Worker B: 0 buggar. Driftstopp+skiftrapport+dashboard+rapport+operatorsportal OK. 120+ routes OK. Build+deploy dev OK.

### 2026-03-28 — Session #392 (klar)
Worker A: KRITISK prestandafix manads-aggregat 12.8s→0.34s (33x, batch-query). HistorikController SQL-fix GROUP BY skiftraknare (650→793 IBC). GamificationController SQL-fix COUNT→MAX(ibc_ok). 107 endpoints 0x500. Deploy dev OK.
Worker B: 18 komp granskade 0 buggar. 10 admin-sidor. 3 rebotling-historik. 4 statistik. 1 gamification. Build+deploy dev OK.

### 2026-03-29 — Session #393 (klar)
Worker A: Verifiering av #392-fixar. Operatorsbonus+skiftrapport+dashboard KPI alla korrekta (793 IBC matchar prod DB). Cykeltider AVG=169.6s OK. Lasttest 80 parallella <250ms 0x500. 151 endpoints 0x500. SQL-audit 7 controllers 0 mismatches. Deploy dev OK.
Worker B: 30 komp granskade 0 buggar. Bonus(3)+skiftrapport(6)+dashboard(5)+drifttid(6)+stopporsak(9)+ovrigt(1). ~45 charts alla destroy() OK. Lifecycle+dark theme+svenska+mobil OK. Build+deploy dev OK.

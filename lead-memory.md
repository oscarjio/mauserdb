# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-28 (session #372)*
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

## OPPEN BACKLOG (prioritetsordning)

GRUNDLIG GENOMGANG + FORBATTRING — vi har nu prod_db_schema.sql och deploy-pipeline.

### Verktyg:
- prod_db_schema.sql i projektroten = facit for SQL
- Deploy: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- Prod DB: ssh -p 32546 user@mauserdb.com + mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb
- mb_string polyfill i api.php (servern saknar php-mbstring)
- VIKTIGT: rsync --exclude='db_config.php' for backend deploy (fixat session #329)

### Nasta (session #377):
- Granska operatorsbonus-sidan UX — visa KPI-detaljer per operator tydligare
- Rebotling historik — forbattra filterfunktioner och sortering
- Statistik dashboard — forbattra periodval och jamnforelser
- Granska navigationsmenyn — alla sidor narbara, korrekt ordning
- Rebotling live-dashboard — verifiera data uppdateras korrekt (TITTA BARA)

## BESLUTSDAGBOK (senaste 3)

### 2026-03-28 — Session #375 (klar)
Worker A: Skiftrapport operator-kpi-jamforelse endpoint. Driftstopp orsaksfordelning+veckotrend endpoints. Prestandafix ensureTables 2.4s->0.13s. 115 endpoints 0x500 <1s. 0 SQL mismatches. Deploy dev OK.
Worker B: Skiftrapport trendgrafer synliga (dolda canvas fixade, KPI-kort utokade 4->6). Audit-logg+notifikationer redan implementerade. Bundle redan optimerad (69KB). 5030 cykler 0 diskrepanser. Build+deploy dev OK.

### 2026-03-28 — Session #376 (klar)
Worker A: Operatorsbonus berakningar verifierade mot prod (13 operatorer, bonus_konfiguration 40/30/20/10 viktning OK). Admin CRUD-audit alla controllers OK. 113 endpoints 0x500 <1s. 0 SQL mismatches. Deploy dev OK.
Worker B: Operator-KPI-jamforelse stapeldiagram i skiftrapport (snitt IBC/h, OEE%, kassation%). Driftstopp orsaksfordelning doughnut + veckotrend linjediagram (7/14/30 dagar). Lifecycle 0 leaks. 5030 cykler 0 diskrepanser. Build+deploy dev OK.

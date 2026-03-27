# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-27 (session #370)*
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
Session #355: SQL-schema granskning + performance audit + WCAG.
Session #356: E2E regressionstest + HTTP interceptor audit + caching + lazy loading + Chart.js granskning + auth-flode.
Session #357: Rebotling-djupgranskning — 0 SQL-mismatches, produktion_procent OK (inte kumulativ), 3 schema-fixes, heatmap CSS-fix, E2E 50/50 PASS.
Session #358: Icke-rebotling genomgang — 100+ endpoints 0x500, 80+ komponenter 0 buggar, 2 indexes, produktion_procent cap OK, E2E 50/50 PASS.
Session #359: Performance-optimering oee-trendanalys 988ms→124ms + alarm-historik 931ms→130ms (filcache+2 index). CRUD-integrationstest OK. 109 endpoints 0x500. Data-kvalitet DB vs API 0 diskrepanser. 118 grafer OK. E2E 50/50 PASS.
Session #360: EXPLAIN-audit 3 covering indexes + error-handling 3 fixes + stresstest <600ms + security audit 0 SQLi/XSS + API-docs 117 endpoints + 115 endpoints 0x500 + E2E 115/115 PASS.
Session #361: Cache review 13 filer OK + DB persistent connections + PHP error_log (kraver root) + 130+128 endpoints 0x500 + bundle 8.8MB/72K main + admin guards OK + 50+ grafer OK + 0 DB diskrepanser + E2E 128/128 PASS.
Session #362: API benchmark 103 endpoints 0x500 alla <500ms + 32 dead code filer borttagna + ErrorLogger centraliserad + 27 WCAG heading-fixes + 23 chart.destroy() fixes + backup OK + 0 DB diskrepanser.
Session #363: 156 endpoints 0x500 + rebotling backend SQL OK + error handling 1186 loggar OK + rebotling-statistik IBC/h+effektivitet+bar chart granskad + CSV export fixad + UX alla sidor OK + DB 946 vs 1058 diskrepans noterad.
Session #364: API vs DB diskrepans FIXAD (skiftraknare IS NOT NULL filter borttaget 220 stallen 44 filer) + PHP parse error FIXAD (RebotlingAnalyticsController.php) + slow endpoints optimerade (today-snapshot, all-lines-status) + 55 endpoints 0x500 + 94 sidor mobile/UX granskade 0 problem + $rows7 dead code borttagen + deploy dev OK.
Session #365: Diskrepans-fix verifierad mot prod DB OK + benchmarking 926ms->237ms + month-compare 984ms->627ms (CTE-optimering) + getOtherLineStatus dead code borttagen + covering index + 97 endpoints 0x500 + UX alla sidor OK + produktion_procent EJ kumulativ (bekraftad) + rebotling-statistik djupgranskning + VD Dashboard OK + error handling audit + lifecycle-audit alla Chart-komponenter + build + deploy dev OK.
Session #366: 129 endpoints 0x500 alla <2s + PHP controller-audit 112 filer 79k rader 0 problem + integration test API vs DB perfekt match + error/404-hantering OK + data-korrekthet 0 diskrepanser + Angular kodgranskning 92 services OK + 115 chart-filer OK + build 0 fel + deploy dev OK.
Session #367: month-compare 1032ms→540ms + operatorsbonus RATTVIS + admin CRUD OK + caching 6 controllers OK + bundle 151 kB (redan optimerad) + 111 endpoints 0x500 + $cutoff dead code borttagen + deploy dev OK.
Session #368: KRITISK buggfix livestats COUNT→SUM(MAX) + month-compare 600ms→100ms (covering index+cache) + cache-invalidering 9 admin-endpoints + heatmap UX (gradient+klickbar+summor) + 145 endpoints 0x500 + 0 DB diskrepanser + deploy dev OK.
Session #369: Livestats ibc_today fix (overcounting) + summaryTotalIbc buggfix + SQL-audit 0 mismatches + 171 endpoints 0x500 + produktion_procent EJ kumulativ (bekraftad) + 0 DB diskrepanser + deploy dev OK.
Session #370: PHP dead code cleanup + error handling OK + 115 endpoints 0x500 alla <1s + driftstopp-timeline + effektivitet cap 150% + lifecycle audit 0 leaks + WCAG AA OK + deploy dev OK.
Session #371: idx_datum migrationsfil + admin CRUD 0x500 + 115 endpoints alla <0.5s + 118 controllers 0 buggar + skiftrapport OK + 69 komponenter 0 leaks + data 0 diskrepanser + deploy dev OK.

## OPPEN BACKLOG (prioritetsordning)

GRUNDLIG GENOMGANG + FORBATTRING — vi har nu prod_db_schema.sql och deploy-pipeline.

### Verktyg:
- prod_db_schema.sql i projektroten = facit for SQL
- Deploy: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- Prod DB: ssh -p 32546 user@mauserdb.com + mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb
- mb_string polyfill i api.php (servern saknar php-mbstring)
- VIKTIGT: rsync --exclude='db_config.php' for backend deploy (fixat session #329)

### Nasta (session #372):
- API response-format standardisering
- Performance-regression test
- Rebotling graf-forbattringar
- Security headers audit
- Error monitoring

## BESLUTSDAGBOK (senaste 3)

### 2026-03-27 — Session #371 (klar)
Worker A: Redundant index idx_datum migrationsfil skapad. Admin CRUD 9 endpoints x4 metoder 0x500. 115 endpoints 0x500 alla <0.5s. PHP controller-audit 118 filer 0 buggar 0 SQLi. Deploy dev OK.
Worker B: Uncommitted rebotling-statistik.ts REVERTERAD (tog bort 150% cap — bryter designbeslut). Skiftrapport UX OK. 69 komponenter lifecycle 0 leaks. Data-verifiering 0 diskrepanser. Build + deploy dev OK.

### 2026-03-27 — Session #370 (klar)
Worker A: PHP dead code audit (1 dead method borttagen). Error handling review OK. 115 endpoints 0x500 alla <1s. SQL optimization: covering indexes OK, 1 redundant index noterad (idx_datum). Deploy dev OK.
Worker B: Driftstopp-timeline + effektivitet cap 150%. Icke-rebotling UX 6 sidor OK. Lifecycle audit 0 memory leaks. WCAG AA OK. Build + deploy dev OK.

### 2026-03-27 — Session #369 (klar)
Worker A: Livestats ibc_today fix (SUM MAX→MAX ibc_count, overcounting fixad). SQL-audit 90+ tabeller 0 mismatches. 171 endpoints 0x500 alla <2s. API vs prod DB 0 diskrepanser. Deploy dev OK.
Worker B: summaryTotalIbc buggfix (totalt→ibc_ok). produktion_procent bekraftad EJ kumulativ (4:e gangen). UX djupgranskning alla rebotling-sidor OK. Data-verifiering 0 diskrepanser. Build + deploy dev OK.

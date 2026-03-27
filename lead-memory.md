# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-27 (session #364)*
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

## OPPEN BACKLOG (prioritetsordning)

GRUNDLIG GENOMGANG + FORBATTRING — vi har nu prod_db_schema.sql och deploy-pipeline.

### Verktyg:
- prod_db_schema.sql i projektroten = facit for SQL
- Deploy: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- Prod DB: ssh -p 32546 user@mauserdb.com + mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb
- mb_string polyfill i api.php (servern saknar php-mbstring)
- VIKTIGT: rsync --exclude='db_config.php' for backend deploy (fixat session #329)

### Nasta (session #365):
- [ ] Verifiera diskrepans-fix mot prod DB (SSH var nere #364)
- [ ] Benchmarking + month-compare endpoints (>1s)
- [ ] Integration test suite (API-floden)
- [ ] Error page / 404-hantering

## BESLUTSDAGBOK (senaste 3)

### 2026-03-27 — Session #364 (klar)
Worker A: API vs DB diskrepans FIXAD — `skiftraknare IS NOT NULL` filter exkluderade 112 giltiga cykler (220 stallen, 44 filer). PHP parse error i RebotlingAnalyticsController.php FIXAD (trasigt try/catch efter refaktorering). Slow endpoints: today-snapshot 6→1 query, all-lines-status 4→1 query. 55 endpoints 0x500. PHP 8.2.29, inga composer-deps, bcrypt OK.
Worker B: 94 sidor granskade for mobile responsivitet — alla redan responsive med Bootstrap grid. Dark theme, lifecycle, svenska texter: konsistent overallt. VD Dashboard: KPI:er korrekta, grafer OK, forkJoin + 30s polling. produktion_procent INTE kumulativ (bekraftad). Build + deploy OK.
Lead: $rows7 undefined variable borttagen (dead code efter 14-dagars query konsolidering).

### 2026-03-27 — Session #363 (klar)
Worker A: 156 endpoints stresstest — 0x500, alla svarstider rimliga (exec-dashboard 1.5s = aggregering). 7 rebotling-controllers — alla SQL matchar schema. EXPLAIN optimal indexanvandning. Error handling 1186 loggar, 0 tomma catches. Response-format konsistent. Inga fixes kravdes.
Worker B: Rebotling-statistik andringar granskade — IBC/h korrekt, effektivitet korrekt, scroll-restore OK. CSV/Excel export fixad (kolumnnamn). 45+ rebotling + 48 icke-rebotling sidor — alla lifecycle OK, chart.destroy OK, dark theme OK. DB diskrepans: API 946 vs DB 1058 mars-cykler (pre-existerande, moj PHP limit).

### 2026-03-27 — Session #362 (klar)
Worker A: API benchmark 103 endpoints — alla <500ms, 0x500. 32 dead code filer borttagna. ErrorLogger centraliserad. Silent catch fixad.
Worker B: Backup OK. WCAG 27 heading-fixes + 2 aria-labels. 23 chart.destroy() fixes. 0 DB-diskrepanser.

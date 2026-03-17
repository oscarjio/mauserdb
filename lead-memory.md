# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-17 (session #136)*
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
Session #105-#106: BUGGJAKT — 12 backend + 4 frontend + 12 endpoints testade.
Session #107: BUGGJAKT — 122 fixar + 270 trackBy.
Session #108: BUGGJAKT — 13 buggar (10 backend + 3 frontend) + 7 endpoints OK.
Session #109: BUGGJAKT — 33 buggar (11 backend + 22 UTC-datum).
Session #110: BUGGJAKT — 15 buggar (13 backend + 2 imports). Alla controllers/ granskade.
Session #111: BUGGJAKT — 30 buggar (21 workers + 9 lead).
Session #112: BUGGJAKT — 22 buggar + 10 unused vars.
Session #113: BUGGJAKT — 11 buggar (3 aggregering + 8 null-safety/setTimeout/unicode).
Session #114: BUGGJAKT — 26 fixar. Worker A: 1 SQL-injection + 83 JSON_UNESCAPED_UNICODE. Worker B: 25 (5 catch-block + 18 setTimeout + 2 maskin).
Session #115: BUGGJAKT — 40 buggar (20 Worker A + 20 Worker B). 17 controllers granskade.
Session #116: BUGGJAKT — 58 buggar (34 Worker A + 24 Worker B). 20 controllers granskade. 12 kritiska operators.id/number-fixar.
Session #117: BUGGJAKT — 51 buggar (25 Worker A + 26 Worker B). 11 PHP-controllers + 15 TS-services granskade.
Session #118: BUGGJAKT — 23 buggar (5 Worker A + 18 Worker B). 10 PHP-controllers + 15 TS-services granskade.
Session #119: BUGGJAKT — 46 buggar (33 Worker A + 13 Worker B). 5 rebotling-controllers + 11 TS-services granskade.
Session #120: BUGGJAKT — 41 buggar (4 Worker A + 37 Worker B). 16 PHP-controllers + 21 TS-services granskade.
Session #121: BUGGJAKT — 41 buggar (12 Worker A + 29 Worker B). 13 PHP-controllers + 15 TS-services + 14 komponenter granskade.
Session #122: BUGGJAKT — 28 buggar (13 Worker A + 15 Worker B). 13 PHP-controllers + api.php + PHP helpers + endpoint-testning. Kritisk 500-fix i RebotlingTrendanalys.
Session #123: BUGGJAKT — 27 buggar (20 Worker A + 7 Worker B). 36 PHP-controllers + 4 Angular utils/guards/interceptors granskade. Inga pipes i projektet.
Session #124: BUGGJAKT — 52 buggar (34 Worker A + 18 Worker B). 17 PHP-controllers granskade (batch 5 klar) + 9 Angular services re-audit.
Session #125: BUGGJAKT — 16 buggar (10 Worker A + 6 Worker B). SQL-parametervalidering OK + error-logging + TS-logik + dead code.
Session #126: BUGGJAKT — 9 buggar (2 Worker A 500-fixar + 7 Worker B polling/guards). Kritiska 500-fel fixade (shift filter, time_of_day, saknade tabeller).
Session #127: BUGGJAKT — 16 buggar (8 Worker A + 8 Worker B). intval()-bugg (kritisk), XSS-risk, DB-lakcor + setTimeout-leaks, timezone-parsing.
Session #128: BUGGJAKT — 27 buggar (12 Worker A + 15 Worker B). Type coercion, saknad auth, input validation + Safari datetime-parsing, timezone-fix.
Session #129: BUGGJAKT — 23 buggar (20 Worker A + 3 Worker B). PDOException-lackage, 18x loose comparisons, 3x division-by-zero. Frontend audit: 109 charts OK, alla subscriptions OK.
Session #130: BUGGJAKT — 48 buggar (27 Worker A + 21 Worker B). SQL edge cases, JSON return consistency, error_log audit + template null-safety (.toFixed). Lazy loading OK, service URLs OK.
Session #131: BUGGJAKT — 52 buggar (22 Worker A + 30 Worker B). Boundary validation, date range swap, SQL param whitelists + form validation, error state UI i 6 komponenter.
Session #132: BUGGJAKT — 33 buggar (11 Worker A + 22 Worker B). HTTP method enforcement, unused vars, CORS/headers + accessibility, template null-safety. Memory profiling: inga lakor.
Session #133: BUGGJAKT — 29 buggar (22 Worker A + 7 Worker B). Error response consistency (19 controllers), HTTP 405 + route guards, error interceptor, dark theme.
Session #134: BUGGJAKT — 19 buggar (5 Worker A + 14 Worker B). SQL prepared statements OK, 1 XSS-fix, unused vars + form validation, unused declarations, subscription fixes.
Session #135: BUGGJAKT — 15 buggar (9 Worker A + 6 Worker B). Date/time ISO-fix, unused vars, null/edge cases + error state UI, auth guard. HTTP services alla konsekvent.
Session #136: BUGGJAKT — 6 buggar (3 Worker A + 3 Worker B). JSON_UNESCAPED_UNICODE (263x), error_log format (444x), PreloadAllModules, setTimeout-lacka. 109 charts + 138 routes OK.

## OPPEN BACKLOG (prioritetsordning)

BUGGJAKT-FOKUS — inga nya features tills vidare.

### Kvarstaende buggjakt-items:
- [x] PHP response format consistency audit [Worker A #136]
- [x] Angular chart destroy audit [Worker B #136]
- [x] PHP file upload validation audit [Worker A #136] — inga filuppladdningar i projektet
- [x] PHP error_log format consistency [Worker A #136]
- [x] Angular lazy loading route audit [Worker B #136]
- [ ] PHP session/cookie security audit
- [ ] Angular template strict null-check audit
- [ ] PHP SQL column name verification

## BESLUTSDAGBOK (senaste 3)

### 2026-03-17 — Session #135 (klar)
Worker A: 9 buggar — 1x date('o') ISO-vecka, 1x unused $shift, 7x null/edge cases.
Worker B: 6 buggar — 4x error state UI, 2x auth.guard unused params. Totalt: 15 buggar.

### 2026-03-17 — Session #136 (klar)
Worker A: 3 buggar — 263x json_encode saknade JSON_UNESCAPED_UNICODE (42 filer), 444x error_log standardiserade. Ingen filuppladdning i projektet.
Worker B: 3 buggar — PreloadAllModules saknades, 1x setTimeout-lacka i maskinunderhall. 109 chart-komponenter + 138 routes alla OK.
Totalt: 6 buggar fixade (748+ rader andrade).

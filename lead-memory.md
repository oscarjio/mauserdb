# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-18 (session #156)*
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
Session #105-#131: BUGGJAKT — ~700 buggar totalt. SQL injection, UTC-datum, trackBy, operators.id/number, 500-fel, type coercion, boundary validation, form validation. Se lead-memory-archive.md.
Session #132: BUGGJAKT — 33 buggar (11 Worker A + 22 Worker B). HTTP method enforcement, unused vars, CORS/headers + accessibility, template null-safety. Memory profiling: inga lakor.
Session #133: BUGGJAKT — 29 buggar (22 Worker A + 7 Worker B). Error response consistency (19 controllers), HTTP 405 + route guards, error interceptor, dark theme.
Session #134: BUGGJAKT — 19 buggar (5 Worker A + 14 Worker B). SQL prepared statements OK, 1 XSS-fix, unused vars + form validation, unused declarations, subscription fixes.
Session #135: BUGGJAKT — 15 buggar (9 Worker A + 6 Worker B). Date/time ISO-fix, unused vars, null/edge cases + error state UI, auth guard. HTTP services alla konsekvent.
Session #136: BUGGJAKT — 6 buggar (3 Worker A + 3 Worker B). JSON_UNESCAPED_UNICODE (263x), error_log format (444x), PreloadAllModules, setTimeout-lacka. 109 charts + 138 routes OK.
Session #137: BUGGJAKT — 23 buggar (9 Worker A + 14 Worker B). Session fixation, cookie cleanup, 7x date range validation + 5 null-check, 5 input sanitization, 90 HTTP timeout/catchError.
Session #138: BUGGJAKT — 18 buggar (10 Worker A + 8 Worker B). Boundary/pagination, error boundary, 8x race condition (transaktioner) + open redirect-fix, router param whitelist, 5x unused imports, change detection audit.
Session #139: BUGGJAKT — 29 buggar (13 Worker A + 16 Worker B). SQL-kolumner, timestamp, GROUP BY, json_decode null-safety, dead code + interceptor retry, 10x change detection cache, deprecated API migration.
Session #140: BUGGJAKT — 39 buggar (7 Worker A + 32 Worker B). SQL mixed params, PII i loggar, hardkodade credentials, security headers + 32x setTimeout destroy$-guards i 19 chart-komponenter.
Session #141: BUGGJAKT — 55 buggar (15 Worker A + 40 Worker B). Response format, transactions, in_array strict, XSS + error state UI (7 komponenter), 33x setTimeout guards, route guards OK.
Session #142: BUGGJAKT — 43 buggar (21 Worker A + 22 Worker B). DateTime explicit timezone (11 controllers), strtotime false-check (5 controllers), session timeout (8h), isFetching polling guards (21 komponenter).
Session #143: BUGGJAKT — 24 buggar (15 Worker A + 9 Worker B). N+1 query-optimering (365->1 i calcDailyStreak), CORS/headers, error_log + form validation, routing pathMatch, template null-safety.
Session #144: BUGGJAKT — 33 buggar (19 Worker A + 14 Worker B). Race conditions (6 SELECT-then-UPDATE), input boundary (11 max-langd/limits), template null-safety (12 !. -> ?.), router guard (andon authGuard).
Session #145: BUGGJAKT — 70 buggar (18 Worker A + 52 Worker B). Error handling consistency (14 controllers), session security (4 fixes), HTTP error display (52 subscribe-anrop i 10 komponenter). Memory profiling OK.
Session #146: BUGGJAKT — 19 buggar (5 Worker A + 14 Worker B). SQL injection fixes (BonusController, RebotlingAnalyticsController), dead code, LIMIT cast + 14 getter->cached property (change detection) i 9 komponenter.
Session #147: BUGGJAKT — 10 buggar (Worker A). Rate limiting (register, password change), security headers (Cache-Control, HSTS, X-Powered-By), session lifetime sync, transaction + error fixes.
Session #148: BUGGJAKT — 21 buggar (14 Worker A + 7 Worker B). Transaction consistency (4 controllers), json_decode null-safety (10 fix) + unused FormsModule imports (5), form validation (2).
Session #149: BUGGJAKT — 161 buggar (16 Worker A + 145 Worker B). File I/O guards (2), DateTime try/catch (12), json_decode null-safety (4) + HTTP timeout/catchError (145 i 31 komponenter). Memory leak audit OK.
Session #150: BUGGJAKT — 77 buggar (28 Worker A + 49 Worker B). Error logging consistency (15), unused $e (6), input validation trim (7) + template accessibility (49 aria-labels/roles i 12 filer). Lazy loading OK, unused imports OK.
Session #151: BUGGJAKT — 16 buggar (6 Worker A + 10 Worker B). Unused vars (3), kritisk JOIN-bugg operators.id->number (1), SQL kolumn-fix (2) + error state UI (7 komponenter), catchError guards (6 subscribe). Response format OK, form validation OK.
Session #152: BUGGJAKT — 59 buggar (22 Worker A + 37 Worker B). Transaction wrapping (19 i 9 controllers), edge case null-guards (3) + catchError audit (37 i 8 komponenter). Memory leak OK, template type safety OK.
Session #153: BUGGJAKT — 119 buggar (62 Worker A + 57 Worker B). DateTime timezone (26 i 5 controllers), in_array strict (32 i 22 controllers), json_decode null-safety (4) + duplicate imports (57 i 57 filer). File upload OK (inga uploads), retry OK, route guards OK.
Session #154: BUGGJAKT — 61 buggar (8 Worker A + 53 Worker B). Response headers OK (globalt i api.php), SQL column fixes (rebotling_log->stoppage_log/rebotling_ibc), unused vars (4) + form validation (20 i 10 komponenter), template !.->?. (33 i 3 komponenter).
Session #155: BUGGJAKT — 55 buggar (8 Worker A + 47 Worker B). error_log OK, integer casting OK, 8 json_decode null-safety + HTTP errors redan svenska, 47 trackByIndex->trackById (32 komponenter).
Session #156: BUGGJAKT — 25 buggar (10 Worker A + 15 Worker B). strtotime false-check (7 controllers), DateTime try/catch (2), transaction wrapping (3), file path traversal OK + 15 setTimeout destroy$-guards (12 komponenter), form reset OK.

## OPPEN BACKLOG (prioritetsordning)

BUGGJAKT-FOKUS — inga nya features tills vidare.

### Kvarstaende buggjakt-items:
- [x] PHP date/time edge case audit — 7 strtotime + 2 DateTime + 1 preg_match (session #156)
- [x] Angular memory leak audit — 15 setTimeout guards (session #156)
- [x] PHP file path traversal audit — alla sokvagar sakra (session #156)
- [x] PHP transaction consistency audit — 3 transaction wraps (session #156)
- [x] Angular form reset audit — alla formuler redan korrekta (session #156)
- [ ] PHP SQL ORDER BY injection audit — verifiera kolumn-whitelist
- [ ] Angular route param validation audit — validera route params

## BESLUTSDAGBOK (senaste 3)

### 2026-03-18 — Session #154 (klar)
Worker A: 8 buggar — Response headers OK (globalt), 4 SQL column fixes, 4 unused vars.
Worker B: 53 buggar — 20 form validation, 33 template !.->?..

### 2026-03-18 — Session #155 (klar)
Worker A: 8 buggar — error_log OK, integer casting OK, 8 json_decode null-safety.
Worker B: 47 buggar — HTTP errors redan svenska, 47 trackByIndex->trackById (32 komponenter).

### 2026-03-18 — Session #156 (klar)
Worker A: 10 buggar — 7 strtotime false-check (7 controllers), 2 DateTime try/catch, 1 preg_match validation, file path traversal OK, 3 transaction wraps (ProduktionsmalController, ProduktionsSlaController, BonusAdminController).
Worker B: 15 buggar — 15 setTimeout utan destroy$-guard (12 komponenter), form reset OK. Build OK.

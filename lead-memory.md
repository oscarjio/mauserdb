# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-19 (session #186)*
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
Session #105-#131: BUGGJAKT — ~700 buggar totalt. Se lead-memory-archive.md.
Session #132-#154: BUGGJAKT — ~800+ buggar. Se lead-memory-archive.md.
Session #155-#170: BUGGJAKT — ~500+ buggar. Se lead-memory-archive.md.
Session #171: BUGGJAKT — 268 buggar (42 Worker A + 226 Worker B). CORS/preflight (3), logging consistency (39), JSON response (0). Form validation (63), chart destroy (163).
Session #172: BUGGJAKT — 55 buggar (8 Worker A + 47 Worker B). File upload (0, ingen kod), SQL optimization (8: 3 SELECT*, 3 N+1, 2 index). Unsubscribe (7), template type-safety (40).
Session #173: BUGGJAKT — 820 buggar (7 Worker A + 813 Worker B). Rate limiting (0, redan OK), error response (5: 4 $_POST->json_decode, 1 felaktigt success:true), session security (2: Content-Type headers). Lazy-loading (0, redan OK), accessibility (813: 11 aria-label, ~160 spinner role, 642 th scope).
Session #174: BUGGJAKT — 3 buggar (3 Worker A + 0 Worker B). Input validation (3 stored XSS: strip_tags), SQL injection (0). HTTP retry (0), route guards (0).
Session #175: BUGGJAKT — 16 buggar (13 Worker A + 3 Worker B). Logging audit (13 saknade error_log i catch-block), file upload (0, ingen kod). Memory leaks (0, redan korrekt), form validation (3).
Session #176: BUGGJAKT — 3 buggar (0 Worker A + 3 Worker B). CORS (0, redan korrekt), session handling (0, redan korrekt). Error boundaries (0, redan korrekt), pagination limits (3).
Session #177: BUGGJAKT — 3 buggar (0 Worker A + 3 Worker B). File permissions (0), SQL injection (0). HTTP interceptor (0). Chart double-destroy (3).
Session #178: BUGGJAKT — 3 buggar (3 Worker A + 0 Worker B). Error response (3: engelska->svenska). Date/timezone (0), array key (0). Form reset (0), route params (0).
Session #179: BUGGJAKT — 8 buggar (4 Worker A + 4 Worker B). Numeric input (4). HTTP timeout (1), error message display (3).
Session #180: BUGGJAKT — 15 buggar (14 Worker A + 1 Worker B). Logging completeness (14). Loading state (1: 152 spinners).
Session #181: BUGGJAKT — 12 buggar (8 Worker A + 4 Worker B). Input sanitization (8). Error boundaries (4).
Session #182: BUGGJAKT — 13 buggar (8 Worker A + 5 Worker B). DST date calc (8: 1 UnderhallsprognosController 86400->DateTime, 7 dagdiff-guards). HTTP retry/timeout (5: andon-board, produktionstakt, skiftjamforelse, produktionsmal, daglig-sammanfattning). File I/O (0), route guards (0).
Session #183: BUGGJAKT — 105 buggar (14 Worker A + 91 Worker B). Header injection (0), JSON response (1), error_log format (13). Lazy-loading (0), form accessibility (89), null-safety/error-handling (2).
Session #184: BUGGJAKT — 26 buggar (0 Worker A + 26 Worker B). Session timeout (0), SQL concat (0), array key (0). setTimeout cleanup (26: 13 komponenter), HTTP error i18n (0).
Session #185: BUGGJAKT — 10 buggar (6 Worker A + 4 Worker B). Date/time format (0, konsekvent), unused vars (6: 5 overlappande period + 1 oanvand variabel). Template complexity (4), router subscriptions (0).
Session #186: BUGGJAKT — 29 buggar (0 Worker A + 29 Worker B). Numeric input validation (0), LIMIT/OFFSET injection (0). Change detection/OnPush (0), error message typos (29: 5 'Okant'->'Okänt' + 24 'Natverksfel'->'Nätverksfel').
Session #187: BUGGJAKT — 32 buggar (32 Worker A + 0 Worker B). Error response consistency (20: 2 RebotlingStationsdetalj 400->500, 6 UnderhallsloggController raw echo->helpers, 12 OperatorDashboardController raw echo->helpers). Content-Type headers (16: alla 16 controllers saknade application/json). Angular HTTP/null-safety (0, redan korrekt).
Session #188: BUGGJAKT — pagaende. Worker A: PHP deprecated function + null/array safety (33 controllers). Worker B: Angular data flow + race condition audit (38 components).

## OPPEN BACKLOG (prioritetsordning)

BUGGJAKT-FOKUS — inga nya features tills vidare.

### Kvarstaende buggjakt-items (session #189+):
- [ ] PHP file upload validation audit
- [ ] Angular HTTP interceptor error handling
- [ ] Angular memory profiling — tunga sidor
- [ ] PHP session/cookie security audit
- [ ] Angular lazy-loading optimization audit

## BESLUTSDAGBOK (senaste 3)

### 2026-03-19 — Session #186 (klar)
Worker A: 0 buggar — Numeric input validation (0, alla controllers har intval/cast). SQL LIMIT/OFFSET (0, alla har int-cast eller prepared statements).
Worker B: 29 buggar — Change detection/OnPush (0, inga kandidater). Error response consistency (29: 5 'Okant fel'->'Okänt fel' i 3 services, 24 'Natverksfel'->'Nätverksfel' i 4 services).

### 2026-03-19 — Session #187 (klar)
Worker A: 32 buggar — Error response (20: 2 RebotlingStationsdetalj sendError 400->500, 6 UnderhallsloggController raw echo->sendSuccess/sendError, 12 OperatorDashboardController saknade helpers). Content-Type (16: alla controllers saknade application/json header).
Worker B: 0 buggar — HTTP error handling (0, alla 96 services har timeout+catchError+svenska). Component null safety (0, alla 17 komponenter har optional chaining, *ngIf-guards, trackBy, division-by-zero-skydd).

### 2026-03-19 — Session #188 (pagaende)
Worker A: PHP deprecated function + null/array safety audit (33 controllers + helpers).
Worker B: Angular data flow + race condition audit (38 components).

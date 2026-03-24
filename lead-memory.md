# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-24 (session #293)*
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
Session #256: BUGGJAKT — 0 buggar (0 Worker A + 0 Worker B). sprintf/usort/array_push/HostListener/async validator/Renderer2: alla rent.
Session #257: BUGGJAKT — 7 buggar (7 Worker A + 0 Worker B). foreach by-reference: 5 saknade unset() fixade. PDO EMULATE_PREPARES: 2 saknade false fixade. static state/ngAfterViewChecked/HTTP interceptors/forkJoin: alla rent.
Session #258: BUGGJAKT — 3 buggar (3 Worker A + 0 Worker B). type juggling: 2 != till !== fixade. SQL LIMIT/OFFSET injection: 1 fixad. error_reporting/templates null-check/guards/URL consistency: alla rent.
Session #259: BUGGJAKT — 0 buggar (0 Worker A + 0 Worker B). file_get_contents/curl: rent. session handling: rent. isset/null: rent. change detection: rent. lazy loading routes: rent. reactive forms: rent.
Session #260: BUGGJAKT — 1 bugg (0 Worker A + 1 Worker B). date/time: rent. JSON: rent. integer overflow: rent. HTTP timeout: 1 saknad catchError fixad (operator-jamforelse). setInterval: rent.
Session #261: BUGGJAKT — 0 buggar (0 Worker A + 0 Worker B). error_log format: rent. SQL transactions: rent. CORS headers: rent. router params: rent. template expressions: rent.
Session #262: BUGGJAKT — 0 buggar (0 Worker A + 0 Worker B). array key existence: rent. file upload: rent. regex safety: rent. HTTP retry/error: rent. form validation: rent.
Session #263: BUGGJAKT — 0 buggar (0 Worker A + 0 Worker B). date/string: rent. PDO fetch: rent. SQL alias: rent. pipe purity: rent. change detection: rent. template null safety: rent.
Session #264: BUGGJAKT — 0 buggar (0 Worker A + 0 Worker B). header() consistency: rent. json_encode flags: rent. memory leak: rent. HTTP URL: rent.
Session #265: BUGGJAKT — 0 buggar (0 Worker A + 0 Worker B). arithmetic overflow/division: rent. SQL injection ORDER BY/GROUP BY: rent. include/require path: rent. router guard: rent. service singleton: rent. template type safety: rent.
Session #266: BUGGJAKT — 22 buggar (15 Worker A + 7 Worker B). error handling: 15 sendError() utan 500 fixade. SQL transaction: rent. password/token: rent. HTTP response type: rent. form reset: rent. Observable catchError: 7 komponenter fixade.
Session #267: BUGGJAKT — 5 buggar (4 Worker A + 1 Worker B). file I/O: 2 fopen felkontroll fixade. session: rent. CORS: 2 X-CSRF-Token headers fixade. route params: rent. environment config: rent. chunk error: 1 GlobalErrorHandler tillagd.
Session #268: BUGGJAKT — 0 buggar (0 Worker A + 0 Worker B). timezone: rent. array key validation: rent. PDO error mode: rent. HTTP interceptor: rent (komplett 401/403/500/0 + retry). memory profiling: rent (498/499 trackBy, alla subscriptions+timers korrekt).
Session #269: BUGGJAKT — 0 buggar (0 Worker A + 0 Worker B). header injection: rent. numeric validation: rent. mail/SMTP: rent. form dirty state: rent. template type safety: rent.
Session #270: BUGGJAKT — 107 buggar (1 Worker A + 106 Worker B). session race condition: 1 session_write_close i api.php fixad. output buffering: rent. CORS preflight: rent. route resolvers: rent (inga resolvers, lazy loading). getElementById null-guards: 106 saknade null-checks i 61 filer fixade.
Session #271: BUGGJAKT — 0 buggar (0 Worker A + 0 Worker B). error_reporting/display_errors: rent. flock/file writes: rent (all I/O via PDO). PDO prepared statements: rent. Chart.js memory leaks: rent (alla destroy). HTTP retry logic: rent (timeout+catchError+retry).
Session #272: BUGGJAKT — 0 buggar (0 Worker A + 0 Worker B). try/catch+SQL korrekthet: rent (17 controllers, alla sendError korrekt, GROUP BY matchar, division skyddad). addEventListener/subscription leaks: rent (36 komponenter, alla takeUntil+destroy$, inga manuella listeners).
Session #273: BUGGJAKT — 3 buggar (3 Worker A + 0 Worker B). PHP controllers N-Z: 2 fel SQL-kolumnnamn i OperatorDashboardController (operator_id->user_id, orsak/stopporsak->JOIN), 1 saknad Content-Type header i NarvaroController (4 endpoints). Angular services: rent (97 services, alla URL:er/felhantering/cleanup korrekt).
Session #274: BUGGJAKT — 5 buggar (5 Worker A + 0 Worker B). api.php router: rent (117 routes, alla controller-filer finns). MorgonrapportController: 5 felaktiga COUNT(*) ersatta med MAX-aggregering (IBC-produktion raknade PLC-rader istallet for faktiska IBC). Angular template null safety: rent (37 filer). @Input/@Output: rent.
Session #275: BUGGJAKT — 1 bugg (0 Worker A + 1 Worker B). PHP N-Z djupgranskning + SQL UNION/subquery audit: rent. Angular environment config: rent. HTTP interceptor: 1 bugg — error.interceptor visade inte serverns felmeddelande vid 5xx (fixat). Routing guards: rent.
Session #276: BUGGJAKT — 0 buggar (0 Worker A + 0 Worker B). PHP rate limiting: rent. File upload: inga uploads finns. Session/cookie sakerhet: rent. Angular lazy loading: rent. Form validation: rent. Pipes/directives: inga custom finns.
Session #277: BUGGJAKT — 2 buggar (2 Worker A + 0 Worker B). Error logging: 1 log injection fixad (Login/Register). SQL transactions: 1 race condition fixad (MaintenanceController). CSRF: rent. HTTP caching: rent. Change detection: rent. Service singletons: rent.
Session #278: BUGGJAKT — 1 bugg (1 Worker A + 0 Worker B). Date/timezone: 1 strtotime('monday this week') i GamificationController fixad (fel pa sondagar). Array bounds: rent. SQL ORDER BY: rent. Angular memory leaks: rent. Chunk felhantering: rent. HTTP felhantering: rent.
Session #279: BUGGJAKT — 0 buggar (0 Worker A + 0 Worker B). Response headers: rent (api.php sattar globalt). Numeric precision: rent (alla divisioner skyddade). SQL JOINs: rent (~140 LEFT JOINs korrekt). Form state: rent. Environment config: rent. Component communication: rent.
Session #280: BUGGJAKT — 4 buggar (2 Worker A + 2 Worker B). GROUP BY: 4 queries fixade (KassationsanalysController+FeedbackAnalysController). DecimalPipe null: 12 stallen fixade (my-bonus+bonus-admin). error_log: rent. CSRF: rent. Router params: rent. Async rendering: rent.
Session #281: BUGGJAKT — 6 buggar (0 Worker A + 6 Worker B). SQL subqueries: rent. array_key_exists/isset: rent. error_log N-Z: rent. HTTP cancellation: rent. Date/time timezone: 6 new Date() timezone-buggar fixade (3 date-only parsing + 3 toISOString). Form validation: rent.
Session #282: BUGGJAKT — 11 buggar (6 Worker A + 5 Worker B). Mail: 4 buggar (returvarde, XSS, CRLF, UTF-8 subject). Cron: 1 flock-las. CORS cache: 1 Vary header. Chart.js: rent. localStorage: 5 saknade try/catch. SSR: ej relevant.
Session #283: BUGGJAKT — 4 buggar (0 Worker A + 4 Worker B). PHP pagination: rent. SQL UNION: rent. file writes: rent. Route guards: rent. ngModel/FormControl: rent. HTTP polling: 4 saknade isFetching-guards fixade.
Session #284: BUGGJAKT — 0 buggar (0 Worker A + 0 Worker B). array_map/array_filter: rent. preg_match/preg_replace: rent. JSON encode/decode: rent. ViewChild/ContentChild: rent. async pipe vs subscribe: rent. template type safety (a-m): rent.
Session #285: BUGGJAKT — 16 buggar (15 Worker A + 1 Worker B). strtotime sondags-bugg: 15 stallen i 11 filer fixade. array_merge: rent. fetchAll memory: rent. HTTP params encoding: rent. number formatting: 1 division-med-noll fixad (skiftoverlamning). template type safety (n-z): rent.
Session #286: BUGGJAKT — 16 buggar (1 Worker A + 15 Worker B). header/redirect: rent. SQL date range: rent. password/token timing: 1 hash_equals fixad (RegisterController). Router navigation: rent. HttpClient response: rent. setTimeout cleanup: 15 timer-IDn sparade+rensade i 3 komponenter.
Session #287: BUGGJAKT — 134 buggar (7 Worker A + 127 Worker B). in_array strict: rent. file path traversal: rent. GROUP BY: 7 fixade (3 controllers). ngOnChanges: rent (inga implementerar). takeUntil ordering: 127 fixade i 38 filer. service audit: rent.
Session #288: BUGGJAKT — 0 buggar (0 Worker A + 0 Worker B). catch(Exception) loggning: rent. LIKE injection escaping: rent. pagination validering: rent. HTTP retry logic: rent. trackBy *ngFor: rent.
Session #289: BUGGJAKT — 2 buggar (2 Worker A + 0 Worker B). mail() injection: rent. date/time: 2 strtotime edge cases fixade (ProduktionsmalController). array bounds: rent. unsubscribed Observables: rent. template null dereference: rent.
Session #290: BUGGJAKT — 0 buggar (0 Worker A + 0 Worker B). header() redirect: rent (inga header(Location:) i PHP, SPA-arkitektur). PDO fetchColumn: rent (191 anvandningar, alla med korrekt cast/check). error suppression @: rent (13 motiverade @-anvandningar). ngIf race conditions: rent (alla arrayer initieras som []). router event leaks: rent (inga router.events.subscribe).
Session #291: BUGGJAKT — 6 buggar (6 Worker A + 0 Worker B). numeric string comparison: rent (alla ===). mb_string: 6 fixade (4 strtoupper->mb_strtoupper pa initialer, 2 strlen->mb_strlen pa username). SQL COALESCE: rent. HTTP timeout: rent (alla har timeout). ViewChild timing: rent (inga i ngOnInit).
Session #292: BUGGJAKT — 2 buggar (2 Worker A + 0 Worker B). array_key_exists/isset: rent. PDO lastInsertId: rent (ERRMODE_EXCEPTION). GROUP BY strict: 2 fixade (MyStatsController). OnPush: rent (inga OnPush-komponenter). pipe chaining: rent (alla null-guards). HTTP retry: rent (retry bara pa GET).
Session #293: BUGGJAKT — 0 buggar (0 Worker A + 0 Worker B). empty() gotchas: rent (~388 anvandningar, alla sakra). strtotime() edge cases: rent (~550 anvandningar, alla validerade). HTTP retry: rent (bekraftad). trackBy *ngFor: rent. template logik N-Z: rent.

## OPPEN BACKLOG (prioritetsordning)

BUGGJAKT-FOKUS — inga nya features tills vidare.

### Nasta (session #294+):
- [ ] PHP array_key_exists vs isset — controllers N-Z
- [ ] PHP PDO lastInsertId — controllers N-Z
- [ ] PHP SQL GROUP BY strict mode — controllers N-Z
- [ ] PHP intval/floatval pa $_GET/$_POST — user input som inte castas korrekt
- [ ] PHP SQL ORDER BY injection — ORDER BY/LIMIT fran user input utan whitelist
- [ ] Angular FormControl validators — asynkrona validators, required pa dolda falt
- [ ] Angular router param type safety — params.get() utan validering/parseInt

## BESLUTSDAGBOK (senaste 3)

### 2026-03-24 — Session #291 (klar)
Worker A: 6 buggar — mb_string: 4 strtoupper->mb_strtoupper (initialer), 2 strlen->mb_strlen (username). numeric comparison: rent. COALESCE: rent.
Worker B: 0 buggar — HTTP timeout: rent (alla har timeout). ViewChild timing: rent (inga i ngOnInit).

### 2026-03-24 — Session #292 (klar)
Worker A: 2 buggar — GROUP BY strict: 2 subqueries i MyStatsController (op_num i SELECT utan GROUP BY). isset/array_key_exists: rent. lastInsertId: rent.
Worker B: 0 buggar — OnPush: rent (inga OnPush-komponenter). pipe chaining: rent. HTTP retry: rent (retry bara pa GET).

### 2026-03-24 — Session #293 (klar)
Worker A: 0 buggar — empty() gotchas: rent (~388 anvandningar). strtotime() edge cases: rent (~550 anvandningar, alla validerade).
Worker B: 0 buggar — HTTP retry: rent (bekraftad). trackBy *ngFor: rent. template logik N-Z: rent.

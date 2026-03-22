# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-22 (session #249)*
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
Session #190-#194: BUGGJAKT — 40 buggar. Se lead-memory-archive.md.
Session #195: BUGGJAKT — 3 buggar (0 Worker A + 3 Worker B). Controllers ar tunna proxys — logik i classes/.
Session #196: BUGGJAKT — 6 buggar (5 Worker A + 1 Worker B). SQL injection, IBC/h 60x-fel, setTimeout-lacka.
Session #197: BUGGJAKT — 14 buggar (6 Worker A + 8 Worker B). DST/timezone i 4 PHP-klasser, timeout/catchError i 8 Angular-komponenter.
Session #198: BUGGJAKT — 8 buggar (3 Worker A + 5 Worker B). Auth-luckor, XSS i operator-compare, maxlength i 4 komponenter.
Session #199: BUGGJAKT — 7 buggar (5 Worker A + 2 Worker B). N+1 queries (2), saknade LIMIT (3), hardkodade API-URLer (2). Transaction audit + routing guard audit: inga buggar.
Session #200: BUGGJAKT — 10 buggar (8 Worker A + 2 Worker B). Saknade audit trails (7), XSS i category (1), saknade error-nycklar (2). Angular template audit: rent.
Session #201: BUGGJAKT — 6 buggar (5 Worker A + 1 Worker B). N+1 queries (2), redundanta DB-anrop (2), midnight edge case (1), saknad losenordsvalidering (1). Lazy loading audit: rent.
Session #202: BUGGJAKT — 16 buggar (1 Worker A + 15 Worker B). Saknad session timeout-check i api.php (1), saknade role="alert" pa 14 templates (15). File path traversal audit: rent. Memory leak audit: rent.
Session #203: BUGGJAKT — 5 buggar (5 Worker A + 0 Worker B). Saknade bounds pa floatval/intval (4), error disclosure via json_last_error_msg (1). HTTP retry/timeout audit: rent. Form XSS audit: rent.
Session #204: BUGGJAKT — 3 buggar (3 Worker A + 0 Worker B). Race conditions i CertificationController + UnderhallsloggController (3). SQL LIKE injection audit: rent. Router guard audit: rent. Environment config audit: rent.
Session #205: BUGGJAKT — 12 buggar (1 Worker A + 11 Worker B). Saknad timezone i update-weather.php (1), engelska UI-strangar i gamification/operator-ranking/produktions-sla (11). File upload audit: rent. Change detection audit: rent.
Session #206: BUGGJAKT — 21 buggar (7 Worker A + 14 Worker B). CRLF header injection (3), catch Exception->Throwable (4), HTTP error UX (3), form accessibility for/id-par (11). Error handling audit: rent.
Session #207: BUGGJAKT — 17 buggar (4 Worker A + 13 Worker B). SQL felaktiga kolumnnamn (4), saknad sv-locale for pipes (2), pipe operator-precedens (11). Session fixation audit: rent. Lazy loading audit: rent.
Session #208: BUGGJAKT — 16 buggar (2 Worker A + 14 Worker B). CSRF-token-mekanism (1), redundanta timeout/catchError i 8 komponenter (14). File inclusion audit: rent. Template strict null check: rent.
Session #209: BUGGJAKT — 20 buggar (6 Worker A + 14 Worker B). Integer overflow bounds (1), password policy + brute force (3), N+1 query (1), DB migration (1), change detection (2), subscription leaks (4), error logging (8).
Session #210: BUGGJAKT — 40 buggar (5 Worker A + 35 Worker B). strtotime month-overflow (2), DST timberakning (1), duplikat-kontroller (2), oanvand Subject (1), svenska UI-accentfel (34).
Session #211: BUGGJAKT — 19 buggar (7 Worker A + 12 Worker B). Input sanitization (3), float===0.0 (4), login trim (1), prematur admin redirect (4), svenska UI-text (7).
Session #212: BUGGJAKT — 20 buggar (0 Worker A + 20 Worker B). File path traversal: rent. Session handling: rent. SQL param binding: rent. Change detection: rent. A11y: 20 fixar (aria-labels, visually-hidden, role="alert").
Session #213: BUGGJAKT — 34 buggar (34 Worker A + 0 Worker B). Error logging: 34 tomma catch-block i 5 PHP-klasser. CORS/headers: rent. HTTP interceptor: rent. Template strict null check: rent.
Session #214: BUGGJAKT — 24 buggar (3 Worker A + 21 Worker B). Date/time: 3 felaktiga "last monday"-berakningar. SQL JOIN: rent. withCredentials: 8 saknade. maxlength: 13 saknade.
Session #215: BUGGJAKT — 12 buggar (5 Worker A + 7 Worker B). Integer overflow bounds (4), array key null-check (1), pipe null-check (7). Routing guard audit: rent.
Session #216: BUGGJAKT — 4 buggar (0 Worker A + 4 Worker B). SQL ORDER BY: rent. SSRF: rent. HTTP retry: rent. Memory leak: 4 setTimeout-lackor fixade.
Session #217: BUGGJAKT — 4 buggar (0 Worker A + 4 Worker B). Session handling: rent. Error response: rent. SQL UNION: rent. Form validation: 2 fixar. Tree-shaking: 2 fixar.
Session #218: BUGGJAKT — 11 buggar (5 Worker A + 6 Worker B). Date/time month-overflow (1), input sanitization trim/strip_tags (4), logikbugg impossible condition (1), svenska accentfel (5).
Session #219: BUGGJAKT — 5 buggar (5 Worker A + 0 Worker B). Saknade fetch()-kontroller (3), felaktiga kolumnnamn dagmal/daily_goal->rebotling_target (2). Strict null check + polling cleanup: rent.
Session #220: BUGGJAKT — 4 buggar (0 Worker A + 4 Worker B). SQL transaction consistency: rent. Error message disclosure: rent. Form validation: 4 saknade required/disabled. Route guard: rent.
Session #221: BUGGJAKT — 47 buggar (0 Worker A + 47 Worker B). Type coercion/strict comparison: rent. SQL injection ORDER BY/LIMIT: rent. HTTP retry/timeout: rent. Svenska diakritiska tecken (a/o saknade accenter): 47 fixar i templates.
Session #222: BUGGJAKT — 11 buggar (8 Worker A + 3 Worker B). floatval NAN/INF bypass i 5 PHP-filer (8). chartTimers minneslackor i 3 Angular-komponenter (3). Date/time: rent. Reactive forms: rent.
Session #223: BUGGJAKT — 57 buggar (20 Worker A + 37 Worker B). mb_string i 14 PHP-filer (20), HTTP error normalization i 12 Angular-filer (37). File upload/array key/template null-safe: rent.
Session #224: BUGGJAKT — 4 buggar (3 Worker A + 1 Worker B). TOCTOU race conditions i 3 PHP-filer (3), adminGuard race condition (1). Regex injection: rent. Pipe audit: rent.
Session #225: BUGGJAKT — 20 buggar (2 Worker A + 18 Worker B). json_decode felhantering i NewsController (2), catchError i 10 Angular services (18). HTTP header injection: rent. Form dirty-state: rent.
Session #226: BUGGJAKT — 20 buggar (20 Worker A + 0 Worker B). SQL COALESCE i 16 PHP-filer (19), array access utan is_array (1). File path validation: rent. HTTP interceptor: rent. Async pipe: rent.
Session #227: BUGGJAKT — 0 buggar (0 Worker A + 0 Worker B). SQL prepared statements (A-M): rent. Type juggling (A-M): rent. Memory leaks (42 komponenter): rent. Route resolvers: rent.
Session #228: BUGGJAKT — 4 buggar (0 Worker A + 4 Worker B). SQL prepared statements (N-Z): rent. Type juggling (N-Z): rent. Error response consistency: rent. HTTP error message: 4 fixar (alert->inline error).
Session #229: BUGGJAKT — 0 buggar (0 Worker A + 0 Worker B). Unused variable/dead code (117 filer): rent. File inclusion: rent. Template null-check (37 filer): rent. Reactive forms: inga (template-driven, korrekt).
Session #230: BUGGJAKT — 1 bugg (0 Worker A + 1 Worker B). array_key_exists/isset: rent. Exception handling: rent. HTTP retry: 1 fix (POST retry). Change detection: rent.
Session #231: BUGGJAKT — 0 buggar (0 Worker A + 0 Worker B). SQL transaction isolation (47 filer): rent. Date/time edge cases (110 filer): rent. Lazy loading: rent (alla loadComponent). Form state: rent.
Session #232: BUGGJAKT — 6 buggar (4 Worker A + 2 Worker B). Input bounds: 4 saknade ovre granser. Race conditions: rent. HTTP caching: rent. Router guards: 1 UrlTree-fix. Interval cleanup: 1 fix.
Session #233: BUGGJAKT — 248 buggar (7 Worker A + 241 Worker B). SQL LIMIT: 7 saknade LIMIT pa vaxxande tabeller. Error response: rent. URL consistency: 125 hardkodade URLer. A11y keyboard: 116 fixar.
Session #234: BUGGJAKT — 35 buggar (35 Worker A + 0 Worker B). CORS/cookie SameSite: rent. File upload: rent (inga uploads). SQL JOIN: 35 INNER->LEFT JOIN fixar i 16 filer. Reactive state: rent. Form dirty-state: rent.
Session #235: BUGGJAKT — 5 buggar (5 Worker A + 0 Worker B). Error logging: 4 catch utan error_log(). Session fixation: 1 saknad session_regenerate_id. HTTP interceptor: rent. Memory profiling: rent.
Session #236: BUGGJAKT — 0 buggar (0 Worker A + 0 Worker B). SQL transaction rollback: rent. Rate limiting: rent. CORS preflight: rent. Template null-check: rent. Lazy-loaded deps: rent.
Session #237: BUGGJAKT — 2 buggar (0 Worker A + 2 Worker B). File locking: rent. PDO error mode: rent. Timezone: rent. HTTP retry idempotency: rent. Form dirty-state: 2 fixar (canDeactivate-guard).
Session #238: BUGGJAKT — 10 buggar (10 Worker A + 0 Worker B). Output buffering: rent. Prepared stmt reuse: 10 prepare() flyttade utanfor loopar. Header injection: rent. trackBy: rent. Environment config: rent.
Session #239: BUGGJAKT — 20 buggar (15 Worker A + 5 Worker B). Error response consistency: rent. SQL GROUP BY: rent. usort subtraktion->spaceship: 15 fixar i 7 PHP-filer. Pipe null-safety: 5 fixar i benchmarking.html. Preload strategy: rent.
Session #240: BUGGJAKT — 13 buggar (0 Worker A + 13 Worker B). SQL DISTINCT: rent. PDO fetchAll: rent. file_get_contents: rent. HTTP error normalization: 13 catchError utan err-param i 5 services. Form validation: rent.
Session #241: BUGGJAKT — 5 buggar (1 Worker A + 4 Worker B). array_filter callback: 1 fix (0 filtrerades bort). header() audit: rent. NgOnChanges: rent. Template expression complexity: 4 tunga metoder cachade.
Session #242: BUGGJAKT — 22 buggar (22 Worker A + 0 Worker B). SQL subquery->JOIN (4), error_log format (18), file_put_contents: rent (A). HTTP polling interval: rent. Router guard return types: rent (B).
Session #243: BUGGJAKT — 257 buggar (111 Worker A + 146 Worker B). PDO fetch mode FETCH_ASSOC (111 i 25 PHP-filer). trackByIndex->item.id (146 i 146 komponenter). str_replace/preg_replace: rent. array_merge: rent. Template safe navigation: rent.
Session #244: BUGGJAKT — 53 buggar (7 Worker A + 46 Worker B). json_decode utan is_array-guard (7 i 4 PHP-filer). setTimeout utan clearTimeout (42 i 42 komponenter). Form reset vid toggle (4). date()/strtotime(): rent. LIKE escaping: rent.
Session #245: BUGGJAKT — 0 buggar (0 Worker A + 0 Worker B). error_log format: rent. header() content-type: rent. array_key_exists vs isset: rent. Pipe null-safety: rent. ngIf/else loading: rent.
Session #246: BUGGJAKT — 13 buggar (6 Worker A + 7 Worker B). intval/floatval saknade ovre granser i 5 PHP-filer (6). Template method caching i ranking-historik, cykeltid-heatmap, my-bonus (7). file_exists: rent. PDO rollback: rent. HTTP error i18n: rent.
Session #247: BUGGJAKT — 12 buggar (0 Worker A + 12 Worker B). intval/floatval range (N-Z): rent. header() redirect: rent. SQL ORDER BY injection: rent. canDeactivate guard: 12 fixar. OnPush: rent.
Session #248: BUGGJAKT — 0 buggar (0 Worker A + 0 Worker B). array_map/array_filter type-safety (A-M): rent. strpos/str_contains (A-M): rent. HTTP timeout: rent (alla services har timeout). Form validation UX: rent (granskade sidor har inga submit-formular).
Session #249: BUGGJAKT — 0 buggar (0 Worker A + 0 Worker B). array_map/array_filter (N-Z): rent. strpos/str_contains (N-Z): rent. preg_match: rent. file_put_contents: finns ej. Lazy-loaded deps: rent. trackBy: rent. HTTP URLs: rent. OnDestroy: rent.

## OPPEN BACKLOG (prioritetsordning)

BUGGJAKT-FOKUS — inga nya features tills vidare.

### Nasta (session #250):
- [ ] PHP mb_substr/mb_strlen consistency audit
- [ ] PHP array_unique/array_values audit
- [ ] Angular pipe chain null-safety audit
- [ ] PHP header() Content-Type consistency audit
- [ ] Angular FormControl validators audit

## BESLUTSDAGBOK (senaste 3)

### 2026-03-22 — Session #248 (klar)
Worker A: 0 buggar — array_map/array_filter type-safety (A-M): rent (alla callbacks ar typsäkra, inputs ar alltid arrays fran fetchAll). strpos/str_contains (A-M): rent (enda forekomst i KvalitetscertifikatController anvander !== false).
Worker B: 0 buggar — HTTP timeout: rent (alla 59 services har timeout+catchError pa varje HTTP-anrop). Form validation UX: rent (granskade sidor anvander ngModel enbart for filter/datumvaljare utan submit).

### 2026-03-22 — Session #249 (klar)
Worker A: 0 buggar — array_map/array_filter (N-Z): rent (alla typsäkra). strpos/str_contains (N-Z): rent (alla strict comparison). preg_match: rent (alla i boolean context, inga farliga patterns). file_put_contents: finns ej i kodbasen, file_get_contents hanteras korrekt.
Worker B: 0 buggar — lazy-loaded deps: rent (130+ loadComponent OK). trackBy: rent (alla ngFor har trackBy). HTTP URLs: rent (inga hardkodade). OnDestroy: rent (alla 160 komponenter har cleanup).

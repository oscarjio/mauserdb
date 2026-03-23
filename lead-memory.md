# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-23 (session #265)*
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

## OPPEN BACKLOG (prioritetsordning)

BUGGJAKT-FOKUS — inga nya features tills vidare.

### Pagaende (session #265):
- [ ] PHP arithmetic overflow/division audit — Worker A
- [ ] PHP SQL injection i dynamiska ORDER BY/GROUP BY — Worker A
- [ ] PHP include/require path audit — Worker A
- [ ] Angular router guard consistency audit — Worker B
- [ ] Angular service singleton audit — Worker B
- [ ] Angular template type safety audit — Worker B

## BESLUTSDAGBOK (senaste 3)

### 2026-03-23 — Session #263 (klar)
Worker A: 0 buggar — date/string comparison: rent. PDO fetch mode: rent (alla FETCH_ASSOC). SQL column alias: rent.
Worker B: 0 buggar — pipe purity: rent (inga custom pipes). change detection: rent. template null safety: rent (18 templates).

### 2026-03-23 — Session #264 (klar)
Worker A: 0 buggar — header() consistency: rent (centralt i api.php). json_encode flags: rent (alla 1085 anrop har JSON_UNESCAPED_UNICODE).
Worker B: 0 buggar — memory leak: rent (170+ komponenter, alla chart.destroy/removeEventListener/takeUntil korrekt). HTTP URL: rent (alla via environment.apiUrl).

### 2026-03-23 — Session #265 (klar)
Worker A: 0 buggar — arithmetic overflow/division: rent (80+ divisioner, alla skyddade med > 0 guards/ternary). SQL injection ORDER BY/GROUP BY: rent (alla whitelist-validerade). include/require path: rent (enbart require_once med __DIR__).
Worker B: 0 buggar — router guard: rent (90+ routes, alla med authGuard/adminGuard). service singleton: rent (96 services, alla providedIn root). template type safety: rent (20 templates, alla med trackBy/null-guards).

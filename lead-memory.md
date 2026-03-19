# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-19 (session #180)*
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
Session #155: BUGGJAKT — 55 buggar (8 Worker A + 47 Worker B). json_decode null-safety + trackByIndex->trackById.
Session #156: BUGGJAKT — 25 buggar (10 Worker A + 15 Worker B). strtotime false-check, DateTime try/catch, transaction wraps + setTimeout destroy$-guards.
Session #157: BUGGJAKT — 23 buggar (22 Worker A + 1 Worker B). XSS-fixar, svenska felmeddelanden + loading state fix.
Session #158: BUGGJAKT — 79 buggar (78 Worker A + 1 Worker B). XSS ENT_QUOTES-fixar, input sanitization + catchError.
Session #159: BUGGJAKT — 5 buggar (3 Worker A + 2 Worker B). 3 saknade auth-checks (ProduktionsTakt, RebotlingTrendanalys, Veckotrend) + 2 error display (loading state + delete error).
Session #160: BUGGJAKT — 0 buggar (0 Worker A + 0 Worker B). Alla 6 audits passerade rent: SQL edge cases, date/time, array access, template null-safety, HTTP interceptor, router guards.
Session #161: BUGGJAKT — 11 buggar (10 Worker A + 1 Worker B). Error logging, CORS, response format, trackBy.
Session #162: BUGGJAKT — 16 buggar (13 Worker A + 3 Worker B). File I/O error logging, VPN info leak + withCredentials, HTML-entiteter.
Session #163: BUGGJAKT — 5 buggar (5 Worker A + 0 Worker B). Division by zero guards + LIKE injection escaping. Angular memory leaks + route guards OK.
Session #164: BUGGJAKT — 50 buggar (35 Worker A + 15 Worker B). HTTP-statuskoder + race conditions + accessibility (keyboard, ARIA, table scope).
Session #165: BUGGJAKT — 122 buggar (21 Worker A + 101 Worker B). Input length/boundary + logging completeness + HTTP retry + form validation.
Session #166: BUGGJAKT — 9 buggar (7 Worker A + 2 Worker B). CORS/security headers + CSV filename injection + pdf-export error handling.
Session #167: BUGGJAKT — 15 buggar (12 Worker A + 3 Worker B). SQL optimization (SELECT *, N+1) + auth edge cases (inactive login, missing auth) + template null-safety.
Session #168: BUGGJAKT — 13 buggar (8 Worker A + 5 Worker B). Response consistency + error logging + float comparison (Worker A) + HTTP error messages + form dirty state (Worker B).
Session #169: BUGGJAKT — 37 buggar (27 Worker A + 10 Worker B). DST-sakra dagberakningar (strtotime/86400 -> DateTime::diff) i 14 controllers (Worker A) + accessibility aria-labels pa 10 icon-only knappar (Worker B).
Session #170: BUGGJAKT — 34 buggar (34 Worker A + 0 Worker B). Error boundaries (31 tysta catch-block + 4 felaktiga success:true), input validation (1 session read_and_close), session security (2 timeout-buggar). Angular HTTP/routing redan korrekt.
Session #171: BUGGJAKT — 268 buggar (42 Worker A + 226 Worker B). CORS/preflight (3), logging consistency (39), JSON response (0). Form validation (63), chart destroy (163).
Session #172: BUGGJAKT — 55 buggar (8 Worker A + 47 Worker B). File upload (0, ingen kod), SQL optimization (8: 3 SELECT*, 3 N+1, 2 index). Unsubscribe (7), template type-safety (40).
Session #173: BUGGJAKT — 820 buggar (7 Worker A + 813 Worker B). Rate limiting (0, redan OK), error response (5: 4 $_POST->json_decode, 1 felaktigt success:true), session security (2: Content-Type headers). Lazy-loading (0, redan OK), accessibility (813: 11 aria-label, ~160 spinner role, 642 th scope).
Session #174: BUGGJAKT — 3 buggar (3 Worker A + 0 Worker B). Input validation (3 stored XSS: strip_tags), SQL injection (0). HTTP retry (0), route guards (0).
Session #175: BUGGJAKT — 16 buggar (13 Worker A + 3 Worker B). Logging audit (13 saknade error_log i catch-block), file upload (0, ingen kod). Memory leaks (0, redan korrekt), form validation (3).
Session #176: BUGGJAKT — 3 buggar (0 Worker A + 3 Worker B). CORS (0, redan korrekt), session handling (0, redan korrekt). Error boundaries (0, redan korrekt), pagination limits (3: operator-ranking, operatorsbonus, kvalitetscertifikat, alarm-historik).
Session #177: BUGGJAKT — 3 buggar (0 Worker A + 3 Worker B). File permissions (0, sakert), SQL injection (0, PDO genomgaende). HTTP interceptor (0, komplett). Chart double-destroy (3: saglinje-statistik, klassificeringslinje-statistik, prediktivt-underhall).
Session #178: BUGGJAKT — 3 buggar (3 Worker A + 0 Worker B). Error response (3: engelska->svenska i BonusAdmin), date/timezone (0, DST-sakert), array key (0, alla skyddade). Form reset (0, korrekt), route params (0, validerade).
Session #179: BUGGJAKT — 8 buggar (4 Worker A + 4 Worker B). Transaction rollback (0, alla korrekt), numeric input (4: 1 days utan ovre grans, 2 year utan bounds, 1 engelsk text). HTTP timeout (1: polling timeout=interval), error message display (3: 2 dolda felmeddelanden, 28 engelska console.error).
Session #180: BUGGJAKT — 15 buggar (14 Worker A + 1 Worker B). Logging completeness (14 tysta catch-block i 12 controllers), response code audit (0, alla korrekt). Memory leaks (0, redan korrekt), loading state (1: 152 spinners saknade visually-hidden text i 25 filer).

## OPPEN BACKLOG (prioritetsordning)

BUGGJAKT-FOKUS — inga nya features tills vidare.

### Kvarstaende buggjakt-items (session #181+):
- [ ] PHP SQL column name audit
- [ ] PHP input sanitization audit
- [ ] Angular error boundary audit
- [ ] PHP date/timezone edge cases
- [ ] Angular template null-safety

## BESLUTSDAGBOK (senaste 3)

### 2026-03-19 — Session #178 (klar)
Worker A: 3 buggar — Error response consistency (3 engelska API-svar -> svenska i BonusAdminController). Date/timezone (0, alla DST-sakra). Array key existence (0, alla skyddade med ?? eller isset).
Worker B: 0 buggar — Form reset (0, alla formular nollstalls korrekt efter submit). Route param validation (0, alla params valideras med parseInt+isNaN+whitelist).

### 2026-03-19 — Session #179 (klar)
Worker A: 4 buggar — Transaction rollback (0, alla 27 controllers korrekt). Numeric input (4: 1 days utan ovre grans, 2 year utan bounds, 1 engelsk text).
Worker B: 4 buggar — HTTP timeout (1: news polling timeout=interval fixat). Error message display (3: 2 dolda felmeddelanden, 28 engelska console.error).

### 2026-03-19 — Session #180 (klar)
Worker A: 14 buggar — Logging completeness (14 tysta catch-block utan error_log i 12 controllers: Gamification, Alerts, Andon, DagligSammanfattning, Kvalitetscertifikat, Kvalitetstrendanalys, MinDag, Morgonrapport, RankingHistorik, Skiftrapport, VDVeckorapport, Kapacitetsplanering). Response code audit (0, alla korrekt).
Worker B: 1 bugg — Memory leaks (0, alla korrekt). Loading state (1: 152 spinners saknade visually-hidden text for WCAG i 25 HTML-filer).

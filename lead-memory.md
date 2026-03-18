# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-18 (session #167)*
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

## OPPEN BACKLOG (prioritetsordning)

BUGGJAKT-FOKUS — inga nya features tills vidare.

### Kvarstaende buggjakt-items (session #168+):
- [ ] PHP response consistency audit
- [ ] PHP error logging completeness audit
- [ ] Angular HTTP error message audit
- [ ] PHP integer overflow/type coercion audit
- [ ] Angular form reset/dirty state audit

## BESLUTSDAGBOK (senaste 3)

### 2026-03-18 — Session #166 (klar)
Worker A: 7 buggar — 0 file upload (inga uploads i projektet), 7 CORS/security (CSP + HSTS + XSS-protection i api/login/admin/update-weather + 3 CSV filename injection).
Worker B: 2 buggar — 0 memory leaks (alla 41 components OK), 2 error boundary (saknad catch i pdf-export component + service).

### 2026-03-18 — Session #167 (klar)
Worker A: 12 buggar — 9 SQL optimization (7 SELECT * ersatta med specifika kolumner, 2 N+1 queries eliminerade i Gamification+OperatorRanking), 3 auth edge cases (inaktiva anvandare kunde logga in, registerBreak() utan auth, leveransplanering utan admin-check).
Worker B: 3 buggar — 3 template null-safety (slice pa undefined i statistik-dashboard, ngFor utan null-guard i vd-veckorapport + vd-dashboard), 0 route guard (solid implementation med APP_INITIALIZER + initialized$ + sessionStorage cache).

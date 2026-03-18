# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-18 (session #157)*
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
Session #157: BUGGJAKT — 23 buggar (22 Worker A + 1 Worker B). 2 XSS-fixar (htmlspecialchars), 20 svenska felmeddelanden (BonusAdminController) + 1 loading state fix (produktionspuls-widget). ORDER BY OK, route params OK, unused methods OK.

## OPPEN BACKLOG (prioritetsordning)

BUGGJAKT-FOKUS — inga nya features tills vidare.

### Kvarstaende buggjakt-items:
- [x] PHP SQL ORDER BY injection audit — alla whitelist-validerade (session #157)
- [x] Angular route param validation audit — alla validerade (session #157)
- [x] PHP error response format audit — 2 XSS + 20 svenska (session #157)
- [x] Angular loading state audit — 1 fix produktionspuls-widget (session #157)
- [x] PHP unused method audit — inga oanvanda metoder (session #157)
- [ ] Angular HTTP retry/timeout audit
- [ ] PHP input sanitization audit
- [ ] Angular change detection audit

## BESLUTSDAGBOK (senaste 3)

### 2026-03-18 — Session #155 (klar)
Worker A: 8 buggar — error_log OK, integer casting OK, 8 json_decode null-safety.
Worker B: 47 buggar — HTTP errors redan svenska, 47 trackByIndex->trackById (32 komponenter).

### 2026-03-18 — Session #156 (klar)
Worker A: 10 buggar — 7 strtotime false-check, 2 DateTime try/catch, 1 preg_match, 3 transaction wraps.
Worker B: 15 buggar — 15 setTimeout destroy$-guards (12 komponenter), form reset OK.

### 2026-03-18 — Session #157 (klar)
Worker A: 22 buggar — 2 XSS-fixar (htmlspecialchars i VeckotrendController + BonusAdminController), 20 engelska->svenska felmeddelanden (BonusAdminController). ORDER BY OK, unused methods OK.
Worker B: 1 bugg — loading state i produktionspuls-widget. Route params OK.

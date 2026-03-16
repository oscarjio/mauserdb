# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-16 (session #121)*
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

## OPPEN BACKLOG (prioritetsordning)

BUGGJAKT-FOKUS — inga nya features tills vidare.

### Kvarstaende buggjakt-items:
- [ ] Backend classes/ controllers batch 2 (Andon, Alerts, Audit, Register, Login, Admin, etc)
- [ ] Backend routing/api.php (orphan-actions)
- [ ] PHP helper-klasser (AuthHelper, AuditLogger, DatabaseConnection)
- [ ] Funktionstesta Rebotling-sidor (curl-testa endpoints)
- [ ] Funktionstesta Funktioner-sidor (curl-testa endpoints)

## BESLUTSDAGBOK (senaste 3)

### 2026-03-16 — Session #120 (klar)
Worker A: 4 buggar i 3 controllers — 1 tomt catch (DagligBriefing), 2 saknade try/catch (Produktionspuls), 1 LIMIT interpolation (Underhallslogg). 13 av 16 controllers var rena.
Worker B: 37 buggar i 14 filer — 28 service-buggar (hardkodade URLs, saknade timeout/catchError, felaktiga imports) i 12 services + 9 null-guards i 2 komponenter (stopporsak-registrering, stoppage-log).
Totalt: 41 buggar.

### 2026-03-16 — Session #121 (klar)
Worker A: 12 buggar i 3 controllers (av 13 granskade) — 7 tomma catch-block (Gamification), 2 XSS (Bonus), 1 SQL created_at->datum + 1 felaktig HTTP 421->401 + 1 doc-fix (Malhistorik). 10 controllers rena.
Worker B: 29 buggar i 8 services (av 15 granskade) + 14 komponenter (alla rena) — hardkodade URLs, saknade timeout/catchError, saknade environment-imports. 7 services rena.
Totalt: 41 buggar. classes/ batch 1 + frontend batch 6 klara.

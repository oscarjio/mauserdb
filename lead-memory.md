# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-16 (session #119)*
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

## OPPEN BACKLOG (prioritetsordning)

BUGGJAKT-FOKUS — inga nya features tills vidare.
- [x] Rebotling-controllers (EJ live) — session #119 Worker A
- [x] Frontend services batch 3 (OEE + operator-services, 11 st) — session #119 Worker B
- [ ] Frontend services batch 4 (Produktion-services, 10 st)
- [ ] Frontend services batch 5 (Skift/Stopp-services, 12 st)
- [ ] Frontend services batch 6 (Ovrigt, 15 st)
- [ ] Frontend components (null-guards, pipes, trackBy)
- [ ] Backend routing/api.php (orphan-actions)

## BESLUTSDAGBOK (senaste 3)

### 2026-03-16 — Session #118 (klar)
Worker A: 5 buggar i 3 controllers — 2 tomma catch (KassationsanalysController), 1 SQL-injection LIMIT/OFFSET (SkiftoverlamningController), 1 saknad htmlspecialchars 4 stallen (RebotlingStationsdetaljController), 1 tom catch (RebotlingStationsdetaljController).
Worker B: 18 buggar i 5 services — 5 kvalitets-trendbrott (import + URL + timeout/catchError), 2 maskinunderhall (import + URL), 2 ranking-historik (import + URL), 2 rebotling-sammanfattning (import + URL), 7 rebotling (import + 60+ hardkodade URLs + felaktig template literal).
Totalt: 23 buggar.

### 2026-03-16 — Session #119 (klar)
Worker A: 33 buggar i 5 rebotling-controllers — 1 XSS (RebotlingStationsdetalj), 5 buggar (RebotlingTrendanalys: htmlspecialchars + JSON_UNICODE + try/catch), 5 buggar (RebotlingProduct: JSON_UNICODE + log injection), 8 buggar (RebotlingAdmin: JSON_UNICODE), 14 buggar (RebotlingAnalytics: 2 tomma catch + 12 JSON_UNICODE).
Worker B: 13 buggar i 3 services — oee-benchmark (2: URL + import), operatorsbonus (2: URL + import), operators (9: URL + imports + timeout/catchError pa 8 metoder).
Totalt: 46 buggar.

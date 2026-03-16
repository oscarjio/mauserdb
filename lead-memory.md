# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-16 (session #111)*
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

## Bug Hunt Status

Bug Hunts #1-#50 genomforda. Kodbasen har genomgatt systematisk granskning.
Session #57-#104: Feature-utveckling. Se lead-memory-archive.md.
Session #105-#106: BUGGJAKT — 12 backend + 4 frontend + 12 endpoints testade.
Session #107: BUGGJAKT — 122 fixar + 270 trackBy.
Session #108: BUGGJAKT — 13 buggar (10 backend + 3 frontend) + 7 endpoints OK.
Session #109: BUGGJAKT — 33 buggar (11 backend + 22 UTC-datum).
Session #110: BUGGJAKT — 15 buggar (13 backend + 2 imports). Alla controllers/ granskade.
Session #111: BUGGJAKT — 30 buggar. Worker A: 8 i batch 4 (SQL-kolumn, XSS, JOIN). Worker B: 13 i 4 stora classes/ (OEE-formel, operator-lookup, tabellnamn). Lead: 9 routing-buggar + 1 en-dash-bugg i RebotlingController/BonusAdmin.

## OPPEN BACKLOG (prioritetsordning)

BUGGJAKT-FOKUS — inga nya features tills vidare.
- [x] **Batch 4 (8 controllers)** — klar #111
- [x] **classes/ audit del 1 (4 storsta)** — klar #111
- [ ] **classes/ audit del 2** — SkiftoverlamningController, KapacitetsplaneringController, OperatorDashboardController, SkiftrapportController, TvattlinjeController
- [ ] **classes/ audit del 3** — AndonController, GamificationController, HistoriskSammanfattningController m.fl.
- [ ] **Oanvanda variabler** — ~21 st i 4 filer
- [ ] **api.php auth + template null-safety**

## BESLUTSDAGBOK (senaste 3)

### 2026-03-15 — Session #109 (klar)
Worker A: 11 buggar i 6 controllers (XSS, SQL, logik, auth, validering).
Worker B: 22 UTC-datum buggar i 19 filer + OEE/routes audit OK. Totalt: 33.

### 2026-03-15 — Session #110 (klar)
Worker A: 13 buggar i 6 filer. Worker B: 2 imports + audit OK. Totalt: 15.
Alla controllers/ (33 st) granskade.

### 2026-03-16 — Session #111 (klar)
Worker A: 8 buggar i 3 batch 4-filer (2 SQL-kolumn, 2 XSS, 2 JOIN, 1 saknad kolumn, 1 felaktig ref).
Worker B: 13 buggar i 4 stora classes/ (2 OEE-formel, 3 operator-tabell, 6 id/number, 1 tabellnamn, 1 dead code).
Lead: 8 routing-buggar i RebotlingController ($this-> -> $this->analyticsController/adminController) + 1 en-dash-bugg i BonusAdmin.
Totalt: 30 buggar fixade. Alla batch 4 controllers + 4 storsta classes/ granskade.

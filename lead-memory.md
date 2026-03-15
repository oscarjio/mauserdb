# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-15 (session #110)*
*Fullständig historik: lead-memory-archive.md*

---

## Projektöversikt

IBC-tvätteri (1000L plasttankar i metallbur). Systemet ger VD realtidsöverblick + rättvis operatörsbonus.

**Roller:** VD (KPI:er, 10-sek överblick), Operatör (bonusläge live, motiverande), Admin (mål, regler, skift).
**Linjer:** Rebotling (AKTIV, bra data), Tvättlinje/Såglinje/Klassificeringslinje (EJ igång).
**Stack:** Angular 20+ → PHP/PDO → MySQL. PLC → plcbackend (rör ALDRIG) → DB.

## ÄGARENS DIREKTIV (2026-03-15)

- **FOKUS: BUGGJAKT** — koncentrera er på att hitta och fixa buggar
- **Rebotling** — enda linjen med bra data
- **INGA NYA FEATURES** — prioritera kvalitet och stabilitet
- Granska controllers, services, templates systematiskt
- VD ska förstå läget på 10 sekunder

## ABSOLUTA REGLER (bryt ALDRIG)

1. **Rör ALDRIG livesidorna**: `rebotling-live`, `tvattlinje-live`, `saglinje-live`, `klassificeringslinje-live`
2. **Rör ALDRIG plcbackend/** — PLC-datainsamling i produktion
3. **ALLTID bcrypt** — AuthHelper använder password_hash/password_verify. Ändra ALDRIG till sha1/md5
4. **ALDRIG röra dist/** — borttagen från git, ska aldrig tillbaka
5. DB-ändringar → SQL-fil i `noreko-backend/migrations/YYYY-MM-DD_namn.sql` + `git add -f`
6. All UI-text på **svenska**
7. Dark theme: `#1a202c` bg, `#2d3748` cards, `#e2e8f0` text, Bootstrap 5
8. Commit + push bara när feature är klar och bygger
9. Bygg: `cd noreko-frontend && npx ng build`

## ÄGARENS INSTRUKTIONER (dokumentera allt — ägaren ska aldrig behöva upprepa sig)

- Fokus rebotling. Övriga linjer ej igång.
- Systemet är för VD (övergripande koll) + rättvis individuell operatörsbonus.
- DB ligger INTE på denna server — deployas manuellt. DB-ändringar via SQL-migrering.
- Agenterna stannar aldrig — håll arbete igång.
- Ledaragenten driver projektet självständigt — sök internet, granska kod, uppfinn features.
- Lägg till nya funktioner i navigationsmenyn direkt.
- Kunden utvärderar efteråt — jobba fritt och kreativt.
- Graferna behöver detaljerade datapunkter, adaptiv granularitet.

## Tekniska mönster

- **AuthService**: `loggedIn$` och `user$` BehaviorSubjects
- **Lifecycle**: `implements OnInit, OnDestroy` + `destroy$ = new Subject<void>()` + `takeUntil(this.destroy$)` + `clearInterval/clearTimeout` + `chart?.destroy()`
- **HTTP polling**: `setInterval` + `timeout(5000)` + `catchError` + `isFetching` guard
- **APP_INITIALIZER**: `firstValueFrom(auth.fetchStatus())`
- **Math i templates**: `Math = Math;` som class property

## Bug Hunt Status

Bug Hunts #1-#50 genomförda. Kodbasen har genomgått systematisk granskning.
Session #57-#104: Feature-utveckling + features. Se lead-memory-archive.md.
Session #105-#106: BUGGJAKT — 12 backend + 4 frontend + 12 endpoints testade.
Session #107: BUGGJAKT — 122 fixar + 270 trackBy.
Session #108: BUGGJAKT — 13 buggar (10 backend + 3 frontend) + 7 endpoints OK.
Session #109: BUGGJAKT — 33 buggar (11 backend + 22 UTC-datum). Alla controllers/ batch 1-3 del 1 klara.
Session #110: BUGGJAKT — Worker A: 13 buggar i 6 filer (7 SQL-kolumnfel, 2 auth, 1 XSS, 1 logikfel, 2 SQL-join). Worker B: 2 unused imports fixade + audit OK (91 services, 109 charts, streaks). Totalt: 15 buggar.

## ÖPPEN BACKLOG (prioritetsordning)

BUGGJAKT-FOKUS — inga nya features tills vidare.
- [~] **Batch 3 del 2 (9 controllers)** — pågår #110
- [~] **Services/Chart.js/Imports/Streaks** — pågår #110
- [ ] **Batch 4 (8 controllers)** — AlarmHistorik, HistoriskSammanfattning, Kassationsanalys, OperatorOnboarding, OperatorRanking, Produktionsmal, ProduktionsPrognos, Veckorapport
- [ ] **classes/ audit (116 filer)** — ogranskade
- [ ] **api.php auth + template null-safety**

## BESLUTSDAGBOK (senaste 3)

### 2026-03-15 — Session #108 (klar)
Worker A: 10 buggar — 3 SQL/logik, 1 XSS, 4 unused, 1 sort, 1 PHP8.1.
Worker B: 7 endpoints OK + 3 frontend-buggar (UTC-datum + race condition).

### 2026-03-15 — Session #109 (klar)
Worker A: 11 buggar i 6 controllers (XSS, SQL, logik, auth, validering).
Worker B: 22 UTC-datum buggar i 19 filer + OEE/routes audit OK. Totalt: 33.

### 2026-03-15 — Session #110 (klar)
Worker A: 13 buggar i 6 filer — 7 SQL-kolumnfel, 2 auth, 1 XSS, 1 logikfel, 2 SQL-join. Alla 11 controllers granskade.
Worker B: 91 services OK, 109 charts OK, 2 unused imports fixade, streaks OK. Totalt: 15 buggar.
Alla controllers/ (33 st) nu granskade. Nästa: batch 4 (8 ogranskade) + classes/ audit (116 filer).

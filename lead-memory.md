# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-11 (session #50)*
*Fullständig historik: lead-memory-archive.md*

---

## Projektöversikt

IBC-tvätteri (1000L plasttankar i metallbur). Systemet ger VD realtidsöverblick + rättvis operatörsbonus.

**Roller:** VD (KPI:er, 10-sek överblick), Operatör (bonusläge live, motiverande), Admin (mål, regler, skift).
**Linjer:** Rebotling (AKTIV, bra data), Tvättlinje/Såglinje/Klassificeringslinje (EJ igång).
**Stack:** Angular 20+ → PHP/PDO → MySQL. PLC → plcbackend (rör ALDRIG) → DB.

## ÄGARENS DIREKTIV (2026-03-09)

- **FOKUS: Rebotling** — enda linjen med bra data
- Statistiksidan — enkel överblick hur produktionen går över tid
- VD ska förstå läget på 10 sekunder
- Buggjakt löpande
- Övriga rebotling-sidor — utveckla och förbättra

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

Bug Hunts #1-#50 genomförda. Kodbasen har genomgått systematisk granskning:
formularvalidering, error states, subscribe-läckor, responsiv design, timezone, dead code,
chart.js lifecycle, export, PHP-robusthet, auth/session, data-konsistens, CSS/UX,
race conditions, accessibility, null safety, HTTP timeout/catchError.
Session #48: Stopporsak-drill-down + Annotationer i grafer — klara.
Session #49: Realtids-OEE-gauge + Exportera grafer som bild — klara.
Session #50: Produktionsmål-tracker + Månadsrapport — startade.

## ÖPPEN BACKLOG (prioritetsordning)

### Rebotling-fokus (pågår session #50)
- [PÅGÅR] **Produktionsmål-tracker** — visuell progress mot dagsmål/veckamål
- [PÅGÅR] **Månadsrapport** — auto-genererad sammanfattning, PDF-export

### Förbättringar
- [ ] **Bonus "What-if"-simulator** — admin justerar parametrar, ser effekt i realtid
- [ ] **Operatörsnärvaro-tracker** — kalendervy från rebotling_ibc-data
- [ ] **Dashboard-widget layout** — VD väljer widgets på startsidan
- [ ] **Skiftjämförelse-vy** — dag vs nattskift prestandajämförelse
- [ ] **Alerts/notifieringar** — varning vid låg OEE eller lång stopptid

### Nya sidor
- [ ] **Skiftplaneringsvy** (`/admin/skiftplan`) — kalendervy, operatörer per skift
- [ ] **Underhållslogg** (`/admin/underhall`) — logga planerat underhåll, koppla till stopptid

## BESLUTSDAGBOK (senaste 3)

### 2026-03-11 — Session #50 (startade)
Worker 1 (Produktionsmål-tracker): Progress-ring dagsmål/veckamål, streak, countdown. Backend: production-goals endpoints. DB: rebotling_production_goals. Worktree.
Worker 2 (Månadsrapport): Ny sida /rapporter/manad. Auto-genererad sammanfattning + PDF-export. Backend: monthly-report endpoint. Worktree.

### 2026-03-10 — Session #49 (klar)
Worker 1 (Realtids-OEE-gauge): Stor cirkulär OEE-gauge överst på statistiksidan. Chart.js doughnut, färgkodad, progress bars A/P/Q, KPI-rutor, periodselektor, auto-refresh 60s.
Worker 2 (Exportera grafer som bild): PNG-exportknapp på alla 6 graf-komponenter. Ny chart-export utility.

### 2026-03-10 — Session #48 (klar)
Worker 1 (Stopporsak-drill-down): Klickbar Pareto → overlay-modal med KPI:er, per-operatör, per-dag, enskilda stopp.
Worker 2 (Annotationer i grafer): Ny tabell rebotling_annotations, CRUD-API, vertikala annotationslinjer i cykeltrend-graf.

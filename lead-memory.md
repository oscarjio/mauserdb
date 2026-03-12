# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-12 (session #74)*
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
Session #57: Underhållslogg + Cykeltids-heatmap per timme — klara.
Session #58: OEE-benchmark + Skiftrapport PDF — klara.
Session #59: Operatörsranking historik + Operatörs-feedback analys — klara.
Session #60: Daglig sammanfattning + Produktionskalender — klara.
Session #61: Målhistorik-analys + Skiftjämförelse-dashboard — klara.
Session #62: Underhållsprognos + Kvalitetstrend per operatör — klara.
Session #63: Stopporsak-trendanalys + Energi/effektivitetsvy — klara.
Session #64: Produktionsmål vs utfall + Maskinutnyttjandegrad — klara.
Session #65: Realtids-produktionstakt + Kassationsanalys — klara.
Session #66: Andon-board/fabriksskärm + Veckorapport-generator — klara.
Session #67: Operatörsportal + Alarm-historik dashboard — klara.
Session #68: Produktions-heatmap + Stopporsak Pareto-diagram — klara.
Session #69: VD:s morgonrapport + OEE-waterfall/brygga — klara.
Session #70: Drifttids-timeline + Skiftvis produktionsjämförelse — klara.
Session #71: Kassationsorsak-drill-down + Produktionspuls realtids-ticker — klara.
Session #72: Första-timme-analys + Operatörs-personligt dashboard — klara.
Session #73: Produktionsprognos + Stopporsak per operatör — klara.
Session #74: Operatörs-onboarding tracker + Skiftöverlämningslogg — klara.

## ÖPPEN BACKLOG (prioritetsordning)

- [x] **Operatörs-onboarding tracker** — lärlingskurva nya operatörer
- [x] **Skiftöverlämningslogg** — digital överlämning mellan skift
- [ ] **Snabbkommandon/favoritvy** — VD:s bokmärken
- [ ] **Maskinunderhåll — serviceintervall-vy** — planerade underhåll + varningar
- [ ] **Batch-spårning** — följ en batch/order genom linjen
- [ ] **Produktionseffektivitet per timme** — heatmap vilka timmar är mest produktiva
- [ ] **Kvalitetsanalys — trendbrott-detektion** — flagga avvikande kassationsgrad
- [ ] **Operatörsjämförelse sida-vid-sida** — jämför 2-3 operatörer i samma graf

## BESLUTSDAGBOK (senaste 3)

### 2026-03-12 — Session #74 (klar)
Worker 1 (Operatörs-onboarding tracker): Visa nya operatörers lärlingskurva — veckovis IBC/h de första 12 veckorna vs teamsnitt. Chart.js linjediagram, KPI-kort, statusfärger (grön/gul/röd). Backend: OperatorOnboardingController (3 endpoints: onboarding-overview, operator-curve, team-stats).
Worker 2 (Skiftöverlämningslogg): Digital överlämning mellan skift. Formulär för avgående operatör + automatisk KPI-data. Historik-lista, detaljvy, filtrering. NY DB-tabell. Backend: SkiftoverlamningController (4 endpoints: list, detail, create, shift-kpis).

### 2026-03-12 — Session #73 (klar)
Worker 1 (Produktionsprognos): VD ser beräknat antal IBC till skiftslut. Stor tydlig siffra, progressbar, trendindikator. Skifttider dag/kväll/natt. Auto-refresh 60s. Backend: ProduktionsPrognosController (2 endpoints: forecast, shift-history).
Worker 2 (Stopporsak per operatör): Vilka operatörer har mest stopp, vilka orsaker, flagga hög stopptid. Drill-down per operatör. Chart.js horisontella staplar + donut. Backend: StopporsakOperatorController (3 endpoints: overview, operator-detail, reasons-summary).

### 2026-03-12 — Session #72 (klar)
Worker 1 (Första-timme-analys): Analyserar första timmen efter skiftstart — tid till första IBC, ramp-up-kurva (10-min-intervaller), jämförelse mot snitt. Identifierar långsamma starter. Backend: ForstaTimmeAnalysController (2 endpoints: analysis, trend).
Worker 2 (Operatörs-personligt dashboard): Varje operatör ser sin egen statistik — IBC, IBC/h, kvalitet, ranking, trender vs teamsnitt. Motiverande prestationer/milstolpar. Backend: OperatorDashboardController (3 endpoints: my-stats, my-trend, my-achievements).

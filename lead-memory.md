# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-12 (session #71)*
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

## ÖPPEN BACKLOG (prioritetsordning)

- [x] **Kassationsorsak-drill-down** — hierarkisk vy orsak → händelse
- [x] **Produktionspuls — realtids-ticker** — scrollande ticker på startsidan
- [ ] **Operatörs-onboarding tracker** — lärlingskurva nya operatörer
- [ ] **Skiftöverlämningslogg** — digital överlämning mellan skift
- [ ] **Snabbkommandon/favoritvy** — VD:s bokmärken
- [ ] **Maskinunderhåll — serviceintervall-vy** — planerade underhåll + varningar
- [ ] **Första-timme-analys** — uppstartstid efter skiftstart
- [ ] **Operatörs-personligt dashboard** — egen statistik + jämförelse mot team

## BESLUTSDAGBOK (senaste 3)

### 2026-03-12 — Session #71 (klar)
Worker 1 (Kassationsorsak-drill-down): Hierarkisk vy — klicka från kassationsgrad → orsaker → enskilda händelser. Chart.js horisontella staplar + trendlinje. Backend: KassationsDrilldownController (3 endpoints: overview, reason-detail, trend).
Worker 2 (Produktionspuls realtids-ticker): Scrollande börsticker med senaste händelser (IBC, stopp, driftstatus). CSS marquee-animation. 4 KPI-snabbkort. Auto-refresh 30s. Backend: ProduktionspulsController (2 endpoints: pulse, live-kpi).
Backlog: Lade till Första-timme-analys + Operatörs-personligt dashboard. Rensade klara items.

### 2026-03-12 — Session #70 (klar)
Worker 1 (Drifttids-timeline): Visuell tidslinje per dag — gröna block=körning, röda=stopp, grå=ej planerat. Klickbara block med detaljer. Datumväljare med navigation. Backend: DrifttidsTimelineController (2 endpoints: timeline-data, summary).
Worker 2 (Skiftvis produktionsjämförelse): Jämför skift A/B/C: IBC/h, stopptid, kassation, OEE. Grupperade staplar, radar-chart, rankingtabell, trendgraf. Backend: SkiftjamforelseController (3 endpoints: comparison, trend, summary).

### 2026-03-12 — Session #69 (klar)
Worker 1 (VD:s morgonrapport): Gårdagssammanfattning med mål vs utfall, stopp, kassation, varningar, highlights. Datumväljare, utskriftsvänlig. Backend: MorgonrapportController.
Worker 2 (OEE-waterfall/brygga): Waterfall-diagram som visar var produktionstid förloras: tillgänglighet → prestanda → kvalitet. 4 KPI-kort (OEE/A/P/Q %), periodväljare. Backend: OeeWaterfallController.

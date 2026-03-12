# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-12 (session #78)*
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
Session #75: Operatörsjämförelse sida-vid-sida + Produktionseffektivitet per timme — klara.
Session #76: Snabbkommandon/favoritvy + Kvalitetsanalys trendbrott-detektion — klara.
Session #77: Statistik-dashboard sammanfattning + Maskinunderhåll serviceintervall — klara.
Session #78: Batch-spårning + Kassationsorsak-statistik — klara.

## ÖPPEN BACKLOG (prioritetsordning)

- [x] **Batch-spårning** — följ en batch/order genom linjen
- [x] **Kassationsorsak-statistik** — Pareto + trend per orsak
- [ ] **Skiftplanering — bemanningsöversikt** — kapacitetsplanering per skift
- [ ] **Produktionskostnad per IBC** — uppskattad kostnad per producerad IBC
- [ ] **Operatörsbonus-kalkylator** — transparent individuell bonusmodell
- [ ] **Produktions-SLA/måluppfyllnad** — dagliga/veckovisa mål + uppfyllnadsgrad

## BESLUTSDAGBOK (senaste 3)

### 2026-03-12 — Session #78 (klar)
Worker 1 (Batch-spårning): Ny sida för att följa batchar/ordrar. Aktiva batchar med progress, batch-detalj med operatörer/cykeltider/kassation, skapa/avsluta batch. NYA DB-tabeller: batch_order + batch_ibc. Chart.js progress-diagram. Backend: BatchSparningController (6 endpoints).
Worker 2 (Kassationsorsak-statistik): Pareto-diagram (stapel + kumulativ linje, 80/20-gräns), trenddiagram per orsak, per operatör, per skift, drilldown. NYA DB-tabeller: kassationsorsak_register + kassation_logg. Backend: KassationsorsakController (6 endpoints).

### 2026-03-12 — Session #77 (klar)
Worker 1 (Statistik-dashboard sammanfattning): VD:s 10-sek överblick. 6 KPI-kort (IBC idag/vecka, kassation%, drifttid%, aktiv operatör, snitt IBC/h). Chart.js dual Y-axel (produktion + kassation% 30d). 7-dagars tabell med färgkodning. Statusindikator grön/gul/röd. Auto-refresh 60s. Backend: StatistikDashboardController (4 endpoints: summary, production-trend, daily-table, status-indicator).
Worker 2 (Maskinunderhåll — serviceintervall-vy): Ny sida med maskinlista, servicestatus (grön/gul/röd), servicehistorik, registreringsformulär. NYA DB-tabeller: maskin_register + maskin_service_logg. 6 seed-maskiner. Chart.js horisontellt tidslinje-diagram. Backend: MaskinunderhallController (6 endpoints: overview, machines, machine-history, timeline, add-service, add-machine).

### 2026-03-12 — Session #76 (klar)
Worker 1 (Snabbkommandon/favoritvy): VD bokmärker mest använda sidor, visas som snabblänkar-kort. Ny DB-tabell user_favoriter. Backend: FavoriterController (4 endpoints: list, add, remove, reorder). Frontend: favorithanterare + snabblänkar på startsidan.
Worker 2 (Kvalitetsanalys trendbrott-detektion): Automatisk flagga vid avvikande kassationsgrad (±2σ). Linjediagram med rörligt medelvärde + konfidensband. Alerts-lista sorterad efter allvarlighetsgrad. Drill-down per dag. Backend: KvalitetsTrendbrottController (3 endpoints: overview, alerts, daily-detail).


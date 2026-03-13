# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-13 (session #92)*
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

Bug Hunts #1-#50 genomförda. Kodbasen har genomgått systematisk granskning.
Session #57-#91: Feature-utveckling löpande. Se lead-memory-archive.md för detaljer.
Session #91: Operatörs-prestanda scatter-plot klar (duplicerat commit från #90).
Session #92: Rebotling stationsdetalj-dashboard + VD veckorapport + buggjakt — klara.

## ÖPPEN BACKLOG (prioritetsordning)

- [x] **Rebotling stationsdetalj-dashboard** — drill-down per station med realtids-OEE (klar #92)
- [x] **VD veckorapport** — veckosammanfattning med KPI-jämförelse, utskriftsvänlig (klar #92)
- [ ] **Operatörs-tidrapport** — automatisk tidrapport baserat på skiftschema + aktivitet
- [ ] **Realtids-notifikationer** — push-notiser vid kritiska händelser
- [ ] **Dashboards favoritlayout** — VD:s anpassningsbara startsida
- [ ] **Operatörs-schemaöversikt** — veckovis schemavy med bemanningsgrad
- [ ] **Rebotling skiftöverlämning** — digital checklista vid skiftbyte
- [ ] **Kassationsorsak-analys** — Pareto-diagram top-5 kassationsorsaker

## BESLUTSDAGBOK (senaste 3)

### 2026-03-13 — Session #90 (klar)
Worker 1 (Rebotling trendanalys): Automatisk trend-identifiering — 3 trendkort (OEE, produktion, kassation) med linjär regression, slope/dag, alert-nivåer (ok/warning/critical), sparklines. Huvudgraf 90d med 7d MA + trendlinje + prognos-zon 7d framåt. Veckosammanfattning 12v. Anomali-detektion (±2 stddev). Auto-polling 60s. Backend: RebotlingTrendanalysController (5 endpoints).
Worker 2 (Operatörs-prestanda scatter-plot): Scatter plot hastighet vs kvalitet — X=cykeltid, Y=kvalitet(100%-kass), punktstorlek=antal IBC, färg per skift. Kvadrant-labels. Sorterbar ranking-tabell (top 3 grön, bottom 3 röd). Expanderbar detaljvy per operatör med daglig graf + veckotrend. Skiftjämförelse 3 kort. Backend: OperatorsPrestandaController (5 endpoints). Data från rebotling_skiftrapport + operators.

### 2026-03-13 — Session #89 (klar)
Worker 1 (Produktions-dashboard startsida): "Command center" for VD — 6 KPI-kort med trendpilar, 2 Chart.js-grafer, alarm-lista, stationsstatus-tabell, senaste IBC. Auto-polling 30s. Backend: ProduktionsDashboardController (6 endpoints).
Worker 2 (Rebotling kapacitetsplanering): Planerad vs faktisk kapacitet — 5 KPI-kort, kapacitetsdiagram, stationsutnyttjande, stopporsaker, tid-fordelning. Vecko-oversikt 12v. Backend: KapacitetsplaneringController (6 endpoints).

### 2026-03-13 — Session #92 (klar)
Worker 1 (Rebotling stationsdetalj-dashboard): Klickbar stationsvy — drill-down per station med realtids-OEE, senaste IBCer, stopphistorik, KPI-kort, trendgraf 30d. Backend: RebotlingStationsdetaljController.
Worker 2 (VD veckorapport + buggjakt): Veckosammanfattning med KPI-jämförelse vecka-för-vecka, trender, anomalier, top/bottom operatörer, stopporsaker, utskriftsvänlig. Dessutom systematisk buggjakt: memory leaks, byggfel, lifecycle-problem.

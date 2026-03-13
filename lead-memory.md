# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-13 (session #89)*
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
Session #57-#89: Feature-utveckling löpande. Se lead-memory-archive.md för detaljer.
Session #89: Produktions-dashboard startsida + Rebotling kapacitetsplanering — klara.

## ÖPPEN BACKLOG (prioritetsordning)

- [ ] **Operatörs-tidrapport** — automatisk tidrapport baserat på skiftschema + aktivitet
- [ ] **Realtids-notifikationer** — push-notiser vid kritiska händelser
- [ ] **Dashboards favoritlayout** — VD:s anpassningsbara startsida
- [ ] **Operatörs-schemaöversikt** — veckovis schemavy med bemanningsgrad
- [ ] **Operatörs-prestanda scatter-plot** — hastighet vs kvalitet per operatör
- [ ] **Rebotling trendanalys** — automatisk identifiering av negativa trender
- [ ] **Energi- och resursöversikt** — uppskattad förbrukning per IBC

## BESLUTSDAGBOK (senaste 3)

### 2026-03-13 — Session #89 (klar)
Worker 1 (Produktions-dashboard startsida): "Command center" for VD — 6 KPI-kort med trendpilar (prod, OEE, kassation, drifttid, stationer, skift), 2 Chart.js-grafer (vecko-prod + OEE-trend), alarm-lista, stationsstatus-tabell, senaste IBC. Auto-polling 30s med pulsanimation. Backend: ProduktionsDashboardController (6 endpoints).
Worker 2 (Rebotling kapacitetsplanering): Planerad vs faktisk kapacitet — 5 KPI-kort, kapacitetsdiagram (stacked bar + linjer for max/mal/snitt), stationsutnyttjande (horisontellt bar), stopporsaker (doughnut), tid-fordelning (stacked bar). Vecko-oversikt 12 veckor. Periodselektor 7/30/90d. Backend: KapacitetsplaneringController (6 endpoints).

### 2026-03-13 — Session #88 (klar)
Worker 1 (Maskinhistorik per station): Detaljerad vy per maskin/station — stationsväljare, 6 KPI-kort, drifttids-graf (bar+linje), OEE-trenddiagram med delkomponenter, stopphistorik-tabell, jämförelsematris alla stationer. Periodselektor 7/30/90d. Backend: MaskinhistorikController (6 endpoints). Använder rebotling_ibc + rebotling_onoff.
Worker 2 (Kassationskvot-alarm): Realtidsövervakning kassationsgrad med färgkodade KPI-kort (grön/gul/röd), puls-animation vid alarm. 24h trendgraf med tröskellinjer. Per-skift-vy 7 dagar. Alarm-historik. Top-5 orsaker. Tröskelinställning (VD). Auto-polling 60s. NY tabell: rebotling_kassationsalarminst. Backend: KassationskvotAlarmController.

### 2026-03-13 — Session #87 (klar)
Worker 1 (Skiftrapport-sammanställning): Daglig rapport per skift (dag/kväll/natt) — produktion, kassation, OEE, stopp per skift. Veckosammanställning + skiftjämförelse. Chart.js stapeldiagram + linjediagram. PDF-export. Backend: SkiftrapportController.
Worker 2 (Produktionsmål-dashboard): VD sätter vecko/månadsmål. Progress med doughnut-diagram. Prognos. Daglig produktion stapeldiagram + mål-linje. NY tabell: rebotling_produktionsmal. Backend: ProduktionsmalController.

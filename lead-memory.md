# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-12 (session #68)*
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

## ÖPPEN BACKLOG (prioritetsordning)

- [x] **Produktions-heatmap** — matrisvy timme x dag
- [x] **Stopporsak Pareto-diagram** — 80/20-analys
- [ ] **VD:s morgonrapport** — automatisk gårdagssammanfattning
- [ ] **Operatörs-onboarding tracker** — lärlingskurva nya operatörer
- [ ] **Skiftöverlämningslogg** — digital överlämning mellan skift
- [ ] **Snabbkommandon/favoritvy** — VD:s bokmärken
- [ ] **OEE-waterfall/brygga** — visuell OEE-förlustanalys
- [ ] **Skiftvis produktionsjämförelse** — jämför skift A/B/C

## BESLUTSDAGBOK (senaste 3)

### 2026-03-12 — Session #68 (klar)
Worker 1 (Produktions-heatmap): Matrisvy dagar x timmar (06-22) med färgkodning — grön=hög, mörk=låg produktion. 4 KPI-kort, periodväljare, hover-tooltip. Backend: HeatmapController (2 endpoints: heatmap-data, summary).
Worker 2 (Stopporsak Pareto-diagram): 80/20-analys med Chart.js combo-chart: staplar (stopptid fallande) + kumulativ linje med 80%-markering. Dubbel Y-axel. Backend: ParetoController (2 endpoints: pareto-data, summary).
Backlog: Lade till OEE-waterfall/brygga + Skiftvis produktionsjämförelse.

### 2026-03-12 — Session #67 (klar)
Worker 1 (Operatörsportal): Personlig dashboard per operatör — IBC idag/vecka/månad vs teamsnitt, bonusberäkning, ranking (#X av Y), 30d trendgraf vs teamsnitt. Backend: OperatorsportalController (3 endpoints: my-stats, my-trend, my-bonus).
Worker 2 (Alarm-historik dashboard): Lista alla triggade larm med severity (critical/warning/info). Byggs från befintlig data: långa stopp, låg takt, hög kassation, maskinstopp. KPI-kort, staplat stapeldiagram, filtrerbar tabell. Backend: AlarmHistorikController (3 endpoints: list, summary, timeline).

### 2026-03-12 — Session #66 (klar)
Worker 1 (Andon-board/fabriksskärm): TV-optimerad helskärmsvy med dagens produktion vs mål, aktuell takt, maskinens status (KÖR/STOPP), senaste stopp, kvalitet, skiftinfo, klocka. Backend: AndonController (1 samlat endpoint). Auto-refresh 30s, stor text, pulsande statusindikator.
Worker 2 (Veckorapport-generator): Utskriftsvänlig veckosammanfattning med KPI:er (produktion, effektivitet, stopp, kvalitet). Veckoväljare, @media print CSS, Ctrl+P till PDF. Backend: VeckorapportController (1 samlat endpoint). Trendpilar vs föregående vecka.

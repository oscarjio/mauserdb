# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-11 (session #57)*
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
Session #48-#56: Se lead-memory-archive.md för detaljer.
Session #57: Underhållslogg + Cykeltids-heatmap per timme — klara.

## ÖPPEN BACKLOG (prioritetsordning)

- [x] **Underhållslogg** — operatör loggar underhåll med kategori + tid
- [x] **Cykeltids-heatmap per timme** — mönster morgon vs kväll
- [ ] **OEE-benchmark jämförelse** — aktuell vs branschsnitt
- [ ] **Skiftrapport PDF-export** — daglig sammanfattning som PDF
- [ ] **Operatörsranking historik** — leaderboard-trender över tid
- [ ] **Kvalitetsavvikelse-trend** — drilldown kasserade IBC
- [ ] **Daglig sammanfattning auto-generering** — KPI-sammanfattning utan navigation
- [ ] **Produktionskalender förbättring** — volym + kvalitet per dag med färgkodning

## BESLUTSDAGBOK (senaste 3)

### 2026-03-11 — Session #57 (klar)
Merge-konflikter lösta. Stopporsak + Skiftöverlämningsmall redan impl. — backlog rensad.
Worker 1 (Underhållslogg): CRUD med kategori/typ/varaktighet, 4 statistikkort, filtrerbar historik, CSV-export. Backend: UnderhallsloggController + DB-migrering. Commit 406b222.
Worker 2 (Cykeltids-heatmap): HTML-tabell-heatmap grön→gul→röd, Chart.js dygnsmönster dubbel Y-axel, klickbar drilldown per operatör, sammanfattningskort. Backend: CykeltidHeatmapController. Commit 8b10f12.

### 2026-03-11 — Session #56 (klar)
Worker 1 (Dashboard-widget layout): Kugghjulsikon på statistiksidan, 8 widgets med toggle + up/down-ordning, sparar per user i DB. Backend: DashboardLayoutController + DB-migrering (dashboard_layouts). Bygger OK.
Worker 2 (Effektivitet per produkttyp): Jämförelse per produkttyp — sammanfattningskort, kvalitetsranking, cykeltidstrend (Chart.js), IBC/h horisontell bar, head-to-head jämförelse. Backend: ProduktTypEffektivitetController. Bygger OK.

### 2026-03-11 — Session #55 (klar)
Worker 1 (Kassationsanalys): Drilldown per stopporsak + kassationstyp, stackad stapelgraf, trendjämförelse, periodselektor. Backend: KassationsanalysController.
Worker 2 (Alerts/notifieringar): Realtidsvarningar vid låg OEE/lång stopptid/hög kassation, kvittering, tröskelvärden, badge i header med polling. Backend: AlertsController + DB-migrering.

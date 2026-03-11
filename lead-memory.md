# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-11 (session #56)*
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
Session #50: Produktionsmål-tracker + Månadsrapport — klara.
Session #51: Bonus "What-if"-simulator + Skiftjämförelse-vy — klara.
Session #52: Maskinupptid-heatmap + Topp-5 leaderboard — klara.
Session #53: Operatörsnärvaro-tracker + Produktionspuls-ticker — klara.
Session #54: Operatörs-dashboard "Min dag" + Veckotrend sparklines — klara.
Session #55: Kassationsanalys + Alerts/notifieringar — klara.
Session #56: Dashboard-widget layout + Effektivitet per produkttyp — klara.

## ÖPPEN BACKLOG (prioritetsordning)

- [x] **Dashboard-widget layout** — VD väljer widgets på startsidan
- [x] **Effektivitet per produkttyp** — FoodGrade vs NonUN vs Tvättade
- [ ] **Stopporsak-snabbregistrering** — mobilvänlig knappmatris
- [ ] **Skiftöverlämningsmall** — auto-sammanfattning vid skiftbyte
- [ ] **Underhållslogg** — operatör loggar underhåll med kategori + tid
- [ ] **Cykeltids-heatmap per timme** — mönster morgon vs kväll
- [ ] **OEE-benchmark jämförelse** — aktuell vs branschsnitt
- [ ] **Skiftrapport PDF-export** — daglig sammanfattning som PDF
- [ ] **Operatörsranking historik** — leaderboard-trender över tid

## BESLUTSDAGBOK (senaste 3)

### 2026-03-11 — Session #56 (klar)
Worker 1 (Dashboard-widget layout): Kugghjulsikon på statistiksidan, 8 widgets med toggle + up/down-ordning, sparar per user i DB. Backend: DashboardLayoutController + DB-migrering (dashboard_layouts). Bygger OK.
Worker 2 (Effektivitet per produkttyp): Jämförelse per produkttyp — sammanfattningskort, kvalitetsranking, cykeltidstrend (Chart.js), IBC/h horisontell bar, head-to-head jämförelse. Backend: ProduktTypEffektivitetController. Använder rebotling_ibc.produkt + rebotling_products. Bygger OK.

### 2026-03-11 — Session #55 (klar)
Worker 1 (Kassationsanalys): Drilldown per stopporsak + kassationstyp, stackad stapelgraf (Chart.js), trendjämförelse, periodselektor 7/14/30/90d, orsaksanalys-tabell med klickbar drilldown. Backend: KassationsanalysController. Commit ca9f0bc.
Worker 2 (Alerts/notifieringar): Realtidsvarningar vid låg OEE/lång stopptid/hög kassation, kvittering, tröskelvärden, badge i header med polling var 60s, tre flikar (aktiva/historik/inställningar). Backend: AlertsController + DB-migrering (alerts + alert_settings). Commit b72b4e2.

### 2026-03-11 — Session #54 (klar)
Worker 1 (Operatörs-dashboard "Min dag"): Personlig vy — dagens IBC, cykeltid-trend (Chart.js), kvalitet, bonus, progressbars mot mål, motivationstext. Backend: MinDagController. Commit d264777.
Worker 2 (Veckotrend sparklines): Canvas 2D sparklines, 7-dagars trend (4 KPI:er), quadratic bezier + gradient fill, animerad 500ms, integrerad överst på statistiksidan. Backend: VeckotrendController. Commit 2384b65.

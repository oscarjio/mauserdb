# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-11 (session #55)*
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
Session #55: Kassationsanalys + Alerts/notifieringar — pågår.

## ÖPPEN BACKLOG (prioritetsordning)

- [PÅGÅR] **Kassationsanalys** — drilldown per stopporsak + kassationstyp (session #55)
- [PÅGÅR] **Alerts/notifieringar** — realtidsvarning vid låg OEE/lång stopptid (session #55)
- [ ] **Dashboard-widget layout** — VD väljer widgets på startsidan
- [ ] **Effektivitet per produkttyp** — FoodGrade vs NonUN vs Tvättade
- [ ] **Stopporsak-snabbregistrering** — mobilvänlig knappmatris
- [ ] **Skiftöverlämningsmall** — auto-sammanfattning vid skiftbyte
- [ ] **Underhållslogg** — operatör loggar underhåll med kategori + tid
- [ ] **Cykeltids-heatmap per timme** — mönster morgon vs kväll
- [ ] **OEE-benchmark jämförelse** — aktuell vs branschsnitt

## BESLUTSDAGBOK (senaste 3)

### 2026-03-11 — Session #55 (pågår)
Worker 1 (Kassationsanalys): Drilldown per stopporsak + kassationstyp, stackad stapelgraf, trendjämförelse, periodselektor. Backend: KassationsanalysController.
Worker 2 (Alerts/notifieringar): Realtidsvarningar vid låg OEE/lång stopptid, kvittering, tröskelvärden, badge i header. Backend: AlertsController + DB-migrering.

### 2026-03-11 — Session #54 (klar)
Worker 1 (Operatörs-dashboard "Min dag"): Personlig vy — dagens IBC, cykeltid-trend (Chart.js), kvalitet, bonus, progressbars mot mål, motivationstext. Backend: MinDagController. Commit d264777.
Worker 2 (Veckotrend sparklines): Canvas 2D sparklines, 7-dagars trend (4 KPI:er), quadratic bezier + gradient fill, animerad 500ms, integrerad överst på statistiksidan. Backend: VeckotrendController. Commit 2384b65.

### 2026-03-11 — Session #53 (klar)
Worker 1 (Operatörsnärvaro-tracker): Kalendervy månadsrutnät operatör×dag, färgkodad, tooltip, sammanfattningskort. Backend: NarvaroController. Commit 8166fd2.
Worker 2 (Produktionspuls-ticker): Realtidsticker med senaste IBC:er, färgkodad, pausar vid hover, statistikrad, widget på startsidan. Backend: ProduktionspulsController. Commit da0cfd2.

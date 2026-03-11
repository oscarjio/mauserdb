# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-11 (session #52)*
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

## ÖPPEN BACKLOG (prioritetsordning)


- [ ] **Operatörsnärvaro-tracker** — kalendervy från rebotling_ibc-data
- [ ] **Dashboard-widget layout** — VD väljer widgets på startsidan
- [ ] **Alerts/notifieringar** — varning vid låg OEE eller lång stopptid
- [ ] **Produktionspuls-ticker** — realtids scrollande ticker med senaste IBC
- [ ] **Kassationsanalys** — drilldown per stopporsak + kassationstyp
- [ ] **Effektivitet per produkttyp** — FoodGrade vs NonUN vs Tvättade

OBS: Skiftplaneringsvy och Underhållslogg redan implementerade (shift-plan 717 rader, maintenance-log 389 rader) — borttagna från backlog.

## BESLUTSDAGBOK (senaste 3)

### 2026-03-11 — Session #52 (klar)
Worker 1 (Maskinupptid-heatmap): CSS-grid 7×24 med drift/stopp/idle färgkodning (grön/röd/grå), tooltip, sammanfattningskort (drifttid%, körtimmar, längsta stopp, bästa dag), periodselektor 7/14/30d, auto-refresh 60s. Backend: machine-uptime-heatmap. 672 rader ny kod.
Worker 2 (Topp-5 leaderboard): Live-ranking topp-5 operatörer, guld/silver/brons gradient-borders, pulsanimation (@keyframes), trendpilar (upp/ner/samma/ny), progressbar relativt ettan, periodselektor 7/30/90d, auto-refresh 30s. Backend: top-operators-leaderboard. 672 rader ny kod.

### 2026-03-11 — Session #51 (klar)
Worker 1 (Bonus "What-if"-simulator): Range-inputs för viktningar/mål/tier, debounce 400ms, jämförelsetabell nuv. vs sim. bonus, spara-knapp. Backend: bonus-simulator + save-simulator-params i BonusAdminController. Commit 3dc15b1.
Worker 2 (Skiftjämförelse-vy): Dag vs natt KPI-paneler (orange/lila), diff-kolumn, grouped bar chart, linjediagram med KPI-toggle, periodselektor 7/14/30/90d. Backend: shift-day-night. Commit 75d6508.

### 2026-03-11 — Session #50 (klar)
Worker 1 (Produktionsmål-tracker): Doughnut-gauge dagsmål/veckamål, streak-badge, countdown, admin inline-edit, auto-refresh 60s. Backend: production-goal-progress + set-production-goal. DB: rebotling_production_goals.
Worker 2 (Månadsrapport): Service-metoder getMonthlyReport/getMonthCompare + interfaces.

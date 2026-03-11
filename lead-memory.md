# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-11 (session #61)*
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

## ÖPPEN BACKLOG (prioritetsordning)

- [x] **Målhistorik-analys** — rebotling_goal_history → visualisering
- [x] **Skiftjämförelse-dashboard** — dag/kväll/natt-prestanda
- [ ] **Underhållsprognos** — prediktera underhåll från historik
- [ ] **Kvalitetstrend per operatör** — kvalitet% trend, utbildningsbehov
- [ ] **Stopporsak-trendanalys** — veckovis utveckling av stopporsaker
- [ ] **Energi/effektivitetsvy** — IBC per drifttimme, slitage-trend
- [ ] **Operatörs-onboarding tracker** — lärlingskurva nya operatörer

## BESLUTSDAGBOK (senaste 3)

### 2026-03-11 — Session #61 (klar)
Merge-konflikter lösta. Diagnostik-varningar fixade (unused imports, deprecated HttpClientModule, unused vars i PHP).
Worker 1 (Målhistorik-analys): Steg-graf med mål vs faktisk IBC/h, ändringslogg-tabell, impact-kort (före/efter), sammanfattning. Backend: MalhistorikController (2 endpoints).
Worker 2 (Skiftjämförelse-dashboard): 3 skiftkort (dag/kväll/natt) med krona på bästa, grupperat stapeldiagram, veckovis trendgraf, topp-5 operatörer per skift. Backend: SkiftjamforelseController (3 endpoints).
Backlog utökad med: Energi/effektivitetsvy + Operatörs-onboarding tracker.

### 2026-03-11 — Session #60 (klar)
Worker 1 (Daglig sammanfattning): Komplett VD-dashboard, 2 endpoints, auto-refresh 60s. Backend: DagligSammanfattningController.
Worker 2 (Produktionskalender): Månadsvy CSS Grid, färgkodade dagar, klickbar detalj-panel. Backend: ProduktionskalenderController.

### 2026-03-11 — Session #59 (klar)
Worker 1 (Operatörsranking historik): Leaderboard-trender, placeringsändringar, head-to-head. Backend: RankingHistorikController.
Worker 2 (Operatörs-feedback analys): Stämningsöversikt, trender, operatörsöversikt. Backend: FeedbackAnalysController.

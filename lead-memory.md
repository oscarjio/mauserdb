# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-12 (session #66)*
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

## ÖPPEN BACKLOG (prioritetsordning)

- [x] **Andon-board / fabriksskärm** — stor TV-skärm med realtidsdata
- [x] **Veckorapport-generator** — automatisk vecko-KPI till PDF
- [ ] **Operatörsportal** — personlig dashboard per operatör
- [ ] **Operatörs-onboarding tracker** — lärlingskurva nya operatörer
- [ ] **Skiftplaneringsöversikt** — visuell kalender med skiftbeläggning
- [ ] **Skiftöverlämningslogg** — digital överlämning mellan skift
- [ ] **Alarm-historik dashboard** — lista triggade alerts
- [ ] **Snabbkommandon/favoritvy** — VD:s bokmärken

## BESLUTSDAGBOK (senaste 3)

### 2026-03-12 — Session #66 (klar)
Worker 1 (Andon-board/fabriksskärm): TV-optimerad helskärmsvy med dagens produktion vs mål, aktuell takt, maskinens status (KÖR/STOPP), senaste stopp, kvalitet, skiftinfo, klocka. Backend: AndonController (1 samlat endpoint). Auto-refresh 30s, stor text, pulsande statusindikator.
Worker 2 (Veckorapport-generator): Utskriftsvänlig veckosammanfattning med KPI:er (produktion, effektivitet, stopp, kvalitet). Veckoväljare, @media print CSS, Ctrl+P till PDF. Backend: VeckorapportController (1 samlat endpoint). Trendpilar vs föregående vecka.
Backlog utökad med: Alarm-historik dashboard, Snabbkommandon/favoritvy.

### 2026-03-11 — Session #65 (klar)
Worker 1 (Realtids-produktionstakt): Live IBC/h med trendpil, måltal-indikator (grön/gul/röd), alert vid låg takt, 24h linjegraf, timtabell. Backend: ProduktionsTaktController. Auto-poll 30s.
Worker 2 (Kassationsanalys): 4 KPI-kort, staplat stapeldiagram per orsak, doughnut, trendgraf, detaljerad tabell med filter. Backend: KassationsanalysController.

### 2026-03-11 — Session #64 (klar)
Worker 1 (Produktionsmål vs utfall): 3 stora statuskort (dag/vecka/månad) med progress bars och färgkodning (grön/gul/röd). Kumulativ Chart.js linjegraf (mål vs faktiskt). Daglig tabell. Backend: ProduktionsmalController (3 endpoints). VD-prioriterad feature.
Worker 2 (Maskinutnyttjandegrad): 3 KPI-kort med cirkulär progress, staplad bar chart (drifttid/stopptid/okänd), doughnut för tidsförlustfördelning. Backend: UtnyttjandegradController (3 endpoints).

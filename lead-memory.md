# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-11 (session #64)*
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

## ÖPPEN BACKLOG (prioritetsordning)

- [x] **Produktionsmål vs utfall dashboard** — dagsmål/veckomål/månadsmål vs faktisk
- [x] **Maskinutnyttjandegrad** — andel tillgänglig tid i produktion
- [ ] **Operatörs-onboarding tracker** — lärlingskurva nya operatörer
- [ ] **Skiftplaneringsöversikt** — visuell kalender med skiftbeläggning
- [ ] **Kassationsanalys** — kasserade IBC orsaker, trender, kostnader
- [ ] **Operatörsportal** — personlig dashboard per operatör
- [ ] **Realtids-produktionstakt** — live IBC/h vs måltal med alert
- [ ] **Veckorapport-generator** — automatisk vecko-KPI-sammanställning

## BESLUTSDAGBOK (senaste 3)

### 2026-03-11 — Session #64 (klar)
Worker 1 (Produktionsmål vs utfall): 3 stora statuskort (dag/vecka/månad) med progress bars och färgkodning (grön/gul/röd). Kumulativ Chart.js linjegraf (mål vs faktiskt). Daglig tabell. Backend: ProduktionsmalController (3 endpoints). VD-prioriterad feature.
Worker 2 (Maskinutnyttjandegrad): 3 KPI-kort med cirkulär progress, staplad bar chart (drifttid/stopptid/okänd), doughnut för tidsförlustfördelning. Backend: UtnyttjandegradController (3 endpoints).
Backlog utökad med: Realtids-produktionstakt, Veckorapport-generator.

### 2026-03-11 — Session #63 (klar)
Worker 1 (Stopporsak-trendanalys): 4 KPI-kort, staplad bar chart (topp-7 orsaker per vecka), trendtabell med sparkline/trendpil/%-förändring, expanderbar detaljvy per orsak med linjegraf+tidslinje. Periodväljare 4/8/12/26v. Backend: StopporsakTrendController (3 endpoints).
Worker 2 (Energi/effektivitetsvy): 4 KPI-kort (IBC/h idag, snitt 7d/30d, trendindikator), Chart.js linjegraf (daglig + 7d glidande medel), 3 skiftkort. Backend: EffektivitetController (3 endpoints).

### 2026-03-11 — Session #62 (klar)
Worker 1 (Underhållsprognos): 4 översiktskort, schematabell, Chart.js horisontellt stapeldiagram. Backend: UnderhallsprognosController (3 endpoints).
Worker 2 (Kvalitetstrend per operatör): 4 KPI-kort, utbildningslarm, Chart.js trendlinjer, operatörstabell med sparkline. Backend: KvalitetstrendController (3 endpoints).

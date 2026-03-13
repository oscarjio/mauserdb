# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-13 (session #87)*
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
Session #69: VD:s morgonrapport + OEE-waterfall/brygga — klara.
Session #70: Drifttids-timeline + Skiftvis produktionsjämförelse — klara.
Session #71: Kassationsorsak-drill-down + Produktionspuls realtids-ticker — klara.
Session #72: Första-timme-analys + Operatörs-personligt dashboard — klara.
Session #73: Produktionsprognos + Stopporsak per operatör — klara.
Session #74: Operatörs-onboarding tracker + Skiftöverlämningslogg — klara.
Session #75: Operatörsjämförelse sida-vid-sida + Produktionseffektivitet per timme — klara.
Session #76: Snabbkommandon/favoritvy + Kvalitetsanalys trendbrott-detektion — klara.
Session #77: Statistik-dashboard sammanfattning + Maskinunderhåll serviceintervall — klara.
Session #78: Batch-spårning + Kassationsorsak-statistik — klara.
Session #79: Skiftplanering + Produktions-SLA/måluppfyllnad — klara.
Session #80: Stopptidsanalys per maskin + Produktionskostnad per IBC — klara.
Session #81: Rebotling maskin-OEE per station + Operatörsbonus-kalkylator — klara.
Session #82: Leveransplanering + Kvalitetscertifikat per batch — klara.
Session #83: Historisk produktionsöversikt + Automatiska avvikelseLarm — klara.
Session #84: Rebotling sammanfattnings-dashboard + Produktionsflödesvy (Sankey) — klara.
Session #85: Kassationsorsak per station + PDF-export alla rapporter — klara.
Session #86: Rebotling OEE-jämförelse per vecka + Maskin-drifttid heatmap — klara.
Session #87: Rebotling skiftrapport-sammanställning + Produktionsmål-dashboard — pågår.

## ÖPPEN BACKLOG (prioritetsordning)

- [PÅGÅR] **Rebotling skiftrapport-sammanställning** — daglig rapport per skift (#87)
- [PÅGÅR] **Produktionsmål-dashboard** — sätt mål, visa progress + prognos (#87)
- [ ] **Operatörs-tidrapport** — automatisk tidrapport baserat på skiftschema + aktivitet
- [ ] **Realtids-notifikationer** — push-notiser vid kritiska händelser
- [ ] **Dashboards favoritlayout** — VD:s anpassningsbara startsida
- [ ] **Operatörs-schemaöversikt** — veckovis schemavy med bemanningsgrad
- [ ] **Maskinhistorik per station** — detaljerad vy per maskin: drifttid, stopp, OEE-trend
- [ ] **Kassationskvot-alarm** — varning vid hög kassationsgrad

## BESLUTSDAGBOK (senaste 3)

### 2026-03-13 — Session #87 (pågår)
Worker 1 (Skiftrapport-sammanställning): Daglig rapport per skift (dag/kväll/natt) — produktion, kassation, OEE, stopp per skift. Veckosammanställning + skiftjämförelse. Chart.js stapeldiagram + linjediagram. PDF-export. Backend: SkiftrapportController. Använder rebotling_ibc + rebotling_onoff.
Worker 2 (Produktionsmål-dashboard): VD sätter vecko/månadsmål. Progress med doughnut-diagram. Prognos: "i nuvarande takt når ni målet [datum]" eller "behöver öka X%". Daglig produktion stapeldiagram + mål-linje. Historik-tabell. NY tabell: rebotling_produktionsmal. Backend: ProduktionsmalController.

### 2026-03-13 — Session #86 (klar)
Worker 1 (OEE-jämförelse per vecka): Veckovis OEE-jämförelse med trendpilar. KPI-kort (aktuell vs förra veckan), linjediagram 12 veckor, tabell med OEE/tillgänglighet/prestanda/kvalitet per vecka. Periodselektor 8-52 veckor. Målindikator. Använder befintlig rebotling_ibc. Backend: OeeJamforelseController.
Worker 2 (Maskin-drifttid heatmap): Visuell heatmap — timmar × dagar, färgkodad (grön/gul/röd/grå). KPI-kort drifttid. Maskinfilter + periodselektor. Tooltip + dagsammanfattning vid klick. Ren HTML/CSS-grid. Använder befintlig rebotling_ibc. Backend: MaskinDrifttidController.

### 2026-03-12 — Session #85 (klar)
Worker 1 (Kassationsorsak per station): Drill-down per station — vilka stationer kasserar mest och varför. Stapeldiagram per station, top-5-orsaker, trendgraf per dag, detaljerad tabell. Periodselektor + stationsfilter. Använder befintlig rebotling_ibc. Backend: KassationsorsakController.
Worker 2 (PDF-export alla rapporter): Generell PDF-export med html2canvas + jsPDF. Återanvändbar service + knapp-komponent. Läggs till på sammanfattning, historisk produktion, avvikelselarm, produktionsflöde.


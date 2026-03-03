# Lead Agent Memory — MauserDB

*Detta är ledaragentens persistenta minne. Uppdateras varje session.*
*Senast uppdaterad: 2026-03-03*

---

## Projektöversikt — Affärskontext

**Företaget** återvinner och tvättar IBC-tankar (Intermediate Bulk Container, 1000-liters plasttankar i metallbur).
Tankarna tas in, inspekteras, tvättas/rebotlas och skickas tillbaka ut i cirkulation.

**Systemets syfte — två huvudmål:**
1. **VD-överblick**: VD har beställt systemet för att enkelt och i realtid kunna följa produktionen. Hen ska kunna se om linjen körs bra, om målen nås, vad som orsakar stopp — utan att behöva gå ut i produktionen.
2. **Rättvis individuell bonus**: Operatörerna arbetar i lag (2-3 per skift). Systemet mäter varje operatörs prestation objektivt (IBC/h, kvalitet%, effektivitet) och beräknar en rättvis bonus. Målet är att motivera operatörerna att hålla hög fart OCH hög kvalitet — inte bara kvantitet.

**Användarroller:**
- **VD / Chef**: Vill se KPI:er på hög nivå — nådde vi dagsmålet? Hur ser veckan ut? Vilken operatör presterar bäst? Executive dashboard, statistik, bonusöversikt.
- **Operatör**: Vill se sitt eget bonusläge live — "hur mycket bonus är jag på väg mot idag/denna vecka?" Motiverande, enkelt, mobilanpassat.
- **Admin**: Konfigurerar mål, bonusregler, skiftlängder. Kan se auditlogg.

**Produktionslinjer:**
- **Rebotling** (aktiv): Byter bottenventil + tvätt på IBC-tankar. Data från PLC i realtid.
- **Tvättlinje** (byggs): Högtryckstvätt av tankar.
- **Såglinje** (byggs): Sågar sönder trasiga burarna för återvinning.
- **Klassificeringslinje** (byggs): Sorterar inkommande tankar efter skick.

**Data-flöde**: PLC → `plcbackend/` → MySQL → PHP API → Angular frontend

**Design-filosofi för features:**
- VD ska kunna öppna dashboarden och på 10 sekunder förstå om produktionen går bra eller dåligt
- Operatören ska känna sig motiverad och rättvist behandlad — transparent bonusberäkning
- Allt ska vara enkelt, snabbt och fungera på både dator och surfplatta i produktionsmiljö

## Arkitektur
- Frontend: Angular 20+ standalone, `noreko-frontend/src/app/`
- Backend: PHP/PDO, `noreko-backend/classes/`, routing via `api.php?action=X&run=Y`
- DB: MySQL (EJ på denna server — ändringar via SQL-filer i `noreko-backend/migrations/`)
- PLC: `plcbackend/` — rör ALDRIG

## ABSOLUTA REGLER (bryt aldrig dessa)
1. Rör ALDRIG livesidorna: `rebotling-live`, `tvattlinje-live`, `saglinje-live`, `klassificeringslinje-live`
2. DB-ändringar → SQL-migreringsfil i `noreko-backend/migrations/YYYY-MM-DD_namn.sql` + `git add -f`
3. All UI-text på svenska
4. Commit + push när en feature är klar (inte halvfärdig kod)
5. Bygg alltid: `cd noreko-frontend && npx ng build` och fixa fel innan commit

## DRIFTSLÄGE — KONTINUERLIGT ARBETE
- **Agenterna stannar ALDRIG** — när en agent är klar startas en ny direkt på nästa backlog-item
- **Minst 2 worker-agenter ska alltid vara igång** parallellt
- **Ledaragenten** (huvudkonversationen) övervakar, uppdaterar backlog och startar nya agenter så fort gamla är klara
- När token-gränsen för huvudkonversationen närmar sig: commit lead-memory.md + push, cron-jobbet tar över

### Om backlogen tar slut — gör detta i ordning:
1. **Sök på internet** efter moderna features för produktionssystem, MES-system (Manufacturing Execution System), OEE-dashboards, bonussystem, operator performance tracking. Söktermer: "production dashboard features", "OEE software features", "operator bonus system UX", "manufacturing KPI dashboard best practices".
2. **Granska befintlig kod** — läs alla komponenter, hitta grafer/tabeller som kan bli mer detaljerade, data som kan visualiseras bättre.
3. **Uppfinn nya funktioner** — led agenten ska självständigt komma på vad som saknas och lägga till det. Ingen behöver godkänna — implementera direkt och lägg till i menyn. Kunden utvärderar efteråt.
4. **Starta bug hunting-agent** parallellt.

### Lead-agentens kreativa ansvar
- Ledaragenten SKA driva projektet framåt på egen hand — inte bara förvalta en lista
- Läs koden, identifiera vad som fattas, hitta inspiration online, föreslå och implementera via worker-agenter
- Lägg alltid till nya funktioner i navigationsmenyn (app.routes.ts + nav-komponent) så de är åtkomliga
- Kunden ser allt efteråt och bestämmer vad som ska vara kvar — jobba fritt och kreativt
- Prioritera: mer detaljerad data i befintliga grafer, nya vyer som ger VD och operatörer mer insikt

### Bug Hunting — starta regelbundet
- **Var 3:e session** (ungefär) ska en dedikerad bug hunting-agent startas parallellt med övriga workers
- Bug hunting-agentens uppdrag:
  - Läs igenom alla TypeScript-komponenter och leta efter: minnesläckor (saknat ngOnDestroy/clearInterval), race conditions i polling, felhantering som saknas, edge cases (null/undefined, tomma arrayer)
  - Kolla PHP-controllers: saknade auth-checks, SQL utan prepared statements, felaktiga HTTP-statuskoder
  - Kolla att alla nya features faktiskt bygger (`npx ng build`) utan fel
  - Fixa buggar direkt, commita med prefix "Bugfix: ..."
  - Rapportera alla fynd i lead-memory.md under "BUGGAR/TEKNISK SKULD"

## LEDARFILOSOFI — Hur ledaragenten tänker och agerar

Tänk som en **ambitiös teamleader** som vill imponera på kunden och visa vad som är möjligt:

- **Ge kunden det de behöver** — lös det uppenbara, det de bad om
- **Ge kunden det de inte visste de behövde** — analysera deras verksamhet djupt, identifiera smärtpunkter de inte uttryckt, bygg lösningar de blir positivt överraskade av
- **Var inte rädd att ta initiativ** — implementera djärva idéer, lägg till i menyn, låt kunden se det köra. Bättre att visa för mycket än för lite.
- **Analysera kundens kontext aktivt**: Ett IBC-tvätteri med operatörsbonus = folk som motiveras av tävling, rättvisa, synlighet. VD som inte hinner vara på golvet = behöver data som berättar en historia på 10 sekunder. Chefer som tar beslut = behöver trender, inte bara siffror.
- **Tänk på hela användarresan**: Vad gör operatören kl 07:00 när skiftet börjar? Vad tittar VD på måndag morgon? Vad behöver en skiftledare precis innan hen lämnar? Bygg för dessa scenarios.
- **Inspiration utifrån**: Sök aktivt efter hur världsledande produktionssystem (Ignition, Wonderware, SAP PM, Tulip) löser liknande problem. Anpassa idéerna till denna kodbas.
- **Kvalitet över kvantitet på features, men kvantitet över noll** — en halvbra feature som finns är bättre än en perfekt feature som aldrig byggs.

## KOMMUNIKATION MED ÄGAREN
- **Dokumentera ALLT ägaren säger** i denna fil direkt — så att varken ledaragent eller workers behöver fråga om samma sak igen
- Ägaren ska aldrig behöva upprepa sig
- Om ägaren ger en ny instruktion: uppdatera lead-memory.md + commit inom samma svar

## ÄGARENS INSTRUKTIONER (kronologisk logg)
- *2026-03-03*: Fokus på rebotling. Övriga linjer ej igång.
- *2026-03-03*: Systemet är för VD som vill ha övergripande koll + ge rättvis individuell bonus åt operatörerna.
- *2026-03-03*: Databas ligger INTE på denna server — allt deployas manuellt. DB-ändringar via SQL-migreringsfiler.
- *2026-03-03*: Rör aldrig livesidorna (i produktion).
- *2026-03-03*: Agenterna ska aldrig stanna — alltid minst 2 igång, ledaragenten håller dom i arbete hela tiden.
- *2026-03-03*: Dokumentera allt som sägs i minnet — ägaren ska aldrig behöva upprepa sig.
- *2026-03-03*: Om backlogen tar slut → analysera koden och hitta förbättringar. Starta bug hunting-agent regelbundet (var 3:e session) som letar buggar och fixar dem.
- *2026-03-03*: Ledaragenten ska driva projektet helt självständigt — sök internet efter nya features, granska koden, uppfinn nya funktioner. Lägg till i menyn direkt utan att vänta på godkännande. Kunden utvärderar med VD efteråt. Ge graferna mer detaljerad data. Håll alltid minst 2 worker-agenter i arbete.
- *2026-03-03*: Tänk som en ambitiös teamleader — ge kunden det de bad om OCH det de inte visste de behövde. Analysera verksamheten (IBC-tvätteri, operatörsbonus, VD-översikt), identifiera smärtpunkter, ta initiativ. Var inte rädd att bygga djärva features och lägga i menyn — kunden och VD utvärderar efteråt.
- *2026-03-03*: **DYGNET RUNT** — Ägaren sover. Ni som inte behöver sova SKA fortsätta jobba aktivt tills ägaren explicit säger stopp. Det finns alltid något att fixa. Håll alltid minst 2 worker-agenter i arbete — om en blir klar, starta en ny direkt. Backlogen tar aldrig slut: granska koden, hitta buggar, sök internet, uppfinn nya features, förbättra befintliga. Ingen väntetid accepteras.
- *2026-03-03*: **GRAFKVALITET** — Graferna behöver mer detaljerade datapunkter. "En punkt var 10:e [enhet] när man är per dygn är lite dåligt." Implementera adaptiv granularitet med toggle-knappar (Timme/Skift/Dag) i graferna. Se TEKNISK PLAN nedan.

## BUGGAR / TEKNISK SKULD
*(Uppdateras av bug hunting-agenter och workers som hittar problem)*

### Åtgärdat `a9716cd` — 2026-03-03 (Bug Hunt #2 + Operators-agent)

**Angular — takeUntil saknas (subscription-läckor):**
- `audit-log.ts`: `loadLogs()` + `exportCSV()` saknade `takeUntil(destroy$)`
- `stoppage-log.ts`: `loadReasons()`, `loadStoppages()`, `loadStats()`, `addStoppage()`, `deleteStoppage()` saknade `takeUntil(destroy$)`

**Angular — setTimeout utan clearTimeout:**
- `executive-dashboard.ts`: `setTimeout(() => buildBarChart(), 100)` ej lagrat → `clearTimeout` anropades aldrig i ngOnDestroy. Fixat med `barChartTimer`-property.

### Åtgärdat `92cbcb1` — 2026-03-03 (Bug Hunting Agent)

**Angular — Minnesläckor (takeUntil saknas):**
- `bonus-dashboard.ts`: `loadWeeklyGoal()` saknade `takeUntil(destroy$)` → subscription läckte
- `bonus-dashboard.ts`: `getDailySummary()` saknade `takeUntil(destroy$)` → läckte vid navigering
- `bonus-dashboard.ts`: `loadPrevPeriodRanking()` saknade `takeUntil(destroy$)` → läckte vid navigering
- `my-bonus.ts`: Tre HTTP-anrop i `loadStats()` saknade `timeout()` och `takeUntil(destroy$)` → kunde hänga oändligt

**Angular — Race conditions:**
- `bonus-dashboard.ts`: `loadData()` i `setInterval`-callback körde utan `destroy$.closed`-check → kunde trigga HTTP efter destroy
- `rebotling-admin.ts`: `loadSystemStatus()` saknade isFetching-guard → kunde stapla anrop under 30s polling

**Angular — Oanvända imports:**
- `my-bonus.ts`: `KPIDetailsResponse`, `OperatorStatsResponse`, `OperatorHistoryResponse` importerades men aldrig användes

**Angular — Logikbugg:**
- `production-analysis.ts`: `catchError` i `getRastStatus`-anropet satte `stopAnalysisLoading=false` för tidigt (den tredje requesten var fortfarande pending)

**PHP — Säkerhet/korrekthet:**
- `BonusController.php`: `sendError()` saknade `http_response_code()` — returnerade alltid HTTP 200 vid fel (klienter kunde inte detektera fel korrekt)
- `BonusAdminController.php`: `FILTER_SANITIZE_STRING` användes i `approveBonuses()` — deprecated sedan PHP 8.1, borttagen i PHP 8.2. Ersatt med `strip_tags()`.

### Kvarstående observerat (ej buggar, men noteringar):
- `rebotling-statistik.ts` hade **pre-existing uncommitted changes** när bug-hunting kördes — ett heatmap-KPI-val feature i progress. Ej påverkat av bug-hunting-committen.
- GET-endpoints i RebotlingController saknar auth-check — detta är by design (produktionsgolvet ska kunna se live-data utan inloggning)

---

## BACKLOG (prioritetsordning)

### 🔴 Hög prioritet
- [x] **My-Bonus realtidsvy**: Levererat `82173ec` — motiverande statusbricka (Rekordnivå!/Uppåt mot toppen!/etc.), IBC/h-trendgraf senaste 7 skiften med glidande snitt, skiftprognos-banner (förväntad bonus + IBC/vecka), inkluderat i PDF-export
- [x] **Bonus-Dashboard rankingkort**: Levererat `82173ec` — trendpilar ↑↓→ vs föregående period, veckobonusmål-progressbar för hela teamet, mini-progressbar per operatör i rankingtabellen
- [x] **Skiftmålsprediktor**: Levererat `c7faa1b` — live-prognos baserat på takt sedan 06:00 → 22:00
- [x] **Veckojämförelse-graf**: Levererat `c7faa1b` — bar chart denna/förra veckan, summakort med diff

### 🟡 Medium prioritet
- [x] **Rebotling-Skiftrapport sammanfattningskort**: Levererat — 6 KPI-kort (Total IBC, Kvalitet%, OEE, Drifttid, Rasttid, Delta vs. föregående skift), sorterbar tabell, skiftväljare, textsök, förbättrad PDF+Excel-export
- [ ] **Operatörsprestanda-trend**: Graf per operatör — förbättring över tid — ÖPPEN (nästa omgång)
- [x] **OEE deep-dive**: Levererat `c7faa1b` — Tillgänglighet/Prestanda/Kvalitet-bars + 30-dagars trendgraf
- [ ] **Stopporsaksanalys**: Visualisera stopp och raster i production-analysis — ÖPPEN
- [x] **Admin: Mål per veckodag**: Levererat — mån–sön individuella mål, `rebotling_weekday_goals` tabell. Skifttider konfigurerbart. Systemstatus-sektion med PLC-ping, löpnummer, DB-status.
- [ ] **Förbättrad heatmap**: Tooltip med IBC/h + kvalitet%, val av KPI — ÖPPEN

### 🟢 Lägre prioritet
- [x] **Systemstatus i admin**: Levererat — PLC-ping ålder, löpnummer, DB-status
- [x] **Bästa skift-topplista**: Levererat `c7faa1b` — guld/silver/brons i production-analysis
- [x] **Skift-sammanfattning vid export**: Levererat — PDF + Excel inkluderar sammanfattningskort
- [x] **Executive dashboard förbättringar**: Alert-sektion levererad `cc4ba9f` — danger/warning-banners för OEE<70%/<80% och produktion<60%/<80%, slide-in-animation, döljs om allt är OK.

---

## NÄSTA BATCH (session 2 — startas av lead-agent via cron)

### 🔴 Hög prioritet
- [x] **Operatörsprestanda-trend**: Levererat `ef505e6` — stapeldiagram per ISO-vecka (8 veckor), grön/orange vs snitt, streckad referenslinje, lagsjämförelse (IBC/h, Kvalitet%, Bonus) i my-bonus. Ny endpoint `bonus&run=weekly_history`.
- [x] **Stopporsaksanalys**: Levererat `ef505e6` — ny flik i production-analysis: horisontell tidslinje (grön=kör, gul=rast), summering-pills, 14-dagars bar chart, KPI-kort. Proxy-data med tydlig kommentar om PLC-integration.
- [x] **Executive dashboard — VD-vy**: Levererat `fb05cce` — SVG-cirkulär progress, prognos, OEE-trendpil, 7-dagars bar chart (grön/röd vs mål), veckokort, operatörstabell. Enda HTTP-anrop via ny endpoint `exec-dashboard`.

### 🟡 Medium prioritet
- [x] **Förbättrad heatmap i statistik**: Levererat `3a89898` — interaktiv tooltip (datum/timme/IBC/h/kvalitet%), KPI-toggle (IBC/h blå, Kvalitet% grön, OEE% violett), legend med gradient+siffror, noll-celler mörkt grå
- [x] **Mobilanpassning**: Levererat `3a89898` — my-bonus responsive (768px/480px), lagerjämförelse 1-kolumn på mobil, 44px touch-targets, overflow-x:hidden
- [x] **Bonushistorik-graf i my-bonus**: Levererat `ef505e6` — stapeldiagram 8 veckor med lagsnitt (weekly_history endpoint)
- [x] **Skiftjämförelse**: Levererat `8404b29` — två datumväljare, 6 KPI-kort sida-vid-sida med diff-badge (grön/röd/grå + pil), operatörstabeller per datum. Backend: shift-compare endpoint med aggregerad SQL.

### 🟢 Lägre prioritet
- [x] **Notifikation/varning i admin**: Levererat `8404b29` — röd banner (>15 min), gul (5–15 min), plc-blink animation. Använder befintlig 30s polling utan extra HTTP-anrop.
- [ ] **Tvättlinje/Såglinje förberedelsearbete**: Sätt upp grundstruktur för när de linjerna startar
- [x] **Audit-log förbättring**: Levererat `e72763c` — fritext+datumfilter med debounce, åtgärds-dropdown (dynamisk från DB), färgkodade badges (grön/blå/röd/orange/grå), förbättrad paginering med ellipsis, CSV-export av hela filtrerade vyn. Stoppage-log: snitt-stopplängd KPI, veckojämförelse vs förra veckan, 14-dagars bar chart, weekly_summary endpoint.

---

## IDÉBANK — Autonomt genererade features (implementera fritt, kunden utvärderar)

### Grafer — mer detaljerad data
- [x] **Cykeltids-histogram**: Levererat `e4ca058` — histogrambuckets (0-2/2-3/3-4/4-5/5-7/7+min), KPI-brickor (Snitt/P50/P90/P95), grön stapelgraf, datumväljare. Backend: cycle-histogram endpoint med fallback till rebotling_ibc.
- [x] **Kontrollkort (SPC)**: Levererat `e4ca058` — X̄±2σ kontrollgränser, 4 dataset (IBC/h blå, UCL röd, LCL orange, medelvärde grön), dagar-väljare (3/7/14/30). Backend: spc endpoint med sample stddev.
- [ ] **Kvalitetstrendkort**: Daglig kvalitet% som linjegraf med 7-dagars rullande medelvärde. Identifiera om kvaliteten försämras gradvis.
- [ ] **Waterfalldiagram OEE**: Visar hur förluster bryts ned: 100% → -X% tillgänglighet → -Y% prestanda → -Z% kvalitet = faktisk OEE.

### Nya vyer/sidor
- [ ] **Skiftplaneringsvy** (`/admin/skiftplan`): Kalendervy där admin kan se vilka operatörer som är schemalagda per skift. Kan kopplas till bonusberäkning.
- [x] **Realtids-tävling** (`/rebotling/live-ranking`): Levererat `a3d5b49` — full-screen TV-vy, neongrön accent, guld/silver/brons ranking, IBC/h + kvalitet% + progress-bar mot dagsmål, polling var 30s, roterande motton. Backend UNION ALL aggregerar op1/op2/op3, fallback senaste 7 dagarna. Layout.ts döljer navbar automatiskt (URL innehåller "live").
- [ ] **Energieffektivitet-vy** (`/rebotling/energi`): Om energidata finns — IBC per kWh, energikostnad per IBC, trend.
- [ ] **Månadsrapport** (`/rapporter/manad`): Auto-genererad sammanfattning för månaden — total produktion, snitt OEE, bästa/sämsta dag, bonusutbetalningar. Exporterbar som PDF.
- [x] **Måluppfyllnad-kalender** (`/rebotling/kalender`): Levererat `cc4ba9f` — 12-månaders grid, 6 färgklasser (grå/röd/orange/gul/grön/superdag-glow), hover-tooltip med datum+IBC+mål+%, KPI-rad (total/snitt/bästa dag/% dagar på mål), årsväljare med pilar. Admin-only route + nav-länk.
- [ ] **Operatörscertifiering** (`/admin/certifiering`): Spåra vilka operatörer som är certifierade för vilka linjer/maskiner.

### Förbättringar av befintliga sidor
- [ ] **Executive dashboard**: Lägg till "Alert-sektion" — om OEE < 70% eller produktion < 60% av mål visas en aktiv varning med åtgärdsförslag.
- [ ] **Bonus-dashboard**: "What-if"-simulator — admin kan justera bonusparametrar och se i realtid hur det påverkar operatörernas utbetalningar.
- [ ] **My-bonus**: Push-notifikation (Web Push API) när operatören passerar en bonusnivå (Brons→Silver osv).
- [ ] **Rebotling-statistik**: Annotationer i grafer — markera ut driftstopp, helgdagar, nya operatörer direkt i tidslinjen.
- [ ] **Skiftrapport**: Automatisk e-post/export vid skiftslut — skicka skiftsammanfattning som PDF till chef.

### Data & Analytics
- [ ] **Prediktiv underhållsindikator**: Baserat på cykeltidsdata — om cykeltid ökar stadigt kan det indikera maskinslitage. Visa varning.
- [ ] **Benchmarking-vy**: Jämför prestanda mot "bästa historiska period" — "Den här veckan vs bästa veckan någonsin".
- [ ] **Korrelationsanalys**: Visar samband — t.ex. "Operatör A presterar X% bättre när partnern är Operatör B".

---

## AKTIVA AGENTER (session 2026-03-03 kväll)
Två agenter startades parallellt:
- **Live-ranking-agent** (a122f4aa871227407): live-ranking/, app.routes.ts, RebotlingController (ny endpoint)
- **Histogram+SPC-agent** (a28e9937bf01212d3): rebotling-statistik.ts, rebotling.service.ts, RebotlingController (ny endpoint)

---

## GENOMFÖRT (commit-historik)
- `ecc6b40` — Auth fix: APP_INITIALIZER väntar på fetchStatus() via firstValueFrom()
- `771e128` — auto-develop.sh och dev-log.md tillagda
- `d4db30b` — lead-agent.sh + lead-memory.md: orchestrator-system etablerat
- `c7faa1b` — **Statistik-agent**: Veckojämförelse, Skiftmålsprediktor, OEE deep-dive (30-dagars trend), Bästa skift-topplista. Nya endpoints: week-comparison, oee-trend, best-shifts.
- **Skiftrapport-agent**: Sammanfattningskort (6 KPIs), sorterbar tabell, skiftväljare, textsök, bonus-estimat i detaljvy, veckodagsmål mån–sön i admin, systemstatus (PLC-ping, löpnummer, DB). Nya tabeller: `rebotling_weekday_goals`, `rebotling_shift_times`. Migration: `2026-03-03_rebotling_settings_weekday_goals.sql`.
- `ef505e6` — **Operatörstrend-agent**: My-bonus veckoutvecklingsgraf (8v), lagsjämförelse. Production-analysis stoppanalys-flik med tidslinje + 14-dagars chart. Ny endpoint weekly_history i BonusController.
- `fb05cce` — **VD-dashboard-agent**: Executive dashboard ombyggd — SVG-cirkulär progress, prognos, OEE-trendpil vs igår, 7-dagars bar chart (grön/röd), veckokort, operatörstabell. Ny endpoint exec-dashboard (ett anrop för allt).
- `e72763c` — **Audit+stoppage-agent**: Audit-log: 4 filter (fritext/datum/åtgärd), färgkodade badges, bättre paginering, CSV-export. Stoppage-log: snitt-stopplängd, veckojämförelse, 14-dagars chart, weekly_summary endpoint.
- `a9716cd` — **Bug Hunt #2 + Operators-agent**: 11 buggar fixade. Operators: initialer-avatars, sortering, sök, status-badges, detaljvy med trendgraf 8v. Ny backend-endpoint trend.
- `a3d5b49` — **Live-ranking TV-skärm**: `/rebotling/live-ranking` full-screen, neongrön, guld/silver/brons, polling 30s, UNION ALL op1/op2/op3, dagsmål progress-bar.
- `e4ca058` — **Histogram+SPC**: Cykeltids-histogram (P50/P90/P95) + SPC-kontrollkort (±2σ) i rebotling-statistik. Nya endpoints: cycle-histogram, spc.
- `cc4ba9f` — **Kalender + Alerts**: Produktionskalender 12-månaders heatmap (6 färgklasser, hover-tooltip, årsväljare, KPI-rad). Executive dashboard alert-sektion (OEE/produktion trösklar). Ny endpoint: year-calendar.
- `8404b29` — **Skiftjämförelse-agent**: Sida-vid-sida KPI-jämförelse med diff-badges, operatörstabeller, shift-compare endpoint. Admin PLC-varningsbanner med blinkanimation vid >15min utan data.
- `3a89898` — **Heatmap+mobil-agent**: Interaktiv heatmap-tooltip, KPI-toggle (IBC/h/Kvalitet%/OEE%), dynamisk färgskala+legend, noll-celler grå. my-bonus mobilanpassad (responsive 768px/480px, touch-targets 44px).
- `92cbcb1` — **Bug hunt #1**: 8 buggar fixade — takeUntil-läckor i bonus-dashboard, timeout saknas i my-bonus HTTP-anrop, isFetching-guard i rebotling-admin, prematur loading-reset i production-analysis, BonusController sendError() HTTP 200 vid fel, FILTER_SANITIZE_STRING deprecated PHP 8.2.
- `82173ec` — **Bonus-agent**: My-bonus: statusbricka (rekordnivå/uppåt/etc.), IBC/h-trendgraf 7 skift med glidande snitt, skiftprognos-banner, PDF-export. Bonus-dashboard: trendpilar ↑↓→ vs föregående period, veckobonusmål-progressbar för team + per operatör. Bonus-admin: ny Prognos-flik (sök operatör → snittbonus/tier/IBC/h), veckobonusmål-konfiguration. Backend: operator_forecast endpoint, set_weekly_goal endpoint. Migration: `2026-03-03_bonus_weekly_goal.sql`.
- StatusController.php: session_start(['read_and_close']) för att undvika PHP-session-låsning

---

## TEKNISK PLAN: ADAPTIV GRAFGRANULARITET

**Bakgrund (research 2026-03-03):** rebotling_ibc.datum = TIMESTAMP med minutprecision. Per-timme-data möjlig via HOUR(datum). Heatmap-endpointen gör redan detta. Befintliga grafer (oee-trend, week-comparison, cycle-trend) aggregerar bara per dag.

**Lösning — granularity-parameter + toggle-knappar:**
- Backend: lägg till `?granularity=hour|shift|day` på oee-trend, week-comparison, cycle-trend
- Frontend: toggle-knappar ovanför varje graf, auto-val baserat på tidsspan
- Auto-selection: ≤2 dagar → timme | 3–14 dagar → skift | ≥15 dagar → dag

**Per-skift SQL (GROUP BY DATE(datum), skiftraknare från rebotling_ibc):**
- 3 skift per dag → 21 punkter för 7 dagar (vs nuvarande 7)
- 3 skift per dag → 90 punkter för 30 dagar (vs nuvarande 30)

**Per-timme SQL (GROUP BY DATE(datum), HOUR(datum) från rebotling_ibc):**
- 16 timmar per dag (06–22) → 16 punkter för 1 dag (vs nuvarande 1)
- Beräkning: MAX(ibc_ok) per timme+skift, sedan delta mot föregående rad (kumulativa fält!)

**Viktigt om kumulativa fält:** ibc_ok/runtime_plc är kumulativa per skiftraknare. För per-timme: ta MAX(ibc_ok) per (timme, skiftraknare) och beräkna delta mot föregående timme inom samma skiftraknare. Alternativt: COUNT(*) per timme som heatmap gör (antal PLC-registreringar ≈ antal IBC, men inte exakt).

**Enklaste korrekt approach för per-skift:**
```sql
SELECT DATE(datum) AS dag, skiftraknare,
       MAX(ibc_ok) AS shift_ibc, MAX(runtime_plc) AS shift_runtime, MAX(rasttime) AS shift_rast,
       MIN(datum) AS skift_start
FROM rebotling_ibc
WHERE datum >= ? AND skiftraknare IS NOT NULL
GROUP BY DATE(datum), skiftraknare
ORDER BY dag, skiftraknare
```
X-axeln: skift_start (timestamp) → Chart.js kan visa "Dag + skiftnummer"

## TEKNISKA OBSERVATIONER
- `rebotling_ibc` tabell har kumulativa fält per skift — aggregering med MAX() per skiftraknare
- BonusController endpoints: operator, ranking, team, kpis, history, summary
- RebotlingController GET-endpoints: admin-settings, status, rast, statistics, day-stats, oee, cycle-trend, report, heatmap, getLiveStats, week-comparison, oee-trend, best-shifts, **exec-dashboard**, **weekday-goals**, **shift-times**, **system-status**
- rebotling-statistik.ts är nu **ännu längre** (veckojämförelse, prediktor, OEE deep-dive tillagda) — läs noggrant
- RebotlingService har nya interfaces: WeekComparisonResponse, OEETrendResponse, BestShiftsResponse
- Angular routing finns i app.routes.ts — nya sidor måste registreras där
- Skiftmålsprediktor beräknar takt från 06:00 → projicerar till 22:00 (produktionsdag)

---

## BESLUTSDAGBOK
**2026-03-03 — Session 1**: Startade tre parallella worker-agenter. Ledaragent-system etablerat.
**2026-03-03 — Statistik-agent klar**: Veckojämförelse, skiftmålsprediktor, OEE deep-dive, bästa skift-topplista.
**2026-03-03 — Skiftrapport-agent klar**: Sammanfattningskort, sorterbar tabell, skiftväljare, textsök, bonus-estimat, veckodagsmål i admin, systemstatus.
**2026-03-03 — Bonus-agent klar**: My-bonus statusbricka + trendgraf + prognos. Bonus-dashboard trendpilar + veckobonusmål. Bonus-admin prognos-flik. Alla tre agenter klara. **10 av 12 backlog-items levererade session 1.**
Nästa session (cron ~3h): Starta ny omgång på återstående öppna items + ny batch features.

---

## NÄSTA SESSION — GÖR DETTA (DYGNET RUNT — JOBBA UTAN PAUS)
1. Kör `git log --oneline -15` för att se vad som committats sedan förra sessionen
2. Uppdatera backlog — markera klara items [x], notera commit-hash
3. Starta 2-3 nya worker-agenter DIREKT på nästa prioriterade öppna items
4. Återstående IDÉBANK-features att implementera:
   - **What-if bonussimulator**: Admin justerar bonusparametrar → ser live-effekt på utbetalningar
   - **Månadsrapport PDF**: Auto-genererad sammanfattning för månaden — total prod, snitt OEE, bästa/sämsta dag, bonusutbetalningar
   - **Skiftplaneringsvy** (`/admin/skiftplan`): Kalendervy, vilka operatörer är schemalagda per skift
   - **Benchmarking-vy**: Jämför denna vecka vs bästa veckan någonsin
   - **Korrelationsanalys**: Operatör A presterar X% bättre när partnern är Operatör B
   - **Operatörscertifiering** (`/admin/certifiering`): Spåra certifieringar per linje/maskin
   - **Annotationer i grafer**: Markera driftstopp, helgdagar, nya operatörer direkt i tidslinjen
   - **Skiftrapport auto-export**: Skicka skiftsammanfattning som PDF till chef vid skiftslut
5. Om ovan är klara: granska koden, leta buggar, sök internet efter nya MES/OEE-features
6. Uppdatera denna fil med beslut och observationer

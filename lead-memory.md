# Lead Agent Memory — MauserDB

*Detta är ledaragentens persistenta minne. Uppdateras varje session.*
*Senast uppdaterad: 2026-03-04*

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

### BACKLOGGEN FÅR ALDRIG VARA TOM — ÄGARENS ABSOLUTA KRAV
**Ägaren ska ALDRIG behöva se en tom backlog. Det är ledaragentens ansvar att ligga FÖRE — inte reagera när det tar slut.**

Ledaragenten ska **proaktivt fylla på backloggen** INNAN den töms. Målet är att alltid ha minst 5-8 öppna items. Gör behovsanalys LÖPANDE — inte bara när det är tomt.

### Behovsanalys — gör detta varje session som en del av den normala rutinen:
1. **Läs igenom hela kodbasen** (noreko-frontend/src/app/pages/, noreko-backend/classes/) — identifiera:
   - Sidor som saknar tomma-lista-hantering (empty states)
   - Grafer med låg datakvalitet eller saknade labels
   - Features som är halvfärdiga eller kan förbättras
   - Sidor som ser inkonsistenta ut jämfört med resten av appen
   - Mobilanpassning som saknas
   - Laddningstillstånd (loading spinners) som saknas
2. **Sök internet varje session** (WebSearch) efter:
   - "manufacturing execution system features 2025"
   - "OEE dashboard best practices"
   - "operator bonus system gamification"
   - "production floor KPI display"
   - "IBC container recycling production tracking"
   — Plocka de bästa idéerna och lägg i IDÉBANK
3. **Tänk på användarresor** — vad gör operatören kl 06:00? Vad kollar VD måndag morgon? Vad behöver skiftledaren precis vid skiftbyte? Bygg för dessa scenarios.
4. **Identifiera datamöjligheter** — vilka kolumner i rebotling_ibc/rebotling_skiftrapport används INTE ännu? Kan de visualiseras?

### Om backlogen mot förmodan ändå är tom — eskalera omedelbart:
1. Starta en dedikerad **behovsanalys-agent** som läser HELA kodbasen och returnerar 10+ nya backlog-items
2. Starta en **bug hunting-agent** parallellt
3. Sök internet efter inspiration
4. Återuppta aldrig med färre än 5 nya items i backloggen

### Lead-agentens kreativa ansvar
- Ledaragenten SKA ligga FÖRE projektet — alltid veta vad nästa 3 batchar ska göra
- Läs koden, identifiera vad som fattas, hitta inspiration online, föreslå och implementera via worker-agenter
- Lägg alltid till nya funktioner i navigationsmenyn (app.routes.ts + nav-komponent) så de är åtkomliga
- Kunden ser allt efteråt och bestämmer vad som ska vara kvar — jobba fritt och kreativt
- Prioritera: mer detaljerad data i befintliga grafer, nya vyer som ger VD och operatörer mer insikt
- **BACKLOGGEN FYLLS PÅ KONTINUERLIGT UNDER UTVECKLINGEN** — varje agent som är klar triggar ledaragenten att lägga till 2-3 nya items baserat på vad som just byggts och vad som naturligt följer

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
- *2026-03-04*: **RATE LIMIT / AVBROTT** — "Ni ska fortsätta hela tiden automatiskt, lös det så det inte stannar." + "Ledaragenten ska ha koll på allt detta och se till att jobb utförs, även efter man får nya tokens." → Ledaragenten ska OMEDELBART starta om misslyckade agenter när rate-limit upphör (token-reset). Inget avbrott tolereras. Om worker-agenter returnerar "out of extra usage" — starta om dem direkt i nästa svar. Ledaragenten ska självständigt hålla koll på vad som misslyckades och driva arbetet vidare utan att ägaren behöver påminna.

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

## NÄSTA BATCH (session 2026-03-04 eftermiddag)

### 🔴 Hög prioritet
- [x] **Rebotling-skiftrapport trendgraf**: KLAR `6af3e1e` — timupplösning vs genomsnittsprofil, skiftnavigering
- [x] **Operatörsdashboard förbättring**: PÅGÅR (a1934683e7c0bf6f6) — veckovy, trend, summary-kort
- [x] **Andon-tavla skiftnoter**: PÅGÅR (ad5e36138558c6ba2) — okvitterade noter, urgency-badge
- [ ] **VD Månadsrapport förbättring**: Månadsrapport (`/rapporter/manad`) behöver: föregående månads-jämförelse med diff-indikatorer, "Månadens bästa dag" highlight, operator-of-the-month tabell, bättre PDF-design. Backend: month-compare endpoint.
- [ ] **Executive dashboard: multi-linje status**: Lägg till real-time statusrad för alla 4 linjer i executive dashboard — grön/orange/röd per linje baserat på om de körs + senaste OEE.

### 🟡 Medium prioritet
- [ ] **Rebotling statistik: Pareto-diagram för stopporsaker**: Horisontellt pareto-diagram (80/20-regel) med kumulativ linje i production-analysis. Visar vilka stopporsaker som tar mest produktionstid.
- [ ] **Skiftplaneringsvy förbättring**: `/admin/skiftplan` — lägg till veckoöversikt (peka ut operatörer per skift med drag-and-drop eller klick-assign), integration med faktisk närvaro från rebotling_ibc.
- [ ] **Bonus-admin: Utbetalningshistorik** — lista historiska bonusutbetalningar per månad per operatör. Kräver `bonus_payouts`-tabell. Migration + admin-vy med period-filter.
- [ ] **Min bonus: Jämförelse med kollegor** — anonymiserad rankingtabell i min-bonus: "Du är #2 av 5 operatörer denna vecka" utan att visa andras exacta bonus.

### 🟢 Lägre prioritet
- [ ] **Cykeltid per operatör** — breakdown av cykeltids-histogrammet per operatör. Visa vilken operatör som har bäst (lägst) median cykeltid. Ny endpoint i RebotlingController.
- [ ] **Underhållslogg: maskin-koppling** — koppla underhållshändelser till stopporsakskategorier. "Maskin X stoppade Y gånger och underhåll gjordes Z gånger" korrelationsanalys.
- [ ] **Rebotling-live: mobiloptimering** — responsiv design för `/rebotling/live` på telefon/surfplatta (EJ ändra logic, bara CSS media queries).
- [ ] **Nyhetsflöde admin-panel** — enkel create/edit/delete för nyheter direkt i admin-dropdownen utan att navigera till separat sida.

---

## NÄSTA BATCH (session 2026-03-04 kväll — pågår)

### 🔴 Hög prioritet
- [x] **Email-notis brådskande not** (ad06aef): PÅGÅR — PHP mail() + notification_emails setting i admin
- [x] **Min-bonus historik-export CSV/PDF** (a1c45eeb): PÅGÅR — CSV-download + window.print()
- [x] **Bug Hunt #8** (a0e7697): PÅGÅR — grep alla .ts/.php sedan senaste bugfix
- [x] **Dagdetalj drill-down** (ac06df25): PÅGÅR — klick på dag i kalender → timvis bargraf

### 🟡 Medium prioritet
- [x] **Rebotling statistik custom date range** (aa3b192): PÅGÅR — Från/Till datumfält
- [x] **Notifikationscentral navbar** (a4d2d65): PÅGÅR — klockikon med badge

### 🟢 Kommande (nästa batch efter dessa är klara)
- [ ] **Skiftbyte-PDF automatgenerering** — Vid skiftslut: generera PDF-sammanfattning automatiskt, länk i UI
- [ ] **Operatörsnärvaro-tracker** — Spåra vilka operatörer som faktiskt arbetat (från rebotling_ibc), kalendervy
- [ ] **Live-ranking admin-konfig** — Konfigurera vilka KPI:er som visas på TV-skärmen (`/rebotling/live-ranking`)
- [ ] **Benchmarking förbättring** — Lägg till månadsvis topplista, personbästa vs team-rekord
- [ ] **Skiftrapport per operatör** — Filtrerbara skiftrapporter per specifik operatör
- [ ] **IBC-kvalitets deep-dive** — Bryt ner ej-godkända IBC-tankar per avvisningsorsak (om data finns)
- [ ] **Produktionsmål-historik** — Graf som visar hur dagsmålet ändrats över tid (ur rebotling_settings historik)
- [ ] **Bug Hunt #9** — Granska alla nya features från senaste batchen

---

## IDÉBANK — Autonomt genererade features (implementera fritt, kunden utvärderar)

### Grafer — mer detaljerad data
- [x] **Cykeltids-histogram**: Levererat `e4ca058` — histogrambuckets (0-2/2-3/3-4/4-5/5-7/7+min), KPI-brickor (Snitt/P50/P90/P95), grön stapelgraf, datumväljare. Backend: cycle-histogram endpoint med fallback till rebotling_ibc.
- [x] **Kontrollkort (SPC)**: Levererat `e4ca058` — X̄±2σ kontrollgränser, 4 dataset (IBC/h blå, UCL röd, LCL orange, medelvärde grön), dagar-väljare (3/7/14/30). Backend: spc endpoint med sample stddev.
- [x] **Kvalitetstrendkort**: Redan implementerat (verifierat a682f9d) — linjegraf daglig%+7d rullande, KPI-brickor, periodväljare 14/30/90 dagar.
- [x] **Waterfalldiagram OEE**: Redan implementerat (verifierat a682f9d) — horisontellt staplat A/P/Q/OEE diagram, KPI-brickor färgkodade.

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

## AKTIVA AGENTER (session 2026-03-04 kväll)
- **Bonus-dashboard IBC/h veckotrendgraf** (a73cdefab): KLAR — d319b6b
- **Operator-compare radar-diagram** (adcaa935): KLAR — 13a24c8
- **Admin operatörslista förbättring** (afd3a1bb): KLAR — f8ececf
- **Bug Hunt #8** (a0e7697): PÅGÅR
- **Dagdetalj drill-down** (ac06df25): PÅGÅR
- **Email-notis skiftöverlämning** (ad06aef): PÅGÅR
- **Min-bonus historik-export CSV/PDF** (a1c45eeb): PÅGÅR
- **Rebotling statistik date range picker** (aa3b192): PÅGÅR
- **Notifikationscentral navbar** (a4d2d65): PÅGÅR

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
- `28dae83` — **Adaptiv grafgranularitet**: Toggle "Per dag / Per skift" i OEE-trend, veckojämförelse, cykeltrend. 3× fler datapunkter vid per-skift. CSS pill-knappar. Bakåtkompatibelt.
- `9001021` — **Benchmarking-vy + What-if simulator**: `/rebotling/benchmarking` — rekordvecka, topp-10 tabell, månadsöversikt bar chart. What-if bonussimulator som ny flik i bonus-admin — justera tier-parametrar med sliders → se kostnadsberäkning per operatör. Backend: benchmarking + simulate endpoints. (Båda features i samma commit)
- `ad4429e` — **Korrelationsanalys**: Bästa operatörspar i operators-sidan — UNION ALL SQL för alla par-kombinationer, LEAST/GREATEST-normalisering, topp-20 sorterat IBC/h, avatarer + stat-pills per par. Ny endpoint: `?run=pairs`.
- `153729e` — **Prediktiv underhållsindikator**: Ny sektion i rebotling-admin — cykeltidstrend 8 veckor, baseline vs nuvarande, status ok/warning/danger (>15%/>30% ökning), Chart.js linjegraf med orange kurva + grön baslinje, 5-min polling.
- `e9e7590`+`d997b06` — **Månadsrapport + Skiftplaneringsvy**: `/rapporter/manad` — 6 KPI-kort, daglig bar chart, PDF-export, "Rapporter"-dropdown nav. `/admin/skiftplan` — 7×3-grid kalender, operatörsbadges, assign/remove per skift. Ny DB-tabell `shift_plan`, ny controller `ShiftPlanController.php`, migration. (Files bundlade i samma commits av parallella agenter)
- `8404b29` — **Skiftjämförelse-agent**: Sida-vid-sida KPI-jämförelse med diff-badges, operatörstabeller, shift-compare endpoint. Admin PLC-varningsbanner med blinkanimation vid >15min utan data.
- `3a89898` — **Heatmap+mobil-agent**: Interaktiv heatmap-tooltip, KPI-toggle (IBC/h/Kvalitet%/OEE%), dynamisk färgskala+legend, noll-celler grå. my-bonus mobilanpassad (responsive 768px/480px, touch-targets 44px).
- `92cbcb1` — **Bug hunt #1**: 8 buggar fixade — takeUntil-läckor i bonus-dashboard, timeout saknas i my-bonus HTTP-anrop, isFetching-guard i rebotling-admin, prematur loading-reset i production-analysis, BonusController sendError() HTTP 200 vid fel, FILTER_SANITIZE_STRING deprecated PHP 8.2.
- `82173ec` — **Bonus-agent**: My-bonus: statusbricka (rekordnivå/uppåt/etc.), IBC/h-trendgraf 7 skift med glidande snitt, skiftprognos-banner, PDF-export. Bonus-dashboard: trendpilar ↑↓→ vs föregående period, veckobonusmål-progressbar för team + per operatör. Bonus-admin: ny Prognos-flik (sök operatör → snittbonus/tier/IBC/h), veckobonusmål-konfiguration. Backend: operator_forecast endpoint, set_weekly_goal endpoint. Migration: `2026-03-03_bonus_weekly_goal.sql`.
- StatusController.php: session_start(['read_and_close']) för att undvika PHP-session-låsning
- `4442ed5`+`611dbff` — **Historisk jämförelse**: `/rebotling/historik` — månadsöversikt stapeldiagram (grön/röd vs snitt), år-mot-år linjegraf per ISO-vecka, månadsdetaljstabell med trendpilar, KPI-sammanfattningskort (total/snitt/bästa månad), periodval 12–48 månader. Backend HistorikController monthly+yearly endpoints.

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
4. Om backlogen mot förmodan är tom: starta behovsanalys-agent + bug hunting-agent parallellt
5. Uppdatera denna fil med beslut och observationer

## BACKLOG — NY BATCH (2026-03-03 natt)

### ✅ Levererat denna session (natt)
- `a3d5b49` — Live-ranking TV-skärm
- `e4ca058` — Histogram + SPC
- `cc4ba9f` — Kalender + Executive alerts
- `28dae83` — Adaptiv grafgranularitet
- `9001021` — Benchmarking + What-if simulator
- `d44a4fe` — Kvalitetstrendkort + OEE Waterfall
- `22bfe7c` — Operatörscertifiering
- `078e804` — Graf-annotationer
- `ad4429e` — Korrelationsanalys
- `153729e` — Prediktiv underhållsindikator
- `e9e7590`+`d997b06` — Månadsrapport + Skiftplaneringsvy
- `ca4b8f2` — Digital skiftöverlämning
- `1feb15e` — Skiftrapportkommentar
- Bonusprognos i kr — pågår (a74a34ff)
- `ddbade9` — Andon-tavla full-screen TV (`/rebotling/andon`) — AndonController, OEE/dagsmål/IBC-per-h KPI-kort, 10s polling, linjestatus (kör/väntar/stopp), färgkodad grön/orange/röd, navbar dold automatiskt
- `1feb15e` — Skiftrapportkommentar — rebotling_skift_kommentar-tabell, GET/POST endpoints, lazy-load, teckenräknare, PDF-inkluderat
- `ca4b8f2` — Digital skiftöverlämning — shift_handover-tabell, prioritetskort (normal/viktig/brådskande), 60s polling, authGuard

### 🔴 Hög prioritet — ÖPPNA
- [x] **Stopporsaksanalys Pareto** (`/stopporsaker`): Levererat `0f4865c` — ny Pareto-flik, kombinerat Chart.js (staplar+linje), custom 80%-referenslinje, vital/trivial-badges, KPI-kort, datumfilter 7/30/90 dagar.
- [x] **Bug hunt #3**: Levererat `20686bb` — 6 buggar fixade: shift-plan timeout/catchError, live-ranking withCredentials+Subscription-rensning, production-calendar withCredentials, benchmarking setTimeout-referens, CertificationController session_start() utan guard.

### 🟡 Medium prioritet — ÖPPNA
- [x] **Bonusprognos i kr**: Levererat `e472997` — bonus_level_amounts-tabell, admin konfigurerar SEK/nivå (Brons/Silver/Guld/Platina), my-bonus visar "~1 000 kr denna månad" + nästa nivå delta-kr + progress-bar.
- [x] **Operatörsdashboard** (`/admin/operator-dashboard`): Levererat `4fb35a1` — live-vy med 4 KPI-kort, operatörstabell (initialer-avatar, IBC/h, kvalitet%, status-badge), 60s polling, adminGuard.
- [x] **OEE World-class benchmark**: Levererat `6633497` — WCM 85% grön streckad linje + Branschsnitt 70% orange streckad linje i OEE-trend-grafen, legend-förklaring ovanför canvas.
- [x] **Nyhetsflöde startsidan**: Levererat `6633497` (bundlad med OEE benchmark) — NewsController.php, 4 händelsetyper (rekordag/hög OEE/certifiering/urgentnotat), färgkodade vänsterborders, 5-min polling.
- [x] **Klassificeringslinje förberedelsearbete**: Levererat `d01b2d8`+`e7374f4` — KlassificeringslinjeController.php (settings/systemstatus/weekday-goals), klassificeringslinje-admin, migration, route `/klassificeringslinje/admin`, nav-länk.
- [x] **Såglinje förberedelsearbete**: Levererat `d01b2d8` — SaglinjeController.php, saglinje-admin (formulär+systemstatus+veckodagsmål), migration, route, nav.
- [x] **Notifikationsbadge**: Levererat `208eb8d` — röd badge på Rebotling-dropdown + Skiftöverlämning-länk, 60s polling, kräver inloggning, session_start read_and_close.
- [ ] **Push-notifikation my-bonus**: Web Push API — notifiera operatör när de passerar bonusnivå (Brons→Silver→Guld).
- [ ] **OEE World-class benchmark**: Referenslinje i OEE-grafer: "WCM = 85%". Informationsruta om branschsnitt (65-75%). — PÅGÅR
- [ ] **Automatisk skiftrapport-export**: POST-endpoint vid skiftslut → genererar PDF → emailar till konfigurerade mottagare.

### 🟢 Lägre prioritet — ÖPPNA
- [ ] **Energieffektivitet-vy** (`/rebotling/energi`): Om energidata finns — IBC per kWh, energikostnad per IBC.
- [x] **Bug hunt #4**: Levererat `dc5c4df` — 14 fixes: news.ts subscriptions (4), menu.ts takeUntil+timeout/catchError (3), KlassificeringslinjeController session_start guard (2), bonus-admin.ts subscriptions (8).
- [x] **Månadsrapport förbättring**: Levererat `ba533b7` — OEE-trend linjegraf (WCM 85% referens), topp-3 operatörer med medaljer, bästa/sämsta vecka, total stillestånd KPI, gold/worst-row markering i veckosammanfattning.
- [x] **Veckodag-analys**: Levererat `632c0fe` — ny sektion i rebotling-statistik, bästa dag grön/sämsta röd, tooltip med OEE+max+min, datatabell, dropdown 30/90/180/365 dagar, weekday-stats endpoint i RebotlingController.
- [x] **Tvättlinje förberedelsearbete**: Levererat `8040402` — TvattlinjeController getSettings/setSettings/getSystemStatus/getWeekdayGoals, tvattlinje_settings + tvattlinje_weekday_goals tabeller, tvattlinje-admin utbyggd med formulär+systemstatus+veckodagsmål.
- [ ] **Nyhetsflöde förbättring**: "Senaste händelser"-sektion på startsidan — ny rekordag, certifiering uppnådd, bonusnivå ändrad.
- [x] **Export förbättring**: Levererat `8040402` — SheetJS aoa_to_sheet, kolumnbredder, fryst header-rad, sammanfattningsblad. Alla 3 skiftrapportsidor (rebotling/tvattlinje/saglinje).
- [x] **Operatörsdashboard**: Levererat `4fb35a1` — UNION ALL op1/op2/op3 från rebotling_skiftrapport, IBC/h, kvalitet%, status-badge, 60s polling, adminGuard.

### 🔵 IDÉBANK — framtida sessioner
- Maskinlärning-prediktion: förutsäg morgondagens produktion baserat på historik
- QR-kod per tank: scanna → se hela historiken för just den tanken
- Flödesanalys: visualisera IBC-flödet genom hela anläggningen (in → tvätt → ut)
- Kundportal: extern vy för kunder (kräver auth + sub-domain)

---

## NÄSTA BATCH (2026-03-04 eftermiddag / kväll)

### 🔴 Pågående
- [ ] **Certifikatsvarnings-badge** (a7370a70): PÅGÅR — badge på Certifiering-länk i Admin-menyn
- [ ] **Bonus-dashboard IBC/h-trendgraf** (a73cdefab): PÅGÅR — daglig trend per operatör
- [ ] **Produktionskalender Excel-export** (afbc22a8): PÅGÅR

### 🟡 Planerat
- [ ] **Operator-compare: radar-diagram** — Multi-dimensionell jämförelse (IBC/h, Kvalitet%, OEE, aktiva dagar, snitt cykeltid) som radar chart. Touches operator-compare.ts + OperatorCompareController.
- [ ] **Produktionsanalys: Dagdetalj-vy** — Klick på dag i produktionskalendern → popup/drill-down med timvisning för den dagen (per-timme IBC/h, stopporsaker, operatörer). Ny endpoint `action=rebotling&run=day-detail&date=YYYY-MM-DD`.
- [ ] **Skiftöverlämning: Email-notis** — När en brådskande not skapas → POST till en PHP-endpoint som triggar PHP mail() till konfigurerad admin-adress. Konfigureras i rebotling_settings.
- [ ] **Nyhetsflöde: Senaste händelser** — Startsidans "Senaste händelser" sektion (redan delvis implementerad) förbättras med: produktionsrekord denna vecka, certifieringsuppdateringar, bonus-trösklar som passerats. Auto-uppdatering var 5 min.
- [ ] **Admin: Operatörslista förbättring** — `/admin/operators` — lägg till: sorterbar kolumn för senaste aktivitet, export som CSV/Excel, bulk-inaktivera, länk till operatörsprofil (/admin/operator/:id).
- [ ] **Rebotling-statistik: Custom date range** — Lägg till en "Anpassad period"-option i datum-väljarna som öppnar en datepicker-range istället för fasta perioder (7/14/30/90 dagar).
- [ ] **Bug hunt #8** — Kör när 3+ nya features har tillkommit sedan bug hunt #7. Granska: operator-detail.ts, executive-dashboard.ts, bonus-dashboard.ts, production-calendar.ts, maintenance-log.ts, news-admin.ts.
- [ ] **Min-bonus: Historik-export** — Knapp för att ladda ned sin personliga bonus-historik som PDF eller CSV.

# Lead Agent Memory — MauserDB

*Detta är ledaragentens persistenta minne. Uppdateras varje session.*
*Senast uppdaterad: 2026-03-04 (session #4)*

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

### Åtgärdat `d9bc8f0` — 2026-03-04 (Bug Hunt #9)

**PHP — Saknade auth-kontroller på admin GET-endpoints:**
- `RebotlingController.php`: 8 admin-only GET-endpoints saknade sessionskontroll — `admin-settings`, `weekday-goals`, `shift-times`, `system-status`, `alert-thresholds`, `today-snapshot`, `notification-settings`, `all-lines-status` var åtkomliga utan autentisering. En angripare som kände till API-URL:erna kunde läsa konfigurations- och notifikationsdata.
- ÅTGÄRD: Tidig kontroll i GET-dispatchern med `$adminOnlyActions`-array + `session_start(['read_and_close'])` + `user_id/role=admin`-check.

**Granskat och godkänt (inga buggar):**
- `OperatorCompareController.php`: auth korrekt i `handle()` (session_start + role=admin check)
- `MaintenanceController.php`: auth korrekt i `handle()` (session_status guard + admin check)
- `BonusAdminController.php`: auth korrekt via `isAdmin()` i `handle()`
- `ShiftPlanController.php`: `requireAdmin()` kallas korrekt före alla mutations-endpoints
- `RebotlingController.php POST-block`: session_start + admin-check på korrekt plats
- Angular HTTP-anrop: Alla polling-calls har `timeout()+catchError()+takeUntil(destroy$)`. Admin save-calls har `takeUntil(destroy$)` (timeout ej obligatoriskt för user-triggered one-shot calls).
- Angular routes: Alla `/admin/`-rutter har `adminGuard`. `rebotling/benchmarking` har `authGuard`. `live-ranking`/`andon` är publika avsiktligt.

### Kvarstående observerat (ej buggar, men noteringar):
- `rebotling-statistik.ts` hade **pre-existing uncommitted changes** när bug-hunting kördes — ett heatmap-KPI-val feature i progress. Ej påverkat av bug-hunting-committen.
- GET-endpoints för live-data i RebotlingController (status, rast, statistics, day-stats, osv.) är publika by design — produktionsgolvet ska kunna se live-data utan inloggning

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

## AKTIVA AGENTER (session 2026-03-04 kväll #2)
*(Inga aktiva — startar nya nedan)*

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

### ✅ Levererat (2026-03-04 eftermiddag/kväll)
- [x] **Bonus-dashboard IBC/h-trendgraf**: KLAR `8c1fad6` — veckans hjälte, diff-indikatorer, CSV-export
- [x] **Operator-compare radar-diagram**: KLAR `10922ce` — periodval, CSV-export, diff-badges
- [x] **Admin Operatörslista förbättring**: KLAR `5f0c9c1` — audit-log+stoppage-log KPI, action-ikoner
- [x] **Rebotling-statistik custom date range**: Levererat
- [x] **Min-bonus historik-export**: Levererat (CSV/PDF)

### 🟡 Fortfarande öppna
- [ ] **Skiftöverlämning: Email-notis** — PHP mail() vid brådskande not → admin-adress
- [ ] **Nyhetsflöde: Senaste händelser förbättring** — utöka med produktionsrekord, certifieringsuppdateringar, bonus-trösklar

---

## NÄSTA BATCH (2026-03-04 natt — kodbasanalys-fynd)

### 🔴 Hög prioritet (från automatisk kodbasanalys)
- [x] **Bug Hunt #10 — RebotlingController aggregering**: KLAR `dcf9a4e` — COALESCE(MAX(...), 0) tillagt
- [x] **Bug Hunt #10 — BonusController parametrar**: KLAR `7c1d898` — period whitelist-validering tillagd
- [ ] **Bug Hunt #10 — BonusAdmin threshold-validering**: Kontrollera att `brons < silver < guld < platina` vid sparande. Avvisa negativa värden och extrema belopp (> 100000 SEK).
- [x] **Bug Hunt #10 — bonus-dashboard.ts**: KLAR — timeout(8000)+catchError() finns nu på alla tre anrop
- [x] **Skiftrapport empty-state**: KLAR — "Ingen skiftrapport registrerad för vald period" + disabled export-knappar

### 🟡 Medium prioritet
- [x] **Operatörsprestanda-trend**: KLAR `1ce8257` — IBC/h-trendgraf 8/16/26 veckor + lagsnitt
- [ ] **Historik/Audit Log pagination**: Backend LIMIT+OFFSET + frontend "Ladda fler"-knapp — annars kan 10 000+ rader orsaka timeout
- [x] **My Bonus — tom-state för ranking**: KLAR `334af16`
- [ ] **Chart error-boundary**: try-catch runt chart?.destroy() — 59% av 59 Chart.js-instanser SAKNAR try-catch (rebotling-statistik 11, my-bonus 12, stoppage-log 10)
- [x] **Cykeltid per operatör**: KLAR `d23d330` — horisontellt bar-diagram + rangtabell

### 🟢 Lägre prioritet
- [ ] **Prediktiv underhållsindikator v2**: Korrelationsanalys maskin-stopp vs. underhållshändelser
- [x] **Nyhetsflöde förbättring**: KLAR `17d7cfa` — rekordnyhet-trigger i NewsController
- [x] **Exportknappar disable-state**: KLAR `dcf9a4e`

---

## NÄSTA BATCH (MES-research 2026-03-04 natt)

### 🔴 Hög prioritet (online research + affärsvärde)
- [x] **Kassationsorsaksanalys**: KLAR `f1d0408` — Pareto-chart + registrering
- [x] **Winning-the-Shift scoreboard**: KLAR `9e9812a` — Andon med IBC kvar + S-kurva
- [x] **Flexibla dagsmål per datum**: KLAR `fc66bcb` — produktionsmal_undantag + admin-UI
- [ ] **Prediktivt underhåll körningsbaserat** — Serviceintervall baserat på IBC-volym. Admin-UI + varning när < 10% kvar

### 🟡 Medium prioritet
- [x] **MTTR/MTBF per utrustning**: KLAR `6075bfa` — maintenance-log flik
- [x] **OEE A/P/Q-komponentuppdelning v2**: KLAR `c6ba987` — daglig trendgraf i statistik
- [x] **Certifikat-utgångvarning i exec-dashboard**: KLAR `6075bfa`
- [x] **Kumulativ dagskurva (S-kurva)**: KLAR `9e9812a` — i Andon-tavlan
- [x] **Bemanningsvarning i shift-plan**: KLAR `f1d0408`

### 🟢 Lägre prioritet
- [x] **Operatörsfeedback-loop**: KLAR `2981f70` — stämnings-emoji + kommentar i My-bonus
- [x] **QR-kod till stopplogg**: KLAR `b6b0c3f` — QR per maskin
- [ ] **Push-notiser webbläsare** — Web Push API vid stopp > 10 min eller urgent handover-not

---

## AKTIV BATCH (2026-03-04 kväll #3 — DENNA SESSION)

### ✅ Levererat denna session
- [x] **Chart error-boundary (delvis)**: `fd92772` — try-catch runt destroy() i bonus-charts, my-bonus, rebotling-statistik (3 filer)
- [x] **Audit-log pagination**: `44f11a5` — LIMIT+OFFSET backend, ladda-fler frontend

### 🔴 Hög prioritet — Workers startas NU
- [x] **Bug Hunt #12 — resterande Chart error-boundary**: KLAR `6e36544` — 18 filer, ~64 locations fixade med try-catch. BonusAdmin threshold-validering tillagd (negativa, max 100k, stigande ordning).
- [x] **Skiftrapport per operatör**: KLAR — redan implementerat, verifierat: skiftrapport-list med ?operator=X, skiftrapport-operators endpoint, 5 KPI-kort, responsive grid.
- [x] **VD Månadsrapport förbättring**: KLAR — diff-indikatorer på KPI-kort, bästa dag-highlight, operator-of-the-month med score, vecko-progress bars, PDF-stöd.

### 🟡 Medium — planeras efter dessa
- [x] **Skiftöverlämning: Email-notis** — redan implementerat: mail() vid urgent, admin email-config, validering
- [ ] **Prediktiv underhållsindikator v2** — Korrelationsanalys maskin-stopp vs. underhåll
- [ ] **Push-notiser webbläsare** — Web Push API vid stopp > 10 min
- [x] **Skiftsammanfattning PDF-export**: KLAR `be0eea4` — shift-summary endpoint, expanderbar detaljvy per skift, 6 KPI-kort, timvis PLC-data, print-optimerad PDF med A4-format

### 🔵 Batch 3
- [x] **Prediktiv underhåll v2** — KLAR: Pearson-korrelation med lagg, 4 KPI-kort, dubbelaxel-graf (staplar+linje), datatabell
- [x] **Nyhetsflöde förbättring** — KLAR: 4 nya auto-triggers (produktionsrekord, OEE≥85%, bonus-milstolpe, 5+ dagars streak), admin redan på plats

---

## NY BATCH (från behovsanalys kväll #3 — 53 fynd)

### 🔴 Hög prioritet (UX-kritiskt)
- [x] **Empty-states batch 1**: KLAR `164e0d0` — 6 sidor: operator-attendance, weekly-report, rebotling-prognos, benchmarking, live-ranking, certifications
- [x] **Empty-states batch 2**: KLAR — 6 sidor: my-bonus (weekly+feedback), operator-detail, saglinje-admin, tvattlinje-admin, andon, operator-trend + 4 TS-fix i stoppage-log
- [x] **Mobilanpassning batch 1**: KLAR `c472c8e` — operator-attendance (768/480px), bonus-dashboard (toggle+tabell+kort), operators (2-kol tablet, 1-kol mobil)
- [x] **Mobilanpassning batch 2**: KLAR `a6637ad` — audit-log (flex-wrap+fonts), stoppage-log (tabell+nowrap bort), statistik (chart 250px)

### 🟡 Medium prioritet
- [x] **Loading-states batch**: KLAR — 3 sidor uppgraderade (prognos, certifieringar, attendance), 2 redan hade spinners
- [x] **Design-konsistens fix**: KLAR `a6637ad` — stoppage-log+audit-log flat colors, bonus-dashboard Bootstrap-overrides
- [x] **Chart.js tooltip-förbättring**: KLAR — 6 grafer: audit-log (sv dagnamn), production-calendar (datum+mål), stoppage-log (4 charts: h:mm, andel%, peak-varning)

### 🟢 Lägre prioritet
- [ ] **Halvfärdiga features-granskning**: Granska och slutför eller ta bort: rebotling-prognos, operator-detail, vpn-admin

### 🔵 IDÉBANK
- Maskinlärning-prediktion: förutsäg produktion
- Flödesanalys: visualisera IBC-flödet genom anläggningen
- Kundportal: extern vy
- Energieffektivitet-vy
- Push-notiser webbläsare

---

## BESLUTSDAGBOK (forts.)
**2026-03-04 kväll #2**: Massiv genomgång — ~30 nya commits sedan senaste ledarsession. Nästan alla MES-research items och kodbasanalys-items levererade. Kvarstår: Chart error-boundary (59% osskyddade), BonusAdmin threshold-validering, historik-pagination, prediktivt underhåll körningsbaserat. Startar 3 workers: bug hunt, pagination, skiftrapport-filter.
**2026-03-04 kväll #3**: Committade uncommitted chart error-boundary-ändringar (fd92772). Audit-log pagination redan levererat (44f11a5). Prediktivt underhåll körningsbaserat redan levererat (dev-log). 5 workers körda: Bug Hunt #12 (18 filer), Skiftrapport per operatör (redan klart), VD Månadsrapport (diff+operator-of-month), Skiftöverlämning email (redan klart), Skiftsammanfattning PDF-export (be0eea4). Alla hög+medium items levererade. Startar nästa batch: Prediktiv underhåll v2, Push-notiser, nya idébank-items.
**2026-03-04 session #4**: Genomgång av alla öppna items. Kväll #3 levererade: empty-states (12 sidor), mobilanpassning (6 sidor), loading-states, design-konsistens, Chart.js tooltips, prediktiv underhåll v2 (korrelation). Massiv leverans — nästan alla behovsanalys-items klara. Kvarstående öppna: Executive dashboard multi-linje, bonus-admin utbetalningshistorik, halvfärdiga features-cleanup, push-notiser, skiftplaneringsvy förbättring. Startar 3 workers: multi-linje exec dashboard, bonus utbetalningshistorik, halvfärdiga features-granskning.

---

## AKTIV BATCH (2026-03-04 session #4)

### ✅ Redan implementerat (verifierat session #4)
- [x] **Executive dashboard: multi-linje status**: Redan implementerat — `all-lines-status` endpoint + `loadAllLinesStatus()` i exec-dashboard, 60s polling, grön/orange/röd per linje.
- [x] **Bonus-admin: Utbetalningshistorik**: Redan implementerat — `list-payouts`, `record-payout`, `delete-payout`, `payout-summary`, `update-payout-status` endpoints. Frontend har payoutHistory-flik med filter, CSV-export.
- [x] **Halvfärdiga features**: Granskat — rebotling-prognos (184 rader, komplett), operator-detail (622 rader, komplett), vpn-admin (183 rader, komplett). Alla är färdiga.

### 🔴 Hög prioritet — Workers startas NU
- [ ] **Stub-katalog cleanup**: Ta bort gamla/oanvända stub-kataloger: `pages/rebotling/` (9 filer, stub) och `pages/tvattlinje/` (9 filer, stub). Routing pekar på de korrekta `-live/`-katalogerna.
- [ ] **Min bonus: Jämförelse med kollegor**: Anonymiserad ranking "Du är #X av Y denna vecka" i my-bonus. Endpoint i BonusController.
- [ ] **Automatisk skiftrapport-export**: POST-endpoint vid skiftslut → generera PDF → email till konfigurerade mottagare.

### 🟡 Medium — nästa batch
- [ ] **Push-notiser webbläsare**: Web Push API vid stopp > 10 min
- [ ] **Skiftplaneringsvy förbättring**: Veckoöversikt med operatörs-assign

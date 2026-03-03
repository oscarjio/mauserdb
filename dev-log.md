# MauserDB Dev Log

---

## 2026-03-03 — Adaptiv grafgranularitet (per-skift toggle) — commit 28dae83

### Nytt: Per-skift granularitet i rebotling-statistik

**Syfte:** VD och produktionschefer ville se produktion INOM dagar, inte bara dag-för-dag. En dag-för-dag-graf dolde om morgonsskiftet var bra men kvällsskiftet dåligt. Lösningen: toggle "Per dag / Per skift" på tre grafer.

**Backend — `noreko-backend/classes/RebotlingController.php`:**
- `getOEETrend()`: stödjer nu `?granularity=shift`. Per-skift-SQL aggregerar med `MAX(kumulativa fält) per (DATE, skiftraknare)`, beräknar OEE, Tillgänglighet, Prestanda, Kvalitet per skift. Label: `"DD/MM Skift N"`. Bakåtkompatibelt — default är `'day'`.
- `getWeekComparison()`: stödjer nu `?granularity=shift`. Returnerar varje skift för de senaste 14 dagarna med veckodags-label (t.ex. `"Mån Skift 1"`). Splittar i `this_week`/`prev_week` baserat på datum.
- `getCycleTrend()`: stödjer nu `?granularity=shift`. Returnerar IBC OK, cykler, IBC/h per skift. Label: `"DD/MM Skift N"`.

**Teknisk detalj — kumulativa fält:** `ibc_ok`, `runtime_plc`, `rasttime` i `rebotling_ibc` är kumulativa per `skiftraknare` — `MAX()` per `(DATE, skiftraknare)` ger korrekt skifttotal. `SUM()` vore fel.

**Service — `noreko-frontend/src/app/services/rebotling.service.ts`:**
- `getWeekComparison(granularity?)`, `getOEETrend(days, granularity?)`, `getCycleTrend(days, granularity?)` tar valfri granularity-param och skickar med som query-param.
- Interface `OEETrendDay`, `WeekComparisonDay`: nya valfria fält `label?`, `skiftraknare?`.
- Interface `CycleTrendResponse`: `granularity?` + `label?`, `skiftraknare?` i daily-objekten.

**Frontend — `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts`:**
- Nya state-props: `oeeGranularity`, `weekGranularity`, `cycleTrendGranularity` (default `'day'`).
- Nya toggle-metoder: `setOeeGranularity()`, `setWeekGranularity()`, `setCycleTrendGranularity()` — nollställer `loaded` och laddar om data.
- `renderOEETrendChart()` och `renderWeekComparisonChart()` använder `d.label ?? d.date.substring(5)` för att visa skift-labels automatiskt.
- Ny `loadCycleTrend()` + `renderCycleTrendChart()` — stapeldiagram (IBC OK, vänster y-axel) + linjediagram (IBC/h, höger y-axel).
- `cycleTrendChart` städas i `ngOnDestroy()`.

**HTML — `rebotling-statistik.html`:**
- Pill-toggle "Per dag / Per skift" ovanför OEE-trend-grafen och veckojämförelse-grafen.
- Ny cykeltrend-panel (`*ngIf="cycleTrendLoaded"`) med toggle + canvas `#cycleTrendChart`.
- Ny snabblänksknapp "Cykeltrend" i panelraden.

**CSS — `rebotling-statistik.css`:**
- `.granularity-toggle` + `.gran-btn` — pill-knappar i dark theme, aktiv = `#4299e1` (blå accent).

---

## 2026-03-03 — Produktionskalender + Executive Dashboard alerts — commit cc4ba9f

### Nytt: /rebotling/kalender (GitHub-liknande heatmap-kalender)

**Syfte:** VD vill ha en omedelbar visuell historia av hela årets produktion. GitHub-liknande heatmap med 12 månadsblock ger en snabb överblick av produktionsmönster.

**Backend — `RebotlingController.php`:**
- Ny endpoint: `GET ?action=rebotling&run=year-calendar&year=YYYY`
  - Metod `getYearCalendar()`: hämtar `SUM(ibc_ok)` per datum ur `rebotling_skiftrapport` för valt år.
  - Fallback till PLC-data (`rebotling_ibc`) om inga skiftrapporter finns.
  - Dagsmål hämtas från `rebotling_weekday_goals` (ISO-veckodag 1=Mån...7=Sön) med fallback till `rebotling_settings.rebotling_target`.
  - Helgdagar med `daily_goal=0` men faktisk produktion får defaultGoal som mål.
  - Returnerar: `{ success, year, days: [{ date, ibc, goal, pct }] }`.

**Frontend — `ProductionCalendarPage` (`/rebotling/kalender`, adminGuard):**
- Tre filer: `production-calendar.ts`, `production-calendar.html`, `production-calendar.css`
- Standalone-komponent med `OnInit+OnDestroy`, `destroy$` + `takeUntil`.
- Årsväljare (dropdown + pil-knappar).
- 12 månadsblock i ett 4-kolumners responsivt grid (3 på tablet, 2 på mobil).
- Varje dag = färgad ruta: grå (ingen data), röd (<60%), orange (60-79%), gul (80-94%), grön (>=95%), ljusgrön/superdag (>=110%).
- Hover-tooltip: datum + IBC + mål + %.
- KPI-summering: totalt IBC, snitt IBC/dag, bästa dag + datum, % dagar nådde mål.
- Nav-länk: "Produktionskalender" under Rebotling-dropdown (admin only).
- Route: `rebotling/kalender` skyddad av `adminGuard`.

### Nytt: Alert-sektion i Executive Dashboard (`/oversikt`)

**Syfte:** VD ska inte missa kritiska situationer — tydliga röda/orangea varningsbanners ovanför KPI-korten.

**`executive-dashboard.ts`:**
- Ny property: `alerts: { type, message, detail }[]`
- Ny privat metod `computeAlerts()` anropas efter varje `loadData()`.
- OEE-varningar: danger om oee < 70%, warning om oee < 80%.
- Produktionsvarningar: danger om pct < 60%, warning om pct < 80%.

**`executive-dashboard.html`:**
- Alert-sektion med `*ngFor` ovanför SEKTION 1, döljs om `alerts.length === 0`.
- Klasser `.alert-danger-banner` / `.alert-warning-banner` med ikon och tydlig text.

**`executive-dashboard.css`:**
- Nya stilar: `.alerts-container`, `.alert-banner`, `.alert-danger-banner`, `.alert-warning-banner`, `.alert-icon`, `.alert-text`, `.alert-message`, `.alert-detail`.
- Slide-in animation.

---

## 2026-03-03 — Cykeltids-histogram + SPC-kontrollkort i rebotling-statistik — commit e4ca058

### Nytt: Djupanalys i /rebotling/statistik

**Syfte:** VD och produktionschef vill se djupare analys. Histogram visar om produktionen
är jämn. SPC-kortet visar om IBC/h-processen är statistiskt under kontroll.

**Backend — `RebotlingController.php`:**
- Ny endpoint: `GET ?action=rebotling&run=cycle-histogram&date=YYYY-MM-DD`
  - Metod `getCycleHistogram()`: hämtar `ibc_ok` och `drifttid` per skift från
    `rebotling_skiftrapport`, beräknar cykeltid = drifttid/ibc_ok per skift.
  - Fallback till PLC-data via `TIMESTAMPDIFF(SECOND, LAG(datum), datum)/60` per cykel
    i `rebotling_ibc` om inga skiftrapporter finns för datumet.
  - Histogrambuckets: 0-2, 2-3, 3-4, 4-5, 5-7, 7+ min.
  - Returnerar: `{ buckets[], stats: { n, snitt, p50, p90, p95 } }`.
- Ny endpoint: `GET ?action=rebotling&run=spc&days=7`
  - Metod `getSPC()`: hämtar IBC/h per skift de senaste N dagarna från
    `rebotling_skiftrapport` (ibc_ok * 60 / drifttid).
  - Fallback till PLC-data per skiftraknare (MAX ibc_ok / MAX runtime_plc).
  - Beräknar X̄ (medelvärde), σ (standardavvikelse), UCL=X̄+2σ, LCL=max(0,X̄-2σ).
  - Returnerar: `{ points[], mean, stddev, ucl, lcl, n, days }`.

**Service — `rebotling.service.ts`:**
- Nya interfaces: `CycleHistogramResponse`, `CycleHistogramBucket`, `SPCResponse`, `SPCPoint`.
- Nya metoder: `getCycleHistogram(date)`, `getSPC(days)`.

**Frontend — `rebotling-statistik.ts` + `rebotling-statistik.html`:**
- Histogram-sektion: datumväljare (default idag), KPI-brickor (Antal skift, Snitt, P50, P90),
  Chart.js bar chart (grön `#48bb78`), laddnings- och tom-tillstånd, förklaringstext.
- SPC-sektion: dagar-väljare (3/7/14/30), KPI-brickor (Medelvärde, σ, UCL, LCL),
  Chart.js line chart med 4 dataset (IBC/h blå fylld, UCL röd streckad, LCL orange streckad,
  medelvärde grön streckad), laddnings- och tom-tillstånd, förklaringstext.
- Alla nya properties: `histogramDate`, `histogramLoaded/Loading`, `histogramBuckets`,
  `histogramStats`, `histogramChart`, `spcDays`, `spcLoaded/Loading`, `spcMean/Stddev/UCL/LCL/N`, `spcChart`.
- `ngOnInit()` kallar `loadCycleHistogram()` och `loadSPC()`.
- `ngOnDestroy()` anropar `histogramChart?.destroy()` och `spcChart?.destroy()`.
- `takeUntil(this.destroy$)` på alla subscriptions.

---

## 2026-03-03 — Realtids-tävling TV-skärm (/rebotling/live-ranking) — commit a3d5b49

### Nytt: Live Ranking TV-skärm

**Syfte:** Helskärmsvy för TV/monitor på fabriksgolvet. Operatörer ser sin ranking live
medan de arbetar — motiverar tävlingsanda och håller farten uppe.

**Backend — `RebotlingController.php`:**
- Ny endpoint: `GET ?action=rebotling&run=live-ranking` (ingen auth krävs — fabriksgolvet)
- Metod `getLiveRanking()`: aggregerar op1/op2/op3 via UNION ALL från `rebotling_skiftrapport`
- Joinar mot `operators`-tabellen för namn
- Beräknar IBC/h = `SUM(ibc_ok) / (SUM(drifttid)/60)`, kvalitet% = `SUM(ibc_ok)/SUM(totalt)*100`
- Sorterar på IBC/h DESC, returnerar topp 10
- Fallback: om ingen data idag → senaste 7 dagarna
- Returnerar: `{ success, ranking[], date, period, goal }` där goal = dagsmål från `rebotling_settings`

**Frontend — `src/app/pages/live-ranking/` (3 nya filer):**
- `live-ranking.ts`: standalone component, OnInit+OnDestroy, `destroy$ = new Subject<void>()`,
  polling var 30s med `setInterval` + `isFetching`-guard + `timeout(8000)` + `catchError`.
  Roterande motton (8 st) via `setInterval` 6s. Alla interval rensas i `ngOnDestroy`.
- `live-ranking.html`: TV-layout med pulsande grön dot, header med datum+tid, rankinglista
  (guld/silver/brons-brickor, rank 1-3 framhävda), progress-bars mot dagsmål, roterande motto i footer.
- `live-ranking.css`: full-screen `100vw × 100vh`, dark theme (`#0d1117`/`#1a202c`), neongrön
  accent `#39ff14`, guld/silver/brons-gradienter, CSS-animationer (pulse, spin, fadeIn).

**Routing — `app.routes.ts`:**
- Lagt till som public route (ingen canActivate): `{ path: 'rebotling/live-ranking', loadComponent: ... }`
- URL innehåller `/live` → Layout döljer automatiskt navbar (befintlig logik i layout.ts)

---

## 2026-03-03 — Bug Hunt #2 + Operators-sida ombyggd

### Bug Hunt #2 — Fixade minnesläckor

**angular — takeUntil saknas (subscription-läckor):**
- `audit-log.ts`: `loadLogs()` saknade `takeUntil(destroy$)` → subscription läckte vid navigering
- `audit-log.ts`: `exportCSV()` saknade `takeUntil(destroy$)` → export-anrop kvarstod efter destroy
- `stoppage-log.ts`: `loadReasons()` saknade `takeUntil(destroy$)`
- `stoppage-log.ts`: `loadStoppages()` saknade `takeUntil(destroy$)`
- `stoppage-log.ts`: `loadStats()` saknade `takeUntil(destroy$)`
- `stoppage-log.ts`: `addStoppage()` saknade `takeUntil(destroy$)`
- `stoppage-log.ts`: `deleteStoppage()` saknade `takeUntil(destroy$)`

**Angular — setTimeout utan clearTimeout:**
- `executive-dashboard.ts`: `setTimeout(() => buildBarChart(), 100)` var ej lagrat → `clearTimeout` kallades aldrig i ngOnDestroy. Fixat: ny `barChartTimer` property, clearTimeout i ngOnDestroy, guard `!destroy$.closed`.

### Uppdrag 2 — Operators-sida ombyggd

**Frontend — `operators.ts` (fullständig omskrivning):**
- Operatörskort med initialer-avatar (cirkel med bakgrundsfärg baserad på namn-hash)
- Sorterbar statistiklista på: IBC/h, Kvalitet%, Antal skift, Namn
- Sökfunktion med fritext-filter (namn + nummer)
- Status-badge per operatör: "Aktiv" (jobbat ≤7 dagar), "Nyligen aktiv" (≤30 dagar), "Inaktiv" (>30 dagar), "Aldrig jobbat"
- Detaljvy: klicka på operatörskortet → expanderas med KPI-tiles + trendgraf
- Trendgraf (Chart.js): IBC/h (blå, vänster axel) + Kvalitet% (grön, höger axel) senaste 8 veckorna
- Medaljsystem: guld/silver/brons för rank 1-3
- Statistiken laddas direkt vid sidstart (inte lazy-load bakom knapp)
- Alla Chart.js-instanser destroy():as i ngOnDestroy (map av `trendCharts`)

**Backend — `OperatorController.php`:**
- `getStats()` utökad: lägger till `active`, `all_time_last_shift`, `activity_status` (active/recent/inactive/never)
- Ny endpoint `?run=trend&op_number=N`: veckovis IBC/h + kvalitet% + antal skift senaste 8 veckorna (56 dagar)
- Prepared statements, try/catch, error_log() — konsistent med övrig kod

**Service — `operators.service.ts`:**
- Ny metod `getTrend(opNumber: number)` → `?run=trend&op_number=N`

**CSS — `operators.css` (fullständig omskrivning):**
- Mörkt tema: `#1a202c` bg, `#2d3748` kort, `#e2e8f0` text
- Operatörskort-grid med expanderbar detaljvy
- Sök + sortering-knappar med aktiv-markering
- Responsivt (768px breakpoint)

---

Kort logg över vad som hänt — uppdateras automatiskt av Claude-agenter.

---

## 2026-03-03 — Tvättlinje-förberedelse + UX-polish

### DEL 1 — Tvättlinje-förberedelse

**Tvättlinje-admin (`pages/tvattlinje-admin/`):**
- Ny TypeScript-logik: `timtakt` och `skiftlangd` som egna fält (utöver `antal_per_dag`)
- Ny systemstatus-sektion med 30-sekunders polling (kör/stoppad, senaste signal, databas, linje)
  - `loadSystemStatus(silent?)` med `isFetchingStatus`-guard mot anropsstaplar
  - `getStatusAge()`, `getStatusAgeMinutes()`, `getStatusLevel()` för åldersindikator
- Felmeddelandehantering: `settingsError` visas med `alert-danger`, separeras från success-toast
- Tillbaka-knapp till Live i sidhuvudet
- "Ej i drift"-infobanner förklarar att inställningar kan förberedas
- Info-sektion med relevanta KPI:er och snabblänkar till Statistik / Skiftrapport
- Fullständigt omskriven CSS i mörkt tema (`#1a202c`/`#2d3748`/`#e2e8f0`), konsistent med rebotling-admin

**TvattlinjeController.php:**
- `saveAdminSettings()` hanterar nu `timtakt` och `skiftlangd` (utöver `antal_per_dag`)
- `loadSettings()` returnerar `timtakt` och `skiftlangd` med standardvärden 20 resp. 8.0
- Idempotent `ALTER TABLE ADD COLUMN IF NOT EXISTS` i både load och save — inga migrations-beroenden

**Databas-migration:**
- `noreko-backend/migrations/2026-03-03_tvattlinje_settings_extend.sql`
  - `ALTER TABLE tvattlinje_settings ADD COLUMN IF NOT EXISTS timtakt INT DEFAULT 20`
  - `ALTER TABLE tvattlinje_settings ADD COLUMN IF NOT EXISTS skiftlangd DECIMAL(4,1) DEFAULT 8.0`

**Tvättlinje-statistik (`pages/tvattlinje-statistik/`):**
- "Ej i drift"-banner (orange/gul) visas när backend returnerar fel och mock-data visas
- Förbättrad felmeddelande-alert: `alert-info` med "Exempeldata visas"
- Tillbaka-knapp till Live integrerad i navigationskontrollen
- `DecimalPipe` importerad — `avgEfficiency` och `row.efficiency` visas med 1 decimal

**Tvättlinje-skiftrapport (`pages/tvattlinje-skiftrapport/`):**
- Sammanfattningskort överst: Skift totalt, Totalt OK, Totalt ej OK, Snitt kvalitet (1 decimal)
  - `getTotalOk()`, `getTotalEjOk()`, `getAvgQuality()` — nya metoder
- Tillbaka-knapp till Live i sidhuvudet
- Tom-tillstånd med ikon (`fa-clipboard`) + förklaringstext + knapp för manuell rapport
- `getQualityPct()` returnerar nu 1 decimal (0.1% precision)
- Friendlier HTTP-felmeddelande med stäng-knapp på alert

### DEL 2 — UX-polish (tvättlinje)

- **Tillbaka-knappar**: Alla tre tvättlinje-sidor (statistik, skiftrapport, admin) har tillbaka-knapp till `/tvattlinje/live`
- **Tomma tillstånd**: Skiftrapport — dedikerat tom-tillstånd med ikon utanför tabellen
- **Felmeddelanden**: HTTP-fel ger begriplig svensk text; alert har stäng-knapp
- **Datumformat**: `yyyy-MM-dd` konsekvent via DatePipe
- **Procentsiffror**: 1 decimal konsekvent (`| number:'1.1-1'`) i statistik-KPIs, skiftrapport-kort och kvalitet-badges
- **Build**: `npx ng build` — 0 TypeScript-fel, inga nya budgetvarningar

---

## 2026-03-03 — Audit-log & Stoppage-log förbättringar

### Audit-log förbättringar

**Filtrering (server-side):**
- Fritext-sökning i `action`, `user`, `description`, `entity_type` via ny `?search=`-parameter med 350ms debounce
- Datumintervall-filter: knapp togglar "anpassat intervall" med from/to date-inputs (`?from_date=` + `?to_date=`)
- Period-dropdown inaktiveras när datumintervall är aktivt
- Åtgärds-dropdown fylls dynamiskt från ny `?run=actions` endpoint (unika actions från databasen)

**Presentation:**
- Färgkodade action-badges (pill-style): login/logout=grå, create/register=grön, update/toggle/set/approve=blå, delete/bulk_delete=röd, login_failed=orange
- Entitetstyp + ID visas i grå monospace bredvid badgen
- Förbättrad paginering med sifferknappar och ellipsis
- Strukturerad filterrad med labels och gruppering

**Export:**
- CSV-export hämtar upp till 2000 poster för aktiv filtrering (inte bara nuvarande sida)

**Backend (AuditController.php):**
- `getLogs()`: ny `search` (4-kolumns LIKE), `from_date`/`to_date`, `period=custom`
- Ny `getActions()`: returnerar distinkta actions
- `getDateFilter()`: stöder `custom`

**Frontend (audit.service.ts):** `search`, `from_date`, `to_date` + `getActions()`

### Stoppage-log förbättringar

**KPIer:**
- Snitt stopplängd ersätter "Planerade stopp" i fjärde kortet
- Veckosummering-rad: antal stopp + total stopptid denna vecka vs förra veckan med diff-%

**14-dagars bar-chart:**
- Inline chart (130px) bredvid veckokorten, antal stopp/dag, nolldagar i grå
- Tooltip visar stopptid i minuter

**Backend (StoppageController.php):**
- Ny `getWeeklySummary()` (`?run=weekly_summary&line=`): this_week, prev_week, daily_14

**Frontend (stoppage.service.ts):** Interface `StoppageWeeklySummary` + `getWeeklySummary(line)`

---

## 2026-03-03 — Skiftjämförelse + PLC-varningsbanner

### DEL 1 — Skiftjämförelse (rebotling-skiftrapport)

**Backend (`RebotlingController.php`):**
- Ny GET-endpoint `?action=rebotling&run=shift-compare&date_a=YYYY-MM-DD&date_b=YYYY-MM-DD`
- Metod `getShiftCompare()`: validerar datumformat med regex, hämtar aggregerad data per datum från `rebotling_skiftrapport`
- Returnerar per datum: totalt, ibc_ok, bur_ej_ok, ibc_ej_ok, kvalitet%, OEE%, drifttid, rasttid, ibc_per_h samt operatörslista med individuella IBC/h och kvalitet%

**Frontend (`rebotling-skiftrapport.ts`):**
- Properties: `compareDateA`, `compareDateB`, `compareLoading`, `compareError`, `compareResult`
- Metoder: `compareShifts()` (HTTP GET + felhantering), `clearCompare()`, `compareDiff()`, `compareIsImprovement()`, `compareIsWorse()`, `formatMinutes()`

**Frontend (`rebotling-skiftrapport.html`):**
- Ny sektion "Jämför skift" längst ner på sidan
- Två datumväljare + "Jämför"-knapp
- 6 KPI-kort (Total IBC, Kvalitet%, OEE%, Drifttid, Rasttid, IBC/h) med sida-vid-sida-layout
- Diff-badge: grön (förbättring) / röd (försämring) — rasttid är inverterad (lägre = bättre)
- Operatörstabeller för respektive datum (user_name, IBC/h, kvalitet%, op1/2/3-namn)
- Varningsmeddelanden om data saknas för ett/båda datum

**CSS (`rebotling-skiftrapport.css`):**
- `.compare-kpi-card`, `.compare-day-block`, `.compare-diff-block`, `.compare-diff`
- `.compare-better` (grön), `.compare-worse` (röd), `.compare-equal` (grå)
- `.compare-op-card`, `.compare-op-header`

---

### DEL 2 — PLC-varningsbanner (rebotling-admin)

**Frontend (`rebotling-admin.ts`):**
- Getter `plcWarningLevel`: returnerar `'none'` (< 5 min), `'warn'` (5–15 min), `'danger'` (> 15 min)
- Getter `plcMinutesOld`: beräknar antal minuter sedan senaste PLC-ping
- Använder befintlig `systemStatus.last_plc_ping` och existerande 30s polling

**Frontend (`rebotling-admin.html`):**
- Röd `alert-danger`-banner vid `plcWarningLevel === 'danger'`: "PLC har inte rapporterat data på X minuter. Kontrollera produktionslinjen!"
- Gul `alert-warning`-banner vid `plcWarningLevel === 'warn'`: "PLC-data är X min gammal"
- Ingen banner vid `'none'` (allt OK)
- Banner visas bara när `systemStatus` är laddat (undviker false positives under initial laddning)

**CSS (`rebotling-admin.css`):**
- `.plc-warning-banner` med subtil `plc-blink`-animation (opacity-pulsering)

---

## 2026-03-03 — Heatmap förbättring + My-bonus mobilanpassning

### Rebotling-statistik — förbättrad heatmap

**Interaktiva tooltips:**
- Hover över en heatmap-cell visar tooltip: Datum, Timme, IBC denna timme, IBC/h (takt), Kvalitet% om tillgänglig
- Tooltip positioneras ovanför cellen relativt `.heatmap-container`, fungerar med horisontell scroll

**KPI-toggle:**
- Dropdown-knappar ovanför heatmappen: "IBC/h" | "Kvalitet%" | "OEE%"
- IBC/h: vit→mörkblå; Kvalitet%: vit→mörkgrön; OEE%: vit→mörkviolett
- Kvalitet% visas på dagsnivå med tydlig etikett om timdata saknas

**Förbättrad färgskala & legend:**
- Noll-celler: mörk grå (`#2a2a3a`) istället för transparent
- Legend: noll-ruta + gradient "Låg → Hög" med siffror, uppdateras per KPI

**TypeScript ändringar (`rebotling-statistik.ts`):**
- `heatmapKpi: 'ibc' | 'quality' | 'oee'`
- `heatmapRows.qualityPct: number[]` tillagt
- `getHeatmapColor(rowIndex, hourIndex)` — ny signatur med rgb-interpolation per KPI
- `showHeatmapTooltip` / `hideHeatmapTooltip` metoder

### My-bonus — mobilanpassning för surfplatta

**CSS (`my-bonus.css`):**
- `overflow-x: hidden` — ingen horisontell overflow
- `@media (max-width: 768px)`: kort staplas vertikalt, hero kolumnar
- Lagerjämförelse → 1 kolumn på mobil (ersätter 600px-breakpoint)
- Touch-targets: `.period-group button` och `.btn-sm` → `min-height: 44px`
- `font-size: 14px` body, `1.25rem` rubrik
- `chart-container: 200px` höjd på mobil
- `@media (max-width: 480px)`: ytterligare komprimering
- Håller sig inom Angular 12kB CSS-budget

---

## 2026-03-03 — Bug Hunting Session (commit `92cbcb1`)

### Angular — Minnesläckor fixade
- `bonus-dashboard.ts`: `loadWeeklyGoal()`, `getDailySummary()`, `loadPrevPeriodRanking()` saknades `takeUntil(destroy$)`
- `bonus-dashboard.ts`: `loadData()` i setInterval-callback körde utan `destroy$.closed`-check
- `my-bonus.ts`: Alla tre HTTP-anrop i `loadStats()` saknade `timeout(8000)` + `catchError` + `takeUntil`
- `my-bonus.ts`: Borttagna oanvända imports (`KPIDetailsResponse`, `OperatorStatsResponse`, `OperatorHistoryResponse`)

### Angular — Race conditions fixade
- `rebotling-admin.ts`: `loadSystemStatus()` fick `isFetching`-guard → förhindrar anropsstaplar under 30s polling

### Angular — Logikbugg fixad
- `production-analysis.ts`: `catchError` i `getRastStatus` satte `stopAnalysisLoading=false` för tidigt medan övriga anrop pågick

### PHP — Säkerhet/korrekthet
- `BonusController.php`: `sendError()` satte nu `http_response_code($code)` — returnade tidigare alltid HTTP 200
- `BonusAdminController.php`: Deprecated `FILTER_SANITIZE_STRING` (borttagen PHP 8.2) ersatt med `strip_tags()`

---

## 2026-03-03

### Operatörsprestanda-trend + Stopporsaksanalys

**My-Bonus (`pages/my-bonus/`):**
- Ny sektion "Min bonusutveckling" (visas under IBC/h-trenden)
- Veckoutvecklings-graf: Stapeldiagram bonuspoäng per ISO-vecka, senaste 8 veckorna
  - Referenslinje: streckad gul horisontell linje = operatörens eget snitt
  - Färgkodning per stapel: grön = över eget snitt, röd/orange = under
  - Tooltip: diff mot snitt + antal skift den veckan
- Jämförelse mot laget (tre kolumner): IBC/h, Kvalitet%, Bonuspoäng — jag vs lagsnitt med grön/röd diff-pill
- `weeklyLoading` spinner; rensas vid `clearOperator()`

**BonusController.php:**
- Ny endpoint `GET ?action=bonus&run=weekly_history&id=<op_id>`
  - Bonuspoäng (snitt per skift) per ISO-vecka senaste 8 veckorna
  - Korrekt MAX/SUBSTRING_INDEX-aggregering för kumulativa PLC-fält
  - Teamsnitt per vecka (bonus, IBC/h, kvalitet) för lagsjämförelse
  - `my_avg` returneras för referenslinjen

**bonus.service.ts:**
- Ny `getWeeklyHistory(operatorId)` metod
- Nya interfaces: `WeeklyHistoryEntry`, `WeeklyHistoryResponse`

**Production Analysis (`pages/production-analysis/`) — ny flik "Stoppanalys" (flik 6):**
- Tydlig notis om datakälla: rast-data som proxy, riktig stoppanalys kräver PLC-integration
- KPI-kort idag: Status (kör/rast), Rasttid (min), Antal raster, Körtid est.
- Stopp-tidslinje 06:00–22:00: grön=kör, gul=rast/stopp, byggs från rast-events
  - Summering: X min kört, Y min rast/stopp, antal stopp
  - Fallback-meddelande om inga rast-events registrerats
- Bar chart "Rasttid per dag senaste 14 dagarna" (estimerad: 8h skift – körtid)
- Stoppstatistik-tiles: raster idag, rasttid idag, dagar med data, senaste rast-event
- Hämtar: `?run=rast` + `?run=status` + `?run=statistics`
- `stopRastChart` rensas i `destroyAllCharts()`

---

### Executive Dashboard — Fullständig VD-vy (commit fb05cce)

**Mål:** VD öppnar sidan och ser på 10 sekunder om produktionen går bra eller dåligt.

**Sektion 1 — Idag (stor status-panel):**
- Färgkodad ram (grön >80% av mål, gul 60–80%, röd <60%) med SVG-cirkulär progress
- Stor IBC-räknare "142 / 200 IBC" med procent inuti cirkeln
- Prognos-rad: "Prognos: 178 IBC vid skiftslut" (takt beräknad sedan skiftstart)
- OEE idag som stor siffra med trend-pil vs igår

**Sektion 2 — Veckans status (4 KPI-kort):**
- Denna veckas totala IBC vs förra veckans (diff i %)
- Genomsnittlig kvalitet% denna vecka
- Genomsnittlig OEE denna vecka
- Bästa operatör (namn + IBC/h)

**Sektion 3 — Senaste 7 dagarna (bar chart):**
- IBC per dag senaste 7 dagarna (grön = over mål, röd = under mål)
- Dagsmål som horisontell referenslinje (Chart.js line dataset)
- Mini-tabell under grafen med datum och IBC per dag

**Sektion 4 — Aktiva operatörer senaste skiftet:**
- Lista operatörer: namn, position, IBC/h, kvalitet%, bonusestimering
- Hämtas live från rebotling_ibc för senaste skiftraknare

**Backend (RebotlingController.php):**
- `GET ?run=exec-dashboard` — ny samlad endpoint, returnerar alla 4 sektioners data i ett anrop:
  - `today`: ibc, target, pct, forecast, oee_today, oee_yesterday, rate_per_h, shift_start
  - `week`: this_week_ibc, prev_week_ibc, week_diff_pct, quality_pct, oee_pct, best_operator
  - `days7`: array med 7 dagars {date, ibc, target}
  - `last_shift_operators`: array med {id, name, position, ibc_h, kvalitet, bonus}
- Korrekt OEE-beräkning (MAX per skiftraknare → SUM) för idag och igår
- Prognos beräknad som: nuvarande IBC / minuter sedan skiftstart × resterande minuter

**Frontend:**
- `ExecDashboardResponse` interface i `rebotling.service.ts` + ny `getExecDashboard()` metod
- Komplett omskrivning av executive-dashboard.ts/.html/.css
- Polling var 30:e sekund med isFetching-guard (ingen dubbelförfrågan)
- `implements OnInit, OnDestroy` + `destroy$` + `clearInterval` i ngOnDestroy
- SVG-cirkel med smooth CSS-transition på stroke-dashoffset
- Chart.js bar chart med dynamiska färger (grön/röd per dag)
- All UI-text på svenska

---

### Rebotling-skiftrapport + Admin förbättringar (commit cbfc3d4)

**Rebotling-skiftrapport (`pages/rebotling-skiftrapport/`):**
- Sammanfattningskort överst: Total IBC, Kvalitet%, OEE-snitt, Drifttid, Rasttid, Vs. föregående
- Filtrera per skift (förmiddag 06-14 / eftermiddag 14-22 / natt 22-06) utöver datumfilter
- Textsökning på produkt och användare direkt i filterraden
- Sorterbar tabell — klicka på kolumnrubrik för att sortera (datum, produkt, användare, IBC-antal, kvalitet%, IBC/h)
- Kvalitet%-badge med färgkodning (grön/gul/röd) direkt i tabellraden
- Skiftsammanfattning i expanderad detaljvy: snitt cykeltid, drifttid, rasttid, bonus-estimat
- PDF-export inkluderar nu sammanfattningskort med dagsmål-uppfyllnad och bonus-estimat
- Excel-export inkluderar separat sammanfattningsflik med periodnyckeltal

**Rebotling-admin (`pages/rebotling-admin/`):**
- Systemstatus-sektion (live, uppdateras var 30:e sek): senaste PLC-ping med åldersindikator, aktuellt löpnummer, DB-status OK/FEL, IBC idag
- Veckodagsmål: sätt olika IBC-mål per veckodag (standardvärden lägre mån/fre, noll helg)
- Skifttider: konfigurera start/sluttid + aktiv/inaktiv för förmiddag/eftermiddag/natt
- Bonussektion med förklarande estimatformel och länk till bonus-admin

**Backend (RebotlingController.php):**
- `GET/POST ?run=weekday-goals` — hämta/spara veckodagsmål (auto-skapar tabell)
- `GET/POST ?run=shift-times` — hämta/spara skifttider (auto-skapar tabell)
- `GET ?run=system-status` — returnerar PLC-ping, löpnummer, DB-check, IBC-idag, servertid
- POST-hantering samlad med admin-kontroll i en IF-block

**Databas-migration:**
- `noreko-backend/migrations/2026-03-03_rebotling_settings_weekday_goals.sql`
  - `rebotling_weekday_goals` (weekday 1-7, daily_goal, label)
  - `rebotling_shift_times` (shift_name, start_time, end_time, enabled)
  - Standardvärden ifyllda

---

### Rebotling-statistik + Production Analysis förbättringar (commit c7faa1b)

**Rebotling-statistik (rebotling-statistik.ts/.html):**
- Veckojämförelse-panel: Bar chart denna vecka vs förra veckan (IBC/dag), summakort, diff i %
- Skiftmålsprediktor: Prognos för slutet av dagen baserat på nuvarande takt. Hämtar dagsmål från live-stats, visar progress-bar med färgkodning
- OEE Deep-dive: Breakdown Tillgänglighet/Prestanda/Kvalitet som tre separata progress bars (med detaljtext), + 30-dagars OEE-trendgraf
- Alla tre paneler laddas on-demand med egna knappar (lazy load)

**Backend (RebotlingController.php):**
- `?run=week-comparison`: Returnerar IBC/dag för denna vecka + förra veckan (14 dagar, korrekt MAX/SUM-aggregering)
- `?run=oee-trend&days=N`: OEE per dag (Availability, Performance, Quality, OEE) senaste N dagar
- `?run=best-shifts&limit=N`: Historiskt bästa skift sorterade på ibc_ok DESC

**Production Analysis (production-analysis.ts/.html):**
- Ny flik "Bästa skift": historisk topplista med bar+line chart (IBC OK + kvalitet%), detailtabell med medals för topp-3
- Limit-selector (5/10/20/50 skift)

**RebotlingService:** Tre nya metoder (getWeekComparison, getOEETrend, getBestShifts) + type interfaces

### Auth-fix (commit ecc6b40)
- `fetchStatus()` returnerar nu `Observable<void>` istället för void
- `APP_INITIALIZER` använder `firstValueFrom(auth.fetchStatus())` — Angular väntar på HTTP-svar innan routing startar
- `catchError` returnerar `null` istället för `{ loggedIn: false }` — transienta fel loggar inte ut användaren
- `StatusController.php`: `session_start(['read_and_close'])` — PHP-session-låset släpps direkt, hindrar blockering vid sidomladdning

### Bonussystem — förbättringar (commit 9ee9d57)

**My-Bonus (`pages/my-bonus/`):**
- Motiverande statusbricka ("Rekordnivå!", "Över genomsnitt!", "Uppåt mot toppen!", etc.)
- IBC/h-trendgraf för senaste 7 skiften med glidande snitt (3-punkts rullande medelvärde)
- Skiftprognos-banner: förväntad bonus, IBC/h och IBC/vecka (5 skift) baserat på senaste 7 skiften
- PDF-export inkluderar nu skiftprognos i rapporten

**Bonus-Dashboard (`pages/bonus-dashboard/`):**
- Trendpilar (↑/↓/→) per operatör i rankingtabellen, jämfört med föregående period
- Bonusprogressionssbar för teamet mot konfigurerbart veckobonusmål
- Kvalitet%-KPI-kort ersätter Max Bonus (kvalitet visas tydligare)
- Mål-kolumn i rankingtabellen med mini-progressbar per operatör

**Bonus-Admin (`pages/bonus-admin/`):**
- Ny flik "Prognos": sök operatör, se snittbonus, tier-multiplikator, IBC/h och % av veckobonusmål
- Ny sektion i "Mål"-fliken: konfigurera veckobonusmål (1–200 poäng) med tiernamn-preview
- Visuell progressbar visar var valt mål befinner sig på tierskalan

**Backend (`BonusAdminController.php`):**
- `POST ?run=set_weekly_goal` — sparar weekly_bonus_goal i bonus_config (validerat 0–200)
- `GET ?run=operator_forecast&id=<op_id>` — prognos baserat på per-skift-aggregering senaste 7 dagar

**BonusAdminService (TypeScript):**
- `setWeeklyGoal(weeklyGoal)` — ny metod
- `getOperatorForecast(operatorId)` — ny metod med `OperatorForecastResponse` interface

**Databas-migration:**
- `2026-03-03_bonus_weekly_goal.sql`: ALTER TABLE bonus_config ADD weekly_bonus_goal DECIMAL(6,2) DEFAULT 80

---

---

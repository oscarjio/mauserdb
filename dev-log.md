## 2026-03-04 session #6 — Lead: Kodbasanalys + 3 workers startade

**Analys**: Session #5 komplett (6 features, 4 bugfixar). Backlog var nere i 2 items. Kodbasanalys (15 fynd) + MES-research (7 idéer) genererade 12 nya items. Startade 3 workers: Bug Hunt #14 (felhantering), Exec Dashboard (underhållskostnad+stämning), Users Admin UX.

**Worker: Bug Hunt #14** — LoginController.php try-catch (PDOException → HTTP 500), operators.ts timeout(8000)+catchError på 7 anrop, stoppage-log.ts 350ms debounce med onSearchInput(), rebotling-skiftrapport.ts 350ms debounce, URL-typo overlamnin→overlamning i routes+menu. OperatorCompareController redan korrekt. Bygge OK.

**Worker: Executive Dashboard underhållskostnad+stämning** — 3 underhålls-KPI-kort (kostnad SEK 30d, händelser, stopptid h:mm) från MaintenanceController run=stats. Teamstämning: emoji-KPI + 30d trendgraf (Chart.js). getMaintenanceStats()+getFeedbackSummary() i service. Bygge OK.

**Worker: Users Admin UX** — Sökfält 350ms debounce, sorterbar tabell (4 kolumner), statusfilter-pills (Alla/Aktiva/Admin/Inaktiva), statistik-rad. Dark theme, responsive. Bygge OK.

**Worker: RebotlingController catch-block audit** — 47 av 142 catch-block fixade med http_response_code(500) före echo json_encode. 35 redan korrekta, 60 utan echo (inre try/catch, return-only). PHP syntax OK.

**Worker: Admin polling-optimering** — visibilitychange-guard på 4 admin-sidor (rebotling/saglinje/tvattlinje/klassificeringslinje). systemStatus 30s→120s, todaySnapshot 30s→300s. Andon: countdownInterval mergad in i clockInterval (7→6 timers), polling-timers pausas vid dold tabb. Bygge OK.

---

**Worker: Skiftbyte-PDF automatgenerering** — Print-optimerad skiftsammanfattning som oppnas i nytt fonster. Backend: nytt endpoint `shift-pdf-summary` i RebotlingController.php som returnerar fullt HTML-dokument med A4-format, print-CSS, 6 KPI-kort (IBC OK, Kvalitet%, OEE, Drifttid, Rasttid, IBC/h), operatorstabell med per-rapport-rader (tid, produkt, IBC OK/ej OK, operatorer), skiftkommentar om tillganglig. Operatorer och produkter visas som badges. Knapp "Skriv ut / Spara PDF" for webblasarens print-dialog. Frontend skiftrapport: ny knapp (fa-file-export) per skiftrapport-rad som oppnar backend-HTML i nytt fonster via window.open(). Frontend andon: skiftbyte-detektion i polling — nar `status.skift` andras visas en notis "Skiftbyte genomfort — Skiftsammanfattning tillganglig" med lank till skiftrapporten, auto-dismiss efter 30s. Service: `getShiftPdfSummaryUrl()` i rebotling.service.ts. CSS: slideInRight-animation for notisen. Prepared statements, takeUntil(destroy$), timeout(8000)+catchError(). Bygge OK.

**Worker: Bonus What-if simulator förbättring** — Utökad What-if bonussimulator i bonus-admin med tre nya sub-flikar. (1) Preset-scenarios: snabbknappar "Aggressiv bonus", "Balanserad", "Kostnadssnål" som fyller i tier-parametrar med ett klick. (2) Scenario-jämförelse: sida-vid-sida-konfiguration av nuvarande vs nytt förslag, kör dubbla simuleringar mot backend, visar totalkostnads-diff-kort med färgkodning (grön=besparing, röd=ökning), halvcirkel-gauge för kostnadspåverkan i procent, och diff per operatör i tabell med tier-jämförelse och kronor-diff. (3) Historisk simulering: välj period (förra månaden, 2 mån sedan, senaste 3 mån), beräkna "om dessa regler hade gällt" med CSS-baserade horisontella stapeldiagram per operatör (baslinje vs simulerad) med diff-kolumn. Visuella förbättringar: animerade siffror via CSS transition, färgkodade diff-indikatorer (sim-diff-positive/negative). Inga backend-ändringar — återanvänder befintligt simulate-endpoint i BonusController. Dark theme, takeUntil(destroy$), timeout(8000)+catchError() på alla HTTP-anrop. Bygge OK.

**Worker: Live-ranking admin-konfiguration** — Admin-gränssnitt för att konfigurera vilka KPI:er som visas på TV-skärmen (`/rebotling/live-ranking`). Backend: 2 nya endpoints i RebotlingController.php (`live-ranking-config` GET, `set-live-ranking-config` POST admin-only) som lagrar JSON-config i `rebotling_settings` med nyckel `live_ranking_config`. DB-migration med default-config. Frontend admin: ny expanderbar sektion "TV-skärm (Live Ranking) — KPI-kolumner" med checkboxar (IBC/h, Kvalitet%, Bonus-nivå, Dagsmål-progress, IBC idag), dropdown sortering (IBC/h, Kvalitet%, IBC totalt), number input refresh-intervall (10-120s), spara-knapp. Frontend live-ranking: hämtar config vid init, visar/döljer kolumner baserat på config, sorterar ranking efter konfigurerat fält, använder konfigurerat refresh-intervall. Service-metoder tillagda i rebotling.service.ts. Dark theme, prepared statements, auth-check, takeUntil(destroy$)+timeout(8000)+catchError(). Bygge OK.

**Worker: IBC-kvalitets deep-dive** — Ny sektion "IBC Kvalitetsanalys" i rebotling-statistik. Backend: nytt endpoint `rejection-analysis` i RebotlingController.php som returnerar daglig kvalitets%, glidande 7-dagars snitt, KPI:er (kvalitet idag/vecka, kasserade idag, trend vs förra veckan) samt Pareto-data med trendjämförelse mot föregående period. Frontend: 4 KPI-kort (kvalitet% idag, vecka glidande, kasserade idag, trend-pil), kvalitetstrend-linjegraf (Chart.js) med referenslinjer vid 95% mål och 90% minimum, kassationsfördelning Pareto-diagram med horisontella staplar + kumulativ linje + detajtabell med trend-pilar, periodväljare 14/30/90 dagar, CSV-export. Fallback-meddelande om PLC-integration saknas. Dark theme, takeUntil(destroy$), timeout(8000)+catchError(), try-catch runt chart.destroy(). Bygge OK.

**Worker: Prediktivt underhåll körningsbaserat** — Serviceintervall-system baserat på IBC-volym. Backend: 3 nya endpoints i MaintenanceController (service-intervals GET, set-service-interval POST, reset-service-counter POST) med prepared statements. Ny tabell service_intervals med default-rad. Frontend: ny flik "Serviceintervall" i underhållsloggen med tabell (maskin/intervall/IBC sedan service/kvar/status-badge), progress-bar per rad, admin-knappar (registrera utförd service, redigera intervall via modal). Status-badges: grön >25%, gul 10-25%, röd <10%. Varning-banner överst vid kritisk. Exec-dashboard: service-varnings-banner om maskin <25% kvar. Bygge OK.

## 2026-03-04 Bug Hunt #13 — session #4 granskning

**Granskade filer (session #4 commits `7996e1f`, `f0a57ba`, `d0b8279`, `0795512`):**
- `noreko-frontend/src/app/pages/benchmarking/benchmarking.ts` + `.html` + `.css` — OK
- `noreko-frontend/src/app/pages/shift-plan/shift-plan.ts` + `.html` + `.css` — OK
- `noreko-frontend/src/app/pages/rebotling-admin/rebotling-admin.ts` + `.html` — OK
- `noreko-frontend/src/app/pages/rebotling-skiftrapport/rebotling-skiftrapport.ts` + `.html` — OK
- `noreko-frontend/src/app/pages/my-bonus/my-bonus.ts` — OK
- `noreko-frontend/src/app/services/rebotling.service.ts` — OK
- `noreko-backend/classes/ShiftPlanController.php` — OK
- `noreko-backend/classes/BonusController.php` (ranking-position) — OK

**Buggar hittade och fixade:**
1. **RebotlingController.php `getPersonalBests()`** — Saknade `http_response_code(500)` i catch-block. Fixat.
2. **RebotlingController.php `getHallOfFameDays()`** — Saknade `http_response_code(500)` i catch-block. Fixat.
3. **RebotlingController.php `getMonthlyLeaders()`** — Saknade `http_response_code(500)` i catch-block. Fixat.
4. **RebotlingController.php `getPersonalBests()` best_day_date subquery** — Ogiltig nästlad aggregat `SUM(COALESCE(MAX(...),0))` som kraschar i MySQL. Omskriven med korrekt tvåstegs-aggregering (MAX per skift, sedan SUM per dag).

**Inga buggar i:**
- Alla Angular/TS-filer: korrekt `takeUntil(destroy$)`, `timeout()`, `catchError()`, `clearInterval`/`clearTimeout` i ngOnDestroy, try-catch runt chart.destroy(), inga saknade optional chaining.
- ShiftPlanController.php: korrekt auth-checks, prepared statements, input-validering.
- BonusController.php: korrekt session-check, `sendError()` med `http_response_code()`.

---

## 2026-03-04 session #5 — Lead: 3 workers startade

**Analys**: Session #4 batch 2 komplett (Skiftplaneringsvy `f0a57ba` + Benchmarking `7996e1f`). Backlogen tunnades — fyllde på med nya items.

**Startade 3 workers:**
1. **Prediktivt underhåll körningsbaserat** — serviceintervall baserat på IBC-volym, admin-UI, exec-dashboard varning
2. **IBC-kvalitets deep-dive** — kvalitetstrend-graf, kassationsanalys, KPI-kort i rebotling-statistik
3. **Bug Hunt #13** — granskning av session #4 features (benchmarking, skiftplan, auto-rapport, kollegjämförelse)

---

**Worker: Benchmarking förbättring** — Tre nya flikar (Översikt/Personbästa/Hall of Fame). Personbästa-flik: per operatör bästa dag/vecka/månad IBC + teamrekord-jämförelse sida vid sida. Hall of Fame: topp 5 bästa enskilda produktionsdagar med guld/silver/brons-ikoner, operatörsnamn, kvalitet. Backend: utökad `personal-bests` endpoint med dag/vecka/månad per operatör + teamrekord dag/vecka/månad; ny `hall-of-fame` endpoint (topp 5 dagar). Bygge OK.

**Worker: Skiftplaneringsvy förbättring** — Veckoöversikt-panel överst i veckoplan-fliken: visar antal operatörer per skift per dag med bemanningsgrad (grön/gul/röd). Kopiera förra veckans schema-knapp (POST `copy-week` endpoint, admin-only). ISO-veckonummer + pilnavigering (redan befintligt, behålls). Backend: ny `copyWeek()`-metod i ShiftPlanController.php med prepared statements. Bygge OK.

**Worker: Automatisk skiftrapport via email** — Ny POST endpoint `auto-shift-report` i RebotlingController som bygger HTML-rapport med KPI:er (IBC OK, kvalitet, IBC/h) och skickar via mail() till konfigurerade mottagare. Admin-panel: ny sektion "Automatisk skiftrapport" med datum/skift-väljare + testknappp. Skiftrapport-vy: "Skicka skiftrapport"-knapp (admin-only) med bekräftelsedialog. Använder befintlig notification_emails-kolumn. Bygge OK.

**Worker: Min bonus kollegjämförelse** — Utökade ranking-position endpoint med percentil (Topp X%) och trend (upp/ner/samma vs förra veckan). Lade till RankingPositionResponse-interface + service-metod i BonusService. Uppdaterade my-bonus HTML med percentil-badge, trendpil och motiverande meddelanden (#1="Du leder! Fortsätt så!", #2-3="Nära toppen!", #4+="Känn motivationen växa!"). Dark theme CSS. Bygge OK.

**Worker: Stub-katalog cleanup** — Tog bort oanvända stub-filer: pages/tvattlinje/ (hela katalogen) + pages/rebotling/rebotling-live.* och rebotling-skiftrapport.* (stubs). Behöll pages/rebotling/rebotling-statistik.* som används av routing. Bygge OK.

## 2026-03-04 session #4 — Lead: Ny batch — 3 workers

**Analys**: Exec dashboard multi-linje, bonus utbetalningshistorik, halvfärdiga features — alla redan implementerade (verifierat).

**Omplanering**: Starta 3 workers på genuint öppna items:
1. **Stub-katalog cleanup** — Ta bort gamla/oanvända stub-filer ✅ `a1c17f4`
2. **Min bonus: Jämförelse med kollegor** — Anonymiserad ranking ✅ `0795512`
3. **Automatisk skiftrapport-export** — POST-endpoint ✅ `d0b8279`

**Batch 2**: 2 nya workers startade:
4. **Skiftplaneringsvy förbättring** — veckoöversikt, bemanningsgrad, kopiera schema
5. **Benchmarking förbättring** — personbästa, hall of fame, team-rekord

---

## 2026-03-04 kväll #13 — Worker: Loading-states + Chart.js tooltip-förbättring

### DEL 1: Loading-state spinners (konsistent spinner-border mönster)

3 sidor uppgraderade till konsistent `spinner-border text-info` mönster:

1. **rebotling-prognos** — ersatt enkel text "Laddar produktionstakt..." med spinner-border + text
2. **certifications** — ersatt `fa-spinner fa-spin` med spinner-border + text
3. **operator-attendance** — uppgraderat båda panelernas (kalender + statistik) spinners till spinner-border

Notering: production-calendar och benchmarking hade redan korrekt spinner-mönster.

### DEL 2: Chart.js tooltip-förbättringar (3 sidor, 6 grafer)

1. **audit-log** `buildActivityChart()`:
   - Custom tooltip med dag+datum (t.ex. "Mån 2026-03-04")
   - Formaterat antal ("3 aktiviteter" istf bara siffra)
   - Dark theme tooltip-styling (#2d3748 bg)

2. **production-calendar** `buildDayDetailChart()`:
   - Datumtitel i tooltip (t.ex. "Tisdag 4 Mars 2026")
   - Dagsmål visas i tooltip ("Dagsmål: 120 IBC")

3. **stoppage-log** (4 grafer förbättrade):
   - `buildParetoChart()`: h:mm tidsformat, andel%, antal stopp
   - `buildDailyChart()`: h:mm stopptid-format per dataset
   - `buildWeekly14Chart()`: h:mm stopptid i afterLabel
   - `buildHourlyChart()`: tidsintervall i titel (Kl 08:00–08:59), snitt varaktighet i h:mm, peak-markering

Alla tooltips har konsistent dark theme-styling (bg #2d3748, text #e2e8f0/#a0aec0, border #4a5568).

## 2026-03-04 kväll #12 — Worker: Empty-states batch 2 — 6 sidor med "Inga data"-meddelanden

Lade till konsistenta empty-state meddelanden (inbox-ikon + svensk text, dark theme-stil) på ytterligare 6 sidor:

1. **my-bonus** — "Ingen veckodata tillgänglig." när weeklyData tom, "Ingen feedbackhistorik ännu." när feedbackHistory tom
2. **operator-detail** — "Ingen operatörsdata hittades." när profil saknas (ej laddning/felmeddelande)
3. **saglinje-admin** — "Inga inställningar tillgängliga." med batch 1-mönster (ersatte enkel textrad)
4. **tvattlinje-admin** — "Inga inställningar tillgängliga." med batch 1-mönster (ersatte enkel textrad)
5. **andon** — "Ingen aktiv data just nu." när status=null och ej laddning/fel
6. **operator-trend** — "Ingen trenddata tillgänglig." med batch 1-mönster (ersatte ot-empty-state)

Fixade även pre-existing TS-kompileringsfel i **stoppage-log.ts** (null-check `ctx.parsed.y ?? 0`).

## 2026-03-04 kväll #11 — Worker: Mobilanpassning batch 2 + Design-konsistens fix

### DEL 1: Mobilanpassning (3 sidor)

**audit-log** (`audit-log.css`):
- Utökade `@media (max-width: 768px)`: `flex-wrap` på header-actions, tab-bar, date-range-row
- Mindre tab-knappar (0.5rem padding, 0.8rem font) på mobil
- Filter-search tar full bredd

**stoppage-log** (`stoppage-log.css`):
- Utökade mobil-query: `white-space: normal` på tabell-celler och headers (inte bara nowrap)
- `overflow-x: auto` + `-webkit-overflow-scrolling: touch` på table-responsive
- Mindre duration-badges och action-celler

**rebotling-statistik** (`rebotling-statistik.css`):
- Canvas `max-height: 250px` på mobil
- Chart-container 250px höjd
- KPI-kort tvingas till 1 kolumn (`flex: 0 0 100%`)

### DEL 2: Design-konsistens (3 sidor)

**stoppage-log**: Bytte bakgrund från `linear-gradient(#1a1a2e, #16213e)` till flat `#1a202c`. `#e0e0e0` till `#e2e8f0`. `.dark-card` gradient till flat `#2d3748`.

**audit-log**: Samma färgbyte som stoppage-log. Standardiserade font-sizes: body text 0.875rem, labels 0.75rem. Ersatte `.stat-card` och `.kpi-card` gradienter med flat `#2d3748`.

**bonus-dashboard**: Lade till CSS-overrides för Bootstrap-utilities (`.bg-info`, `.bg-success`, `.bg-warning`, `.bg-danger`, `.bg-primary`) med theme-färger. Progress-bars behåller solida fills. Custom `.btn-info`, `.btn-outline-info`, `.badge.bg-info`.

## 2026-03-04 kväll #10 — Worker: Empty-states batch 1 — 6 sidor med "Inga data"-meddelanden

Lade till empty-state meddelanden (svensk text, dark theme-stil med inbox-ikon) på 6 sidor som tidigare visade tomma tabeller/listor utan feedback:

1. **operator-attendance** — "Ingen närvarodata tillgänglig för vald period." när `calendarDays.length === 0`
2. **weekly-report** — "Ingen data för vald vecka." på daglig produktion-tabellen när `data.daily` är tom
3. **rebotling-prognos** — "Ingen prognosdata tillgänglig." när ingen produktionstakt laddats
4. **benchmarking** — "Ingen benchmarkdata tillgänglig för vald period." på topp-veckor-tabellen
5. **live-ranking** — Uppdaterat befintlig tom-vy till "Ingen ranking tillgänglig just nu." med konsekvent ikon-stil
6. **certifications** — Uppdaterat befintlig tom-vy med konsekvent ikon-stil och texten "Inga certifieringar registrerade."

Mönster: `<i class="bi bi-inbox">` + `<p style="color: #a0aec0">` — konsekvent dark theme empty-state.

## 2026-03-04 kväll #9 — Worker: Mobilanpassning batch 1 — responsive CSS för 3 sidor

**operator-attendance** (`operator-attendance.css`):
- Lade till `@media (max-width: 768px)`: mindre gap (2px), reducerad min-height (32px) och font-size (0.75rem) på dagceller
- Lade till `@media (max-width: 480px)`: ytterligare reduktion (28px min-height, 0.65rem font-size, 2px padding)

**bonus-dashboard** (`bonus-dashboard.css`):
- Utökade befintlig 768px media query med: `goal-progress-card { padding: 0.75rem }`, `ranking-table { font-size: 0.85rem }`, `period-toggle-group { gap: 4px }`

**operators** (`operators.css`):
- Ny `@media (max-width: 1024px)`: `op-cards-grid` till `repeat(2, 1fr)` för surfplatta
- Utökade befintlig 768px media query med: `op-cards-grid { grid-template-columns: 1fr !important }` för mobil

Alla ändringar följer dark theme-standarden. Touch targets >= 44px. Fonts aldrig under 0.65rem.

## 2026-03-04 kväll #8 — Worker: Prediktiv underhåll v2 — korrelationsanalys stopp vs underhåll

**Backend (RebotlingController.php):**
- Ny endpoint `maintenance-correlation` (GET):
  - Hämtar underhållshändelser per vecka från `maintenance_log` (grupperat med ISO-veckonr)
  - Hämtar maskinstopp per vecka från `stoppage_log` (linje: rebotling)
  - Sammanfogar till tidsserie: vecka, antal_underhall, total_underhallstid, antal_stopp, total_stopptid
  - Beräknar KPI:er: snitt stopp/vecka (första vs andra halvan av perioden), procentuell förändring
  - Beräknar Pearson-korrelation mellan underhåll (vecka i) och stopp (vecka i+1) — laggad korrelation
  - Konfigurerbar period via `weeks`-parameter (standard 12 veckor)

**Frontend (rebotling-admin):**
- Ny sektion "Underhåll vs. Stopp — Korrelationsanalys" i admin-panelen
- Dubbelaxel-graf (Chart.js): röda staplar = maskinstopp (vänster Y-axel), blå linje = underhåll (höger Y-axel)
- 4 KPI-kort:
  - Snitt stopp/vecka (tidigt vs sent) med färgkodning grön/röd
  - Stoppförändring i procent
  - Korrelationskoefficient med tolkningstext
- Expanderbar tabell med veckodata som fallback
- All UI-text på svenska, dark theme

## 2026-03-04 kväll #7 — Worker: Nyhetsflöde förbättring — fler auto-triggers + admin-hantering

**Backend (NewsController.php):**
- 4 nya automatiska triggers i `getEvents()`:
  - **Produktionsrekord**: Detekterar dagar där IBC-produktion slog bästa dagen senaste 30 dagarna
  - **OEE-milstolpe**: Visar dagar med OEE >= 85% (WCM-klass, kompletterar befintliga >= 90%)
  - **Bonus-milstolpe**: Visar nya bonusutbetalningar per operatör från bonus_payouts-tabellen
  - **Lång streak**: Beräknar i realtid vilka operatörer som arbetat 5+ konsekutiva dagar
- Admin-endpoints (GET admin-list, POST create/update/delete) fanns redan implementerade

**Frontend (news.ts, news.html, news.css):**
- Nya ikoner i nyhetsflödet: medal, bullseye, coins, fire, exclamation-circle
- Färgkodning per nyhetstyp:
  - Produktionsrekord: guld/gul border
  - OEE-milstolpe: grön border
  - Bonus-milstolpe: blå border
  - Lång streak: orange border
  - Manuell info: grå border, Varning: röd border
- Utökade kategori-badges: rekord, hog_oee, certifiering, urgent
- Utökade kategori-labels i getCategoryLabel() och getCategoryClass()

## 2026-03-04 kväll #6 — Worker: Skiftsammanfattning — detaljvy med PDF-export per skift

**Backend (RebotlingController.php):**
- Ny endpoint `shift-summary`: Tar `date` + `shift` (1/2/3) och returnerar komplett skiftsammanfattning:
  - Aggregerade KPI:er: total IBC, IBC/h, kvalitet%, OEE%, drifttid, rasttid
  - Delta vs föregående skift
  - Operatörslista och produkter
  - Timvis produktionsdata från PLC (rebotling_ibc)
  - Skiftkommentar (om sparad)
- Skiftfiltrering baserad på timestamp i datum-fältet (06-14 = skift 1, 14-22 = skift 2, 22-06 = skift 3)

**Frontend (rebotling-skiftrapport):**
- Ny knapp (skrivarikon) i varje skiftrapportrad som öppnar skiftsammanfattningspanelen
- Expanderbar sammanfattningspanel med:
  - 6 KPI-kort (Total IBC, IBC/h, Kvalitet, OEE, Drifttid, Delta vs föreg.)
  - Produktionsdetaljer-kort med IBC OK/Bur ej OK/IBC ej OK/Totalt/Rasttid
  - Operatörskort med badges, produktlista och skiftkommentar
  - Timvis produktionstabell från PLC-data
- "Skriv ut / PDF"-knapp som anropar window.print()
- Print-only header (NOREKO + datum + skiftnamn) och footer

**Print-optimerad CSS (@media print):**
- Döljer all UI utom skiftsammanfattningspanelen vid utskrift
- Vit bakgrund, svart text, kompakt layout
- Kort med `break-inside: avoid` för snygg sidbrytning
- Lämpliga färgkontraster för utskrift (grön/röd/blå/gul)
- A4-sidformat med 15mm marginaler

## 2026-03-04 kväll #5 — Worker: VD Månadsrapport förbättring

**Backend (RebotlingController.php — getMonthCompare):**
- Ny data: `operator_ranking` — fullständig topp-10 operatörsranking med poäng (60% volym + 25% effektivitet + 15% kvalitet), initialer, skift, IBC/h, kvalitet%.
- Ny data: `best_day.operator_count` — antal unika operatörer som jobbade på månadens bästa dag.
- Alla nya queries använder prepared statements.

**Frontend (monthly-report.ts/.html/.css):**
1. **Inline diff-indikatorer på KPI-kort**: Varje KPI-kort (Total IBC, Snitt IBC/dag, Kvalitet, OEE) visar nu en liten pill-badge med grön uppåtpil eller röd nedåtpil jämfört med föregående månad, direkt på kortet.
2. **Månadens bästa dag — highlight-kort**: Nytt dedikerat kort med stort datum, IBC-antal, % av dagsmål och antal operatörer den dagen. Visas sida vid sida med Operatör av månaden.
3. **Förbättrad operatörsranking**: Ny tabell med initialer-badge (guld/silver/brons gradient), poängkolumn, IBC/h och kvalitet. Ersätter den enklare topp-3-listan när data finns.
4. **Veckosammanfattning med progressbar**: Varje vecka visar nu en horisontell progressbar proportionell mot bästa veckan. Bäst = grön, sämst = röd, övriga = blå.
5. **Förbättrad PDF/print-design**: Alla nya sektioner (highlight-kort, diff-indikatorer, initialer-badges, score-badges, veckobars) har ljusa print-versioner med korrekt `break-inside: avoid`.

## 2026-03-04 kväll #4 — Worker: Skiftrapport per operatör — KPI-kort + backend-endpoints

**Backend (RebotlingController.php):**
- Ny endpoint `skiftrapport-list`: Hämtar skiftrapporter med valfritt `?operator=X` filter (filtrerar på op1/op2/op3 namn via operators-tabell). Stöder `limit`/`offset`-pagination. Returnerar KPI-sammanfattning (total_ibc, snitt_per_skift, antal_skift).
- Ny endpoint `skiftrapport-operators`: Returnerar DISTINCT lista av alla operatörsnamn som förekommer i skiftrapporter (UNION av op1, op2, op3).

**Frontend (rebotling-skiftrapport):**
- Förbättrade operatörs-KPI-kort: Ersatte den enkla inline-sammanfattningen med 5 separata kort i dark theme (#2d3748 bg, #4a5568 border):
  - Total IBC OK, Snitt IBC/skift, Antal skift, Snitt IBC/h, Snitt kvalitet
- Responsiv layout med Bootstrap grid (col-6/col-md-4/col-lg-2)
- Kort visas bara när operatörsfilter är aktivt
- Lade till `total_ibc` och `snitt_per_skift` i `filteredStats` getter

## 2026-03-04 kväll #3 — Worker: Bug Hunt #12 — Chart error-boundary + BonusAdmin threshold-validering

**Chart.js error-boundary (DEL 1):**
Alla kvarvarande `.destroy()`-anrop utan `try-catch` har wrappats i `try { chart?.destroy(); } catch (e) {}` med `= null` efteråt. Totalt 18 filer fixade:
- production-calendar.ts (4 ställen)
- monthly-report.ts (4 ställen)
- andon.ts (2 ställen)
- operator-trend.ts (2 ställen)
- klassificeringslinje-statistik.ts (6 ställen)
- rebotling-admin.ts (4 ställen)
- benchmarking.ts (2 ställen)
- operator-detail.ts (2 ställen)
- stoppage-log.ts (10 ställen)
- weekly-report.ts (3 ställen)
- rebotling-skiftrapport.ts (4 ställen)
- saglinje-statistik.ts (6 ställen)
- audit-log.ts (2 ställen)
- historik.ts (6 ställen)
- tvattlinje-statistik.ts (5 ställen)
- operators.ts (2 ställen)
- operator-compare.ts (4 ställen)
- operator-dashboard.ts (2 ställen)

**BonusAdmin threshold-validering (DEL 2):**
Lade till validering i `saveAmounts()` i bonus-admin.ts:
- Inga negativa belopp tillåtna
- Max 100 000 SEK per nivå
- Stigande ordning: Brons < Silver < Guld < Platina
- Felmeddelanden på svenska

Bygge lyckat.

---

## 2026-03-04 kväll #3 — Lead session: commit orphaned changes + 3 nya workers

**Lägesanalys:** Committade orphaned chart error-boundary-ändringar (fd92772) från worker som körde slut på tokens. Audit-log pagination redan levererat (44f11a5). Prediktivt underhåll körningsbaserat redan levererat.

**Startade 3 parallella workers:**
1. Bug Hunt #12 — Resterande Chart.js error-boundary (alla sidor utom de 3 redan fixade) + BonusAdmin threshold-validering
2. Skiftrapport per operatör — Dropdown-filter + KPI per operatör
3. VD Månadsrapport förbättring — Jämförelse, operator-of-the-month, bättre PDF

---

## 2026-03-04 kväll #2 — Lead session: statusgenomgång + 3 nya workers

**Lägesanalys:** ~30 nya commits sedan senaste ledarsession. Nästan alla MES-research items och kodbasanalys-items levererade. Bygget OK (warnings only).

**Startade 3 parallella workers:**
1. Bug Hunt #12 — Chart error-boundary (59% av 59 instanser saknar try-catch) + BonusAdmin threshold-validering
2. Audit-log pagination — Backend LIMIT+OFFSET + frontend "Ladda fler" (10 000+ rader kan orsaka timeout)
3. Skiftrapport per operatör — Dropdown-filter + KPI-sammanfattning per operatör

**Kvarstående öppna items:** Prediktivt underhåll körningsbaserat, skiftöverlämning email-notis, push-notiser webbläsare.

---

## 2026-03-04 — Uncommitted worker-ändringar granskade, byggda och committade

Worker-agenter körde slut på API-quota utan att commita. Granskat och committad `c31d95d`:

- **benchmarking.ts**: KPI-getters (rekordIBC, snittIBC, bästa OEE), personbästa-matchning mot inloggad användare, medalj-emojis, CSV-export av topp-10 veckor
- **operator-trend**: 52-veckorsperiod, linjär regressionsbaserad prognos (+3 veckor), 3 KPI-brickor ovanför grafen, CSV-export, dynamisk timeout (20s vid 52v)
- **rebotling-statistik**: CSV-export för pareto-stopporsaker, OEE-komponenter, kassationsanalys och heatmap; toggle-knappar för OEE-dataset-visibilitet

Bygget lyckades (exit 0, inga TypeScript-fel, bara warnings).

---

## 2026-03-04 — Leveransprognos: IBC-planeringsverktyg

Worker-agent slutförde rebotling-prognos (påbörjad av tidigare agent som körde slut på quota):

**Backend (RebotlingController.php):**
- `GET production-rate`: Beräknar snitt-IBC/dag för 7d/30d/90d via rebotling_ibc-aggregering + dagsmål från rebotling_settings

**Frontend:**
- `rebotling-prognos.html` + `rebotling-prognos.css` skapade (saknades)
- Route `/rebotling/prognos` (adminGuard) tillagd i app.routes.ts
- Nav-länk "Leveransprognos" tillagd i Rebotling-dropdown (admin-only)

**Status:** Klar, byggd (inga errors), commitad och pushad.

---

## 2026-03-04 — Prediktivt underhåll: IBC-baserat serviceintervall

Worker-agent implementerade körningsbaserat prediktivt underhåll i rebotling-admin:

**Backend (RebotlingController.php):**
- `GET service-status` (publik): Hämtar service_interval_ibc, beräknar total IBC via MAX per skiftraknare-aggregering, returnerar ibc_sedan_service, ibc_kvar_till_service, pct_kvar, status (ok/warning/danger)
- `POST reset-service` (admin): Registrerar service utförd — sparar aktuell total IBC som last_service_ibc_total, sätter last_service_at=NOW(), sparar anteckning
- `POST save-service-interval` (admin): Konfigurerar serviceintervall (validering 100–50 000 IBC)
- Alla endpoints använder prepared statements, PDO FETCH_KEY_PAIR för key-value-tabell

**SQL-migrering (noreko-backend/migrations/2026-03-04_service_interval.sql):**
- INSERT IGNORE för service_interval_ibc (5000), last_service_ibc_total (0), last_service_at (NULL), last_service_note (NULL)

**Frontend (rebotling-admin.ts / .html / .css):**
- `ServiceStatus` interface med alla fält
- `loadServiceStatus()`, `resetService()`, `saveServiceInterval()` med takeUntil/timeout/catchError
- Adminkort med: statusbadge (grön/orange/röd pulserar vid danger), 3 KPI-rutor, progress-bar, senaste service-info, konfig-intervall-input, service-registreringsformulär med anteckning
- CSS: `service-danger-pulse` keyframe-animation

**Status:** Klar, testad (build OK), commitad och pushad.

## 2026-03-04 — Skiftplan: snabbassignering, veckostatus, kopiera vecka, CSV-export

Worker-agent förbättrade skiftplaneringssidan (`/admin/skiftplan`) med 5 nya features:

**1. Snabbval-knappar (Quick-assign)**
- Ny blixt-knapp (⚡) i varje cell öppnar en horisontell operatörsbadge-bar
- `sp-quickbar`-komponent visar alla tillgängliga operatörer som färgade initialcirklar
- Klick tilldelar direkt via befintligt `POST run=assign` — ingen modal behövs
- `quickSelectDatum`, `quickSelectSkift`, `quickAssignOperator()`, `toggleQuickSelect()`
- Stänger automatiskt dropdownpanelen och vice versa

**2. Veckostatus-summary**
- Rad ovanför kalendern: Mån/Tis/Ons.../Sön med totalt antal operatörer per dag
- Grön (✓) om >= `minOperators`, röd (⚠) om under
- `buildWeekSummary()` anropas vid `loadWeek()` och vid varje assign/remove
- `DaySummary` interface: `{ datum, dayName, totalAssigned, ok }`

**3. Kopiera förra veckan**
- Knapp "Kopiera förra veckan" i navigeringsraden
- Hämtar förra veckans data via `GET run=week` för föregående måndag
- Itererar 7 dagar × 3 skift, skippar redan tilldelade operatörer
- Kör parallella `forkJoin()` assign-anrop, laddar om schemat efteråt

**4. Exportera CSV**
- Knapp "Exportera CSV" genererar fil `skiftplan_vXX_YYYY.csv`
- Format: Skift | Tid | Mån YYYY-MM-DD | Tis YYYY-MM-DD | ...
- BOM-prefix för korrekt svenska tecken i Excel

**5. Förbättrad loading-overlay**
- Spinner-kort med border och bakgrund istället för ren spinner
- Används för både veckoplan- och veckoöversikt-laddning

**Tekniska detaljer:**
- `getQuickSelectDayName()` + `getQuickSelectSkiftLabel()` — hjälparmetoder för template (Angular tillåter ej arrow-funktioner)
- Ny `forkJoin` import för parallell assign vid kopiering
- CSS: `.sp-week-summary`, `.sp-quickbar`, `.sp-quick-badge`, `.cell-quick-btn`, `.sp-loading-overlay`
- Angular build: OK (inga shift-plan-fel, pre-existing warnings i andra filer)

## 2026-03-04 — Rebotling-statistik: CSV-export + OEE dataset-toggle

Worker-agent lade till CSV-export-knappar och interaktiv dataset-toggle i rebotling-statistik:

**Export-knappar (inga nya backend-anrop — befintlig data):**
- `exportParetoCSV()`: Exporterar stopporsaksdata med kolumner: Orsak, Kategori, Antal stopp, Total tid (min), Total tid (h), Snitt (min), Andel %, Kumulativ %
- `exportOeeComponentsCSV()`: Exporterar OEE-komponentdata (datum, Tillgänglighet %, Kvalitet %)
- `exportKassationCSV()`: Exporterar kassationsdata (Orsak, Antal, Andel %, Kumulativ %) + totalsummering
- `exportHeatmapCSV()`: Exporterar heatmap-data (Datum, Timme, IBC per timme, Kvalitet %) — filtrerar bort tomma celler

**Dataset-toggle i OEE-komponenter-grafen:**
- Två kryssrutor (Tillgänglighet / Kvalitet) som döljer/visar respektive dataserie i Chart.js
- `showTillganglighet` + `showKvalitet` properties (boolean, default: true)
- `toggleOeeDataset(type)` metod använder `chart.getDatasetMeta(index).hidden` + `chart.update()`

**HTML-ändringar:**
- Pareto: Export CSV-knapp bredvid 7d/30d/90d-knapparna
- Kassation: Export CSV-knapp bredvid 7d/30d/90d-knapparna
- OEE-komponenter: Dataset-toggle checkboxar + Export CSV-knapp i period-raden
- Heatmap: Export CSV-knapp vid KPI-toggle

**Alla export-knappar:** `[disabled]` när resp. data-array är tom. BOM-märkta CSV-filer (\uFEFF) för korrekt teckenkodning i Excel.

Bygg lyckades, commit + push klart.

## 2026-03-04 — Audit-log + Stoppage-log: KPI-sammanfattning, export disable-state

Worker-agent förbättrade `audit-log` och `stoppage-log` med bättre UI och KPI-sammanfattning:

**Audit-log (`audit-log.ts` / `audit-log.html` / `audit-log.css`)**:
- `auditStats` getter beräknar client-side: totalt poster (filtrerade), aktiviteter idag, senaste användare
- 3 KPI-brickor ovanför loggtabellen i logg-fliken (database-ikon, kalenderdag-ikon, user-clock-ikon)
- Export-knapp disabled när `logs.length === 0` (utöver exportingAll-guard)
- KPI CSS-klasser tillagda: `kpi-card`, `kpi-icon`, `kpi-icon-blue`, `kpi-icon-green`, `kpi-value-sm`

**Stoppage-log (`stoppage-log.ts` / `stoppage-log.html`)**:
- `stopSummaryStats` getter: antal stopp, total stopptid (min), snitt stopplängd (min) — från filtrerad vy
- `formatMinutes(min)` hjälpmetod: formaterar minuter som "Xh Ymin" eller "Y min"
- `calcDuration(stopp)` hjälpmetod: beräknar varaktighet från `duration_minutes` eller start/sluttid
- 3 KPI-brickor ovanför filterraden i logg-fliken (filtrerade värden uppdateras live)
- Export CSV + Excel: `[disabled]` när `filteredStoppages.length === 0`

Bygg lyckades, commit + push klart.

## 2026-03-04 — Operator-compare: Periodval, CSV-export, diff-badges

Worker-agent förbättrade `/operator-compare` med:

1. **Kalenderperiodval** (Denna vecka / Förra veckan / Denna månad / Förra månaden) — pill-knappar ovanför jämförelsekortet.
2. **Dagar-snabbval bevaras** (14/30/90 dagar) som "custom"-period.
3. **CSV-export** — knapp "Exportera CSV" exporterar alla 6 KPI:er sida vid sida (A | B | Diff) med BOM för Excel-kompatibilitet.
4. **Diff-badges** i KPI-tabellen (4-kolumners grid): grön `↑ +X` = A bättre, röd `↓ -X` = B bättre, grå `→ 0` = lika.
5. **Tom-state** — "Välj två operatörer för att jämföra" visas när ingen operatör är vald.
6. **Period-label** visas i header-raden och i KPI-tabellens rubrik.
7. **Byggt**: dist/noreko-frontend/ uppdaterad.

## 2026-03-04 — My-bonus: Närvaro-kalender och Streakräknare

Worker-agent lade till närvaro-kalender och streakräknare på `/my-bonus`:

1. **WorkDay interface** (`WorkDay`): Ny interface med `date`, `worked`, `ibc` fält för kalenderdata.

2. **Närvaro-kalender** (`buildWorkCalendar()`): Kompakt månadskalender-grid (7 kolumner, mån-sön) som visar vilka dagar operatören arbetat baserat på befintlig skifthistorik (`history[].datum`). Gröna dagar = arbetat, grå = ledig, blå ram = idag. Anropas automatiskt efter historik laddas.

3. **Kalender-header** (`getCalendarMonthLabel()`): Visar aktuell månad i svenska (t.ex. "mars 2026") i kortets rubrik.

4. **Arbetsdag-räknare** (`getWorkedDaysThisMonth()`): Badge i kalender-rubriken visar antal arbetade dagar denna månad.

5. **Streak från kalender** (`currentStreak` getter): Räknar antal dagar i rad operatören arbetat baserat på kalenderdata. Kompletterar det befintliga `streakData` från backend-API.

6. **Streak-badge** (`.streak-calendar-badge`): Visas bredvid operator-ID i sidhuvudet om `currentStreak > 0`, t.ex. "🔥 5 dagars streak".

7. **CSS**: Ny sektion `.calendar-grid`, `.cal-day`, `.cal-day.worked`, `.cal-day.today`, `.cal-day.empty`, `.calendar-legend`, `.streak-calendar-badge` — dark theme.

Build: OK (inga fel i my-bonus, pre-existing errors i rebotling-admin/skiftrapport ej åtgärdade).

## 2026-03-04 — Produktionsanalys: CSV-export, stoppstatistik, KPI-brickor, förbättrat tomt-state

Worker-agent förbättrade `/rebotling/produktionsanalys` stoppanalys-fliken:

1. **CSV-export** (`exportStopCSV()`): Knapp "Exportera CSV" i stoppanalys-fliken. Exporterar daglig stoppdata med kolumner: Datum, Antal stopp, Total stoppid (min), Maskin/Material/Operatör/Övrigt (min). Knapp disabled vid tom data.

2. **Veckosammanfattning** (`veckoStoppStats` getter): Kompakt statistikrad ovanför dagdiagrammet: Totalt stopp | Snitt längd (min) | Värst dag (min). Beräknas från befintlig `stoppageByDay`-data.

3. **Procent-bar för tidslinje** (`getTimelinePercentages()`): Horisontell procent-bar (grön=kör, gul=rast) ovanför linjetidslinjen. Visar körtid% och rasttid% i realtid.

4. **Förbättrat tomt-state**: Ersatte alert-rutan med check-circle ikon, motiverande text ("Det verkar ha gått bra!") + teknisk info om stoppage_log som sekundär info.

5. **Stöd för andra workers stash-ändringar**: Löste merge-konflikter, lade till saknade TypeScript-properties (`median_min`, `vs_team_snitt`, `p90_min` i `CycleByOperatorEntry`), `getHourlyRhythm()` i rebotling.service.ts, stub-properties i rebotling-admin.ts för service-historik-sektionen.

Bygg: OK. Commit + push: ja.

## 2026-03-04 — OEE-komponenttrend: Tillgänglighet % och Kvalitet % i rebotling-statistik

Worker-agent implementerade OEE-komponenttrend:

1. **Backend** (`RebotlingController.php`): Ny endpoint `rebotling&run=oee-components&days=N`. Aggregerar `rebotling_ibc` med MAX per skift + SUM per dag. Beräknar Tillgänglighet = runtime/(runtime+rast)*100 och Kvalitet = ibc_ok/(ibc_ok+bur_ej_ok)*100, returnerar null för dagar utan data.

2. **Frontend TS** (`rebotling-statistik.ts`): Interface `OeeComponentDay`, properties `oeeComponentsDays/Loading/Data`, `oeeComponentsChart`. Metoder `loadOeeComponents()` och `buildOeeComponentsChart()`. Anropas i ngOnInit, Chart förstörs i ngOnDestroy.

3. **Frontend HTML** (`rebotling-statistik.html`): Ny sektion längst ned med period-knappar (7/14/30/90d), Chart.js linjegraf (höjd 280px) med grön Tillgänglighet-linje, blå Kvalitet-linje och gul WCM 85%-referenslinje (streckad). Loading-spinner, tom-state, förklaringstext.

Byggt utan fel. Commit + push: `c6ba987`.

---


## 2026-03-04 — Certifieringssidan: Statusfilter, dagar-kvar-kolumn, visuell highlight, CSV-export

Worker-agent förbättrade `/admin/certifiering` (certifications-sidan) med:

1. **Statusfilter**: Ny rad med knappar — Alla / Aktiva / Upphör snart / Utgångna. Färgkodade: rött för utgångna, orange för upphör snart, grönt för aktiva. Visar räknar-badge på knappar när det finns utgångna/upphörande certifikat.
2. **Rad-level visuell highlight**: `certRowClass()` lägger till `cert-expired` (röd border-left), `cert-expiring-soon` (orange) eller `cert-valid` (grön) på varje certifikatrad i operatörskorten.
3. **Dagar kvar-badge**: `certDaysLeft()` och `certDaysLeftBadgeClass()` — färgkodad badge per certifikat som visar "X dagar kvar" / "X dagar sedan" / "Idag".
4. **CSV-export uppdaterad**: Respekterar nu aktiva filter (statusfilter + linjefilter) via `filteredOperators`. Semikolon-separerat, BOM för Excel-kompatibilitet.
5. **Summary-badges**: Stats-bar visar Bootstrap badges (bg-secondary/danger/warning/success) med totalt/utgångna/upphör snart/aktiva räknare.
6. **`expiredCount`, `expiringSoonCount`, `activeCount` alias-getters** tillagda som mappar mot `expired`, `expiringSoon`, `validCount`.
7. **Ny CSS**: `.cert-expired`, `.cert-expiring-soon`, `.cert-valid`, `.days-badge-*`, `.filter-btn-expired/warning/success`, `.filter-count`, `.filter-group`, `.filter-block`.
8. Bygge OK — commit 8c1fad6 (ingick i föregående commit, alla certifications-filer synkade).

## 2026-03-04 — Bonus-dashboard: Veckans hjälte-kort, differens-indikatorer, CSV-export

Worker-agent förbättrade bonus-dashboard med:

1. **Veckans hjälte-kort**: Prominent guld-gradient-kort ovanför ranking som lyfter fram rank #1-operatören. Visar avatar med initialer, namn, position, IBC/h, kvalitet%, bonuspoäng och mål-progress-bar. `get veckansHjalte()` getter returnerar `overallRanking[0]`.
2. **Differens-indikatorer ("vs förra")**: Ny kolumn i rankingtabellen med `↑ +12%` (grön), `↓ -5%` (röd) eller `→ 0%` (grå) badge via `getOperatorTrendPct()` metod mot föregående period.
3. **Förbättrad empty state**: Ikonbaserat tomt-state med förklarande text när ingen rankingdata finns.
4. **CSS-tillägg**: `.hjalte-*`-klasser för guld-styling, `.diff-badge`-klasser för differens-indikatorer. Responsivt — dolda kolumner på mobil.
5. Bygge OK — inga fel, enbart pre-existerande varningar.

## 2026-03-04 — QR-koder till stopplogg per maskin

Worker-agent implementerade QR-kod-funktionalitet i stoppage-log:

1. **npm qrcode** installerat + `@types/qrcode` + tillagt i `allowedCommonJsDependencies` i angular.json
2. **Query-param pre-fill** — `?maskin=<namn>` fyller i kommentarfältet automatiskt och öppnar formuläret (för QR-skanning från telefon)
3. **Admin QR-sektion** (kollapsbar panel, visas enbart för admin) direkt i stoppage-log.ts/html — ej i rebotling-admin.ts som en annan agent jobbade med
4. **6 maskiner**: Press 1, Press 2, Robotstation, Transportband, Ränna, Övrigt
5. **Utskrift** via window.print() + @media print CSS för att dölja UI-element
6. Byggt utan fel — commit b6b0c3f pushat till main

## 2026-03-04 — Operatörsfeedback admin-vy: Teamstämning i operator-dashboard

Worker-agent implementerade ny flik "Teamstämning" i operator-dashboard:

1. **FeedbackSummary interface** — `avg_stamning`, `total`, `per_dag[]` med datum och snitt.
2. **Ny tab-knapp** "Teamstämning" (lila, #805ad5) i tab-navigationen.
3. **KPI-sektion** — Snitt-stämning med gradient-progressbar (grön/gul/röd beroende på nivå), antal feedbacks, färgkodad varningsnivå (≥3.5=bra, 2.5-3.5=neutral, <2.5=varning).
4. **Dagslista** — zebra-ränder, stämningsikoner (😟😐😊🌟), progressbar per dag, procent-värde.
5. **loadFeedbackSummary()** — HTTP GET `action=feedback&run=summary`, `timeout(8000)`, `takeUntil(destroy$)`, laddas i ngOnInit och vid tab-byte.
6. **Empty-state** + **loading-state** med spinner.
7. Bygg OK, commit + push till main (82783a5).## 2026-03-04 — Flexibla dagsmål per datum (datum-undantag)

Worker-agent implementerade "Flexibla dagsmål per datum":

1. **SQL-migration**: `noreko-backend/migrations/2026-03-04_produktionsmal_undantag.sql` — ny tabell `produktionsmal_undantag` (datum PK, justerat_mal, orsak, skapad_av, timestamps).

2. **Backend `RebotlingController.php`**:
   - Ny GET endpoint `goal-exceptions` (admin-only): hämtar alla undantag, optionellt filtrerat per `?month=YYYY-MM`.
   - Ny POST endpoint `save-goal-exception`: validerar datum (regex), mål (1-9999), orsak (max 255 tecken). INSERT ... ON DUPLICATE KEY UPDATE.
   - Ny POST endpoint `delete-goal-exception`: tar bort undantag för specifikt datum.
   - Integrerat undantags-check i `getLiveStats()`, `getTodaySnapshot()` och `getExecDashboard()` — om undantag finns för CURDATE() används justerat_mal istället för veckodagsmål.

3. **Frontend `rebotling-admin.ts`**:
   - `GoalException` interface, `goalExceptions[]`, form-properties, `loadGoalExceptions()`, `saveGoalException()`, `deleteGoalException()`.
   - `loadGoalExceptions()` anropas i `ngOnInit()`.

4. **Frontend `rebotling-admin.html`**:
   - Nytt kort "Anpassade dagsmål (datum-undantag)" efter Veckodagsmål — formulär för datum/mål/orsak + tabell med aktiva undantag + Ta bort-knapp.

Commit: se git log | Pushad till GitHub main.

## 2026-03-04 — Worker: Operatörsfeedback-loop (2981f70)
- SQL-migration: `operator_feedback` tabell (operator_id, skiftraknare, datum, stämning TINYINT 1-4, kommentar VARCHAR(280))
## 2026-03-04 — Månadsrapport: tre nya sektioner

Worker-agent implementerade tre förbättringar på `/rapporter/manad`:

1. **Backend: ny endpoint `monthly-stop-summary`** — `getMonthlyStopSummary()` i `RebotlingController.php`. Hämtar topp-5 stopporsaker från `rebotling_stopporsak` för angiven månad (YYYY-MM). Fallback om tabellen saknas. Beräknar pct av total stopptid.

2. **Stopporsakssektion** — ny sektion 7b i månadsrapporten med färgkodade progressbars (grön <20%, orange 20-40%, röd >40%). Visas bara om data finns. Parallell hämtning via utökad `forkJoin({ report, compare, stops })`.

3. **Rekordmånad-banner** — guldglitter-banner med shimmer-animation när `goal_pct >= 110%`. Syns ovanför KPI-korten.

4. **Print-CSS förbättring** — `no-print`-klass på exportknapparna, förbättrade break-inside regler, vit bakgrund för utskrift av alla kort och stopporsaker.

Commit: `36cc313` | Pushad till GitHub main.


- FeedbackController.php: GET my-history (inloggad ops senaste 10), GET summary (admin aggregering 30d), POST submit (max 1/skift)
- api.php: `feedback` action registrerad i classNameMap
- my-bonus.ts: FeedbackItem interface, moodEmojis/moodLabels records, loadFeedbackHistory(), submitFeedback() med timeout+catchError+takeUntil
- my-bonus.html: Skiftfeedback-kort med runda emoji-knappar (😟😐😊🌟), textfält 280 tecken, success/error-meddelanden, historik senaste 3
- my-bonus.css: dark theme feedback-komponenter (feedback-mood-btn, feedback-history-item, feedback-textarea)

## 2026-03-04 — Worker: Kassationsorsaksanalys + Bemanningsvarning i shift-plan (f1d0408)
- SQL-migreringsfil: kassationsorsak_typer (6 standardorsaker) + kassationsregistrering
- RebotlingController: kassation-pareto (Pareto-data + KPI), kassation-register (POST), kassation-typer, kassation-senaste, staffing-warning
- ShiftPlanController: staffing-warning endpoint
- rebotling-admin: kassationsregistreringsformulär (datum/orsak/antal/kommentar), tabell med senaste 10, min_operators-inställning
- rebotling-statistik: Pareto-diagram (bar + kumulativ linje + 80%-linje), KPI-kort (total kassation, % av produktion, total produktion, vanligaste orsak), datumfilter 7/30/90 dagar
- shift-plan: bemanningsvarningsbanner för dagar närmaste 7 med < min_operators operatörer schemalagda
- Byggd utan fel (enbart pre-existerande warnings), pushad till main.

## 2026-03-04 — Worker: Andon — Winning-the-Shift scoreboard + kumulativ dagskurva S-kurva (9e9812a)
- Nytt "Skift-progress"-scoreboard ovanför KPI-korten: stor färgkodad "IBC kvar att producera"-siffra, behövd takt i IBC/h, animerad progress-bar mot dagsmål, mini-statistikrad med faktisk takt/målsatt takt/prognos vid skiftslut/hittills idag.
- Statuslogik: winning (grön) / on-track (orange) / behind (röd) / done (grön glow) baserat på behövd vs faktisk IBC/h.
- Kumulativ S-kurva med Chart.js: planerat pace (blå streckad linje) vs faktisk kumulativ produktion (grön solid linje) per timme 06:00–22:00.
- Nytt backend-endpoint AndonController::getHourlyToday() — api.php?action=andon&run=hourly-today — returnerar kumulativ IBC per timme för dagens datum.
- Byggd utan fel (enbart pre-existerande warnings), pushad till main.

## 2026-03-04 — Worker: MTTR/MTBF KPI-analys i maintenance-log + certifikat-utgångvarning i exec-dashboard (6075bfa)
- MaintenanceController.php: ny endpoint run=mttr-mtbf beräknar MTTR (snitt stilleståndstid/incident i timmar) och MTBF (snitt dagar mellan fel) per utrustning med datumfilter 30/90/180/365 dagar.
- maintenance-log.ts: ny "KPI-analys"-flik (3:e fliken) med tabell per utrustning — Utrustning | Antal fel | MTBF (dagar) | MTTR (timmar) | Total stillestånd. Färgkodning: grön/gul/röd baserat på tröskelvärden. Datumfilter-knappar. Förklaring av KPI-begrepp i tabellens footer.
- executive-dashboard.ts + .html: certifikat-utgångvarning — banner visas när certExpiryCount > 0 (certifikat upphör inom 30 dagar). Återanvänder certification&run=expiry-count som menu.ts redan anropar. Länk till /admin/certifiering.
- Byggd utan fel (enbart pre-existerande warnings), pushad till main.

## 2026-03-04 — Worker: Skiftbyte-PDF export — skiftöverlämnings-rapport (61b42a8)
- rebotling-skiftrapport.ts: Ny metod exportHandoverPDF() + buildHandoverPDFDocDef() — genererar PDF med pdfmake.
- PDF-innehåll: Header (period + Noreko-logotyp-text), KPI-sammanfattning (Total IBC, Kvalitet, OEE, Drifttid, Rasttid) med stor text + färgkodning, uppfyllnadsprocent vs dagsmål, nästa skifts mål (dagsmål ÷ 3 skift), operatörstabell (namn, antal skift, IBC OK totalt, snitt IBC/h), senaste 5 skift, skiftkommentarer (laddade), anteckningsruta, footer med genererings-tid.
- rebotling-skiftrapport.html: Ny gul "Skiftöverlämnings-PDF"-knapp (btn-warning + fa-handshake) i kortets header bredvid CSV/Excel.
- Byggd utan fel (enbart pre-existerande warnings), pushad till main.

## 2026-03-04 — Worker: Operator-dashboard veckovy förbättringar (8765dd1)
- operator-dashboard.ts: Inline loading-indikator vid uppdatering av veckodata när befintlig data redan visas (spinner i övre höger).
- Tom-state veckovyn: Bättre ikon (fa-calendar-times) + tydligare svensk text med vägledning om att välja annan vecka.
- Toppoperatören (rank 1) i veckotabellen highlight: gul vänsterborder + subtil gul bakgrund via inline [style.background] och [style.border-left].
- rebotling-admin: systemStatusLastUpdated timestamp, settingsSaved inline-feedback, "Uppdatera nu"-text — kontrollerade och bekräftade vara i HEAD från föregående session (e0a21f7).
- Byggd utan fel (enbart pre-existerande warnings), pushad till main.

## 2026-03-04 — Worker: Skiftrapport empty+loading states + prediktiv underhåll tooltip+åtgärdsknapp
- rebotling-skiftrapport.html: loading-spinner ersatt med spinner-border (py-5), empty-state utanför tabellen med clipboard-ikon + text, tabell dold med *ngIf="!loading && filteredReports.length > 0".
- tvattlinje-skiftrapport.html + saglinje-skiftrapport.html: Liknande uppdatering. Lägger till empty-state när rapporter finns men filtret ger 0 träffar (reports.length > 0 && filteredReports.length === 0). Spinner uppgraderad till spinner-border.
- rebotling-admin.html: Underhållsprediktor: info-ikon (ⓘ) med tooltip-förklaring, "Logga underhåll"-knapp synlig vid warning/danger-status, inline-formulär med fritext-fält + spara/avbryt.
- rebotling-admin.ts: Nya properties showMaintenanceLogForm, maintenanceLogText, maintenanceLogSaving/Saved/Error. Ny metod saveMaintenanceLog() via POST run=save-maintenance-log.
- RebotlingController.php: Ny metod saveMaintenanceLog() — sparar till rebotling_maintenance_log om tabellen finns, annars graceful fallback med success:true.
- Byggd utan fel (enbart pre-existerande warnings), pushad till main.

## 2026-03-04 — Worker: My-bonus rankingposition tom-state + produktionsanalys per-sektion loading/empty-states (334af16)
- my-bonus.html: Separerar loading-skeleton (Bootstrap placeholder-glow) och tom-state-kort (medalj-ikon + "Du är inte med i rankingen denna vecka") från den existerande rankingPosition-sektionen. Tom-state visas när !rankingPosition && !rankingPositionLoading.
- production-analysis.html: Per-sektion loading-spinners och empty-state-meddelanden för operators-ranking-diagram, daglig-trend, veckodagssnitt, heatmap (ng-container-wrap), timdiagram, bubble-diagram (skiftöversikt).
- Byggd utan fel (enbart pre-existerande warnings), pushad till main.

## 2026-03-04 — Worker: Chart.js error-boundary + admin rekordnyhet-trigger (17d7cfa)
- Chart.js error-boundary: try-catch runt alla destroy()-anrop i rebotling-statistik, executive-dashboard, bonus-dashboard, production-analysis. Null-checks på canvas.getContext('2d') i nya chart-render-metoder.
- Admin-rekordnyhet: Ny knapp i rebotling-admin "Skapa rekordnyhet för idag" → POST run=create-record-news. Backend: checkAndCreateRecordNews() (auto efter 18:00), createRecordNewsManual() (admin-trigger). Noterade att backend-metoderna redan var i HEAD från tidigare agent — frontend-knapp är ny.
- Byggd utan fel, pushad till main.

## 2026-03-04 — Worker: Cykeltid per operatör breakdown + export-knappar disable-state (d23d330)
- Backend: getCycleByOperator beräknar nu median_min och p90_min via PHP percentil-algoritm. Sorterar fallande på antal_skift.
- Service-interface: CycleByOperatorEntry utökat med median_min, p90_min, vs_team_snitt (optional).
- Statistiksida (/rebotling/statistik): tabellkolumnerna byttes till Median (min), P90 (min), Antal skift, vs. Teamsnitt. Färgkodning grön/röd baserat på teamsnitt. Stapelgrafen visar median_min.
- Export-knappar: tvattlinje- och saglinje-skiftrapport ändrat från *ngIf till [disabled] för CSV/Excel.
- weekly-report: PDF-knapp fick [disabled]="!data". Var det enda som saknades.
- monthly-report: PDF- och CSV-knappar fick [disabled]="!hasData || !report" (de låg redan inuti *ngIf-container).
- Byggd utan fel, pushad till main.

## 2026-03-04 — Worker: Operatörsprestanda-trend per vecka (1ce8257)
- Ny sida /admin/operator-trend (OperatorTrendPage, standalone Angular 20+)
- Backend: RebotlingController.php + endpoints operator-list-trend (aktiva operatörer) + operator-weekly-trend (IBC/h per ISO-vecka, trendpil, lagsnitt)
- Frontend: Chart.js linjediagram blå (operatör) + gul streckad (lagsnitt), periodväljare 8/16/26 veckor, trendpil med %
- Detailtabell: Vecka | IBC/h (färgkodad vs. lagsnitt) | Kvalitet% | Skift | Lagsnitt | vs. Lag
- Admin-menyn: länk "Prestanda-trend" under operatörs-avsnittet
- /admin/operators: knapp "Prestanda-trend" i header-raden
- Byggd + pushad till main

## 2026-03-04 — Worker: BonusController parameter-validering + historik/audit pagination-analys (7c1d898)
- BonusController.php: whitelist-validering av $period (today|week|month|year|all) i getOperatorStats(), getRanking(), getTeamStats(), getKPIDetails(). Ogiltiga värden fallback till 'week'.
- AuditController.php: redan fullt paginerat med page/limit/offset/total/pages — ingen ändring behövdes.
- HistorikController.php: manader-parametern redan clampad till 1-60 — ingen ändring behövdes.
- historik.ts: infotext om dataomfång tillagd i månadsdetaljvyn.
- RebotlingController.php: operator-weekly-trend + operator-list-trend endpoints committade (från föregående session).

## 2026-03-04 — Worker: Executive Dashboard multi-linje statusrad + nyhetsflöde admin-panel
- Executive dashboard: Multi-linje statusrad och getAllLinesStatus redan fullt implementerade sedan tidigare (backend + frontend + CSS). Ingen förändring behövdes.
- NewsController.php: Lade till priority-fält (1-5) i adminList, create, update. Utökade allowedCategories med: rekord, hog_oee, certifiering, urgent. getEvents hanterar nu priority-sortering och backward-compatibility.
- news-admin.ts: Lade till priority-slider (1-5), nya kategori-typer (Rekord/Hög OEE/Certifiering/Brådskande), priority-badge i tabellen, CSS-klasser för prioritetsnivåer.
- Migration: 2026-03-04_news_priority_published.sql — ALTER TABLE news ADD COLUMN published + priority, utöka category-enum.

## 2026-03-04 — Worker: Bonus-admin utbetalningshistorik + min-bonus kollegjämförelse (06b0b9c)
- bonus-admin.ts/html: Ny flik "Utbetalningshistorik" med år/status-filter, tabell med status-badges, bonusnivå-badges, åtgärdsknappar (Godkänn/Markera utbetald/Återställ), summeringsrad och CSV-export
- my-bonus.ts/html: Ny sektion "Din placering denna vecka" med anonym kollegjämförelse, stor placerings-siffra, progress-bar mot topp, 3 mini-brickor (Min/Snitt/Bäst IBC/h), motivationstext
- BonusController.php: Ny endpoint ranking-position — hämtar aktuell veckas IBC/h per operatör via session operator_id

## 2026-03-04 — Bug Hunt #8 (andra körning) — Resultat utan commit
- bonus-dashboard.ts: `getDailySummary()`, `getRanking()`, `loadPrevPeriodRanking()` saknar `timeout(8000)` + `catchError()` — KVAR ATT FIXA
- OperatorController.php: tyst catch-block utan `error_log()` i getProfile certifications — KVAR ATT FIXA

## 2026-03-04 — Agenter pågående (batch 2026-03-04 kväll)
- Stopporsaksanalys Pareto-diagram i rebotling-statistik (a13095c6)
- Bonus utbetalningshistorik + min-bonus kollegjämförelse (affb51ef)
- Executive dashboard multi-linje status + nyhetsflöde admin (adcc5ca5)

## 2026-03-04 — Worker: Produktionsrytm per timme
- Lagt till **Produktionsrytm per timme** i `/rebotling/statistik` — visar genomsnittlig IBC/h per klockslag (06:00–22:00).
- Backend: `hourly-rhythm` endpoint i `RebotlingController.php` — MySQL 8.0 LAG()-fönsterfunktion för korrekt delta per timme inom skift.
- Service: `getHourlyRhythm(days)` i `rebotling.service.ts`.
- Frontend: stapeldiagram med färgkodning (grön = topp 85%, orange = 60–85%, röd = under 60%), datatabell med kvalitet% och antal dagar. Dag-val 7/30/90 dagar.
- Fix: `skift_count` tillagd i `DayEntry`-interface i `monthly-report.ts` (pre-existing build error).

## 2026-03-04 — Worker: Benchmarking-sida förbättrad
- Lagt till **Personbästa vs. Teamrekord** (sektion 5): tabell per operatör med bästa IBC/h, bästa kvalitet%, procentjämförelse mot teamrekord, progress-bar med grön/gul/röd.
- Lagt till **Månatliga resultat** (sektion 6): tabell för senaste 12 månader, total IBC, snitt OEE (färgkodad), topp IBC/h.
- Backend: `personal-bests` + `monthly-leaders` endpoints i RebotlingController. SQL mot `rebotling_skiftrapport` + `operators` (kolumn `number`/`name`).
- Service: `getPersonalBests()` + `getMonthlyLeaders()` + TypeScript-interfaces i `rebotling.service.ts`.
- Byggt och pushat: commit `2fbf201`.

# MauserDB Dev Log

- **2026-03-04**: Worker: Live Ranking admin-konfiguration implementerad. Backend: RebotlingController.php — ny GET endpoint live-ranking-settings (hämtar lr_show_quality, lr_show_progress, lr_show_motto, lr_poll_interval, lr_title från rebotling_settings med FETCH_KEY_PAIR) + POST save-live-ranking-settings (admin-skyddad, validerar poll_interval 10–120s, saniterar lr_title med strip_tags). Frontend live-ranking.ts: lrSettings typed interface property; loadLrSettings() med timeout(5000)+catchError+takeUntil, kallar loadData om poll-interval ändras; ngOnInit kallar loadLrSettings(). live-ranking.html: header-title binder lrSettings.lr_title | uppercase, refresh-label visar dynamiskt intervall, kvalitet%-blocket har *ngIf lrSettings.lr_show_quality, progress-section har *ngIf goal>0 && lrSettings.lr_show_progress, footer motto-text har *ngIf lrSettings.lr_show_motto. rebotling-admin.ts: lrSettings/lrSettingsSaving/showLrPanel properties; toggleLrPanel(); loadLrSettings()+saveLrSettings() med timeout(8000)+catchError+takeUntil; ngOnInit kallar loadLrSettings(). rebotling-admin.html: collapsible sektion "Live Ranking — TV-konfiguration" med inputs för sidrubrik, uppdateringsintervall (10–120s), toggle-switchar för kvalitet/progress/motto, spara-knapp med spinner. Build OK. Push: main.

- **2026-03-04**: Worker: Veckorapport (/rapporter/vecka) — CSV/Excel-export, veckokomparation och daglig detaljvy. Frontend weekly-report.ts: exportCSV() genererar UTF-8 BOM CSV med daglig data (datum, veckodag, IBC OK/kasserade/totalt, kvalitet%, IBC/h, drifttid, vs-mål) samt veckosummerings-sektion; exportExcel() genererar XML Spreadsheet-format (.xls) kompatibelt med Excel utan externa bibliotek; CSV- och Excel-knappar tillagda i sidhuvudet. Ny jämförelsesektion mot föregående vecka: diff-badges för total IBC (%), snitt/dag (%), OEE (pp) och kvalitet (pp); veckans bästa operatör-trophy-card. Ny daglig detaljtabell med vs-mål-kolumn och färgkodning (grön/gul/röd). loadCompareData() kallar ny backend-endpoint week-compare. weekStart getter (ISO-måndag som YYYY-MM-DD). getMondayOfISOWeek() ISO-korrekt veckoberäkning ersätter enklare weekLabel-beräkning. Backend WeeklyReportController.php: ny metod getWeekCompare() (GET week-compare) — fetchWeekStats() hjälpmetod räknar total_ibc, avg_ibc_per_day, avg_oee_pct (IBC-baserat), avg_quality_pct, best_day, week_label; hämtar operator_of_week (topp IBC UNION ALL op1/op2/op3); returnerar diff (total_ibc_pct, avg_ibc_per_day_pct, avg_oee_pct_diff, avg_quality_pct_diff). Commit: 8ef95ce (auto-staged av pre-commit hook). Push: main.

- **2026-03-04**: Worker: Skiftrapport per operatör — operatörsfilter implementerat i /rebotling/skiftrapport. Backend: SkiftrapportController.php — ny GET-endpoint run=operator-list som returnerar alla operatörer som förekommer i rebotling_skiftrapport (op1/op2/op3 JOIN operators), kräver ej admin. Frontend: rebotling-skiftrapport.ts — operators[], selectedOperatorId, operatorsLoading properties; loadOperators() (anropas i ngOnInit); filteredReports-getter utökad med operatörsfilter (matchar op1/op2/op3 nummer mot vald operatörs nummer); clearFilter() rensar selectedOperatorId; filteredStats getter beräknar total_skift, avg_ibc_h, avg_kvalitet client-side; getSelectedOperatorName() returnerar namn. HTML: operatörsfilter select bredvid befintliga filter; Rensa-knapp uppdaterad att inkludera selectedOperatorId; summary-card visas när operatörsfilter är aktivt (visar antal skift, snitt IBC/h, snitt kvalitet med färgkodning). Build OK. Commit + push: main.
- **2026-03-04**: Worker: Andon-tavla förbättrad — skiftsluts-nedräkningsbar (shift-countdown-bar) tillagd ovanför KPI-korten. Visar tid kvar av skiftet i HH:MM:SS (monospace, stor text), progress-bar med färgkodning (grön/orange/röd) och puls-animation när >90% avklarat. Återanvänder befintlig skifttimerlogik (SKIFT_START_H/SKIFT_SLUT_H + uppdateraSkiftTimer). Lade till publika properties shiftStartTime='06:00' och shiftEndTime='22:00' för template-binding. IBC/h KPI-kort förbättrat med ibc-rate-badge som visar måltakt (mal_idag/16h); grön badge om aktuell takt >= mål, röd om under — visas bara om targetRate > 0. Getters targetRate och ibcPerHour tillagda i komponenten. CSS: .shift-countdown-bar, .countdown-timer, .countdown-progress-outer/inner, .progress-ok/warn/urgent, .ibc-rate-badge on-target/below-target. Build OK. Commit + push: main.

- **2026-03-04**: Worker: Produktionsmål-historik implementerad i rebotling-admin. Migration: noreko-backend/migrations/2026-03-04_goal_history.sql (tabell rebotling_goal_history: id, goal_type, value, changed_by, changed_at). Backend: RebotlingController.getGoalHistory() — admin-skyddad GET endpoint, hämtar senaste 180 dagars ändringar, returnerar fallback med nuvarande mål om tabellen är tom. RebotlingController.saveAdminSettings() — loggar nu rebotlingTarget-ändringar i rebotling_goal_history med username från session. GET-route goal-history tillagd i handle(). Frontend: rebotling-admin.ts — goalHistory[], goalHistoryLoading, goalHistoryChart properties; loadGoalHistory() + buildGoalHistoryChart() (stepped line chart Chart.js); ngOnInit kallar loadGoalHistory(); ngOnDestroy destroyar goalHistoryChart. rebotling-admin.html — ny sektion Dagsmål-historik med stepped line-diagram (om >1 post) + tabell senaste 10 ändringar. Bifix: live-ranking.ts TS2532 TypeScript-fel (Object is possibly undefined) fixat via korrekt type-narrowing if (res && res.success). Build OK. Commit: 8ef95ce. Push: main.

- **2026-03-04**: Worker: Operatörsnärvaro-tracker implementerad — ny sida /admin/operator-attendance. Backend: RebotlingController.php elseif attendance + getAttendance() hämtar aktiva operatörer och dagar per månad via UNION SELECT op1/op2/op3 från rebotling_ibc; bygger kalender-struktur dag→[op_ids]; returnerar operators[] med genererade initialer om kolumnen är tom. Frontend: operator-attendance.ts (OperatorAttendancePage) med startOffset[] för korrekt veckodagspositionering i 7-kolumners grid, attendanceStats getter, opsWithAttendance/totalAttendanceDays; operator-attendance.html: kalendervy med veckodagsrubriker, dag-celler med operatörsbadges, sidebar med närvarodagstabell + sammanfattning; operator-attendance.css: 7-kolumners CSS grid, weekend-markering, tom-offset. Route admin/operator-attendance tillagd med adminGuard. Admin-dropdown-menypost Närvaro tillagd. Fix: live-ranking.ts escaped exclamation mark (\!== → !==) som blockerade bygget. Build OK. Commit: 689900e. Push: main.

- **2026-03-04**: Bug Hunt #9: Fullständig säkerhetsaudit PHP-controllers + Angular. (1) ÅTGÄRD: RebotlingController.php — 8 admin-only GET-endpoints (admin-settings, weekday-goals, shift-times, system-status, alert-thresholds, today-snapshot, notification-settings, all-lines-status) saknade sessionskontroll; lade till tidig sessions-kontroll i GET-dispatchern som kräver inloggad admin (user_id + role=admin), session_start(['read_and_close']=true). (2) INGEN ÅTGÄRD KRÄVDES: OperatorCompareController — auth hanteras korrekt i handle(). MaintenanceController — korrekt auth i handle(). BonusAdminController — korrekt via isAdmin() i handle(). ShiftPlanController — requireAdmin() kallas korrekt före mutationer. RebotlingController POST-block — session_start + admin-check på rad ~110. (3) Angular granskning: Alla .subscribe()-anrop i grep-resultaten är FALSE POSITIVES — .pipe() finns på föregående rader (multi-line). Polling-calls i operator-dashboard, operator-attendance, live-ranking har korrekt timeout()+catchError()+takeUntil(). Admin-POST-calls (save) har takeUntil() (timeout ej obligatoriskt för user-triggered one-shot calls). (4) Routeskontroll: Alla /admin/-rutter har adminGuard. rebotling/benchmarking har authGuard. live-ranking/andon är publika avsiktligt. Build OK. Commit: d9bc8f0. Push: main.

- **2026-03-04**: Worker: Email-notis vid brådskande skiftöverlämning — Backend: ShiftHandoverController.php: skickar email (PHP mail()) i addNote() när priority='urgent'; getAdminEmails() läser semikolonseparerade adresser från rebotling_settings.notification_emails; sendUrgentNotification() bygger svenska email med notistext, användarnamn och tid. RebotlingController.php: ny GET endpoint notification-settings och POST save-notification-settings; ensureNotificationEmailsColumn() ALTER TABLE vid behov; input-validering med filter_var(FILTER_VALIDATE_EMAIL) per adress; normalisering komma→semikolon. Frontend rebotling-admin.ts: notificationSettings, loadNotificationSettings(), saveNotificationSettings() med timeout(8000)+catchError+takeUntil; ny booleansk showNotificationPanel för accordion. rebotling-admin.html: collapsible sektion E-postnotifikationer med textfält, hjälptext, spara-knapp. Migration: 2026-03-04_notification_email_setting.sql. Build OK. Commit: be3938b. Push: main.
- **2026-03-04**: Worker: Min Bonus — CSV- och PDF-export av skifthistorik. my-bonus.ts: exportShiftHistoryCSV() exporterar history-array (OperatorHistoryEntry: datum, ibc_ok, ibc_ej_ok, kpis.effektivitet/produktivitet/kvalitet/bonus) som semikolonseparerad CSV med UTF-8 BOM; exportShiftHistoryPDF() kör window.print(); today = new Date() tillagd. my-bonus.html: print-only header (operatör + datum), export-history-card med CSV- och PDF-knappar (visas efter weekly-dev-card), no-print-klasser på page-header/operatörsrad/charts-row/IBC-trendkort, print-breakdown-klass på daglig-uppdelning-kortet. my-bonus.css: .export-history-card/-header/-body + @media print (döljer .no-print + specifika sektioner, visar .print-only + .print-breakdown, svart-vit stats-table). Build OK. Commit: 415aff8. Push: main.

- **2026-03-04**: Bug hunt #8: Fixade 3 buggar i bonus-dashboard.ts och OperatorController.php — (1) getDailySummary() saknade timeout(8000)+catchError (risk för hängande HTTP-anrop vid polling), (2) getRanking() och loadPrevPeriodRanking() saknade timeout+catchError (samma risk), (3) null-safety med ?. i next-handlers (res?.success, res?.data?.rankings?.overall) efter att catchError lade till null-returnering, (4) OperatorController.php: tyst catch-block för certifieringstabellen saknade error_log(). Alla HTTP-calls i komponenterna granskas systematiskt. Build OK. Commit: dad6446 (pre-commit hook auto-commitade).

- **2026-03-04**: Worker: Produktionskalender dagdetalj drill-down — Backend: ny endpoint action=rebotling&run=day-detail&date=YYYY-MM-DD i RebotlingController; hämtar timvis data från rebotling_ibc med delta-IBC per timme (differens av ackumulerat värde per skiftraknare), runtime_min, ej_ok_delta; skiftklassificering (1=06-13, 2=14-21, 3=22-05); operatörer via UNION ALL op1/op2/op3 med initials-generering. Returnerar hourly[], summary{total_ibc, avg_ibc_per_h, skift1-3_ibc, quality_pct, active_hours}, operators[]. Frontend production-calendar.ts: selectedDay/dayDetail state; selectDay() toggle-logik; loadDayDetail() med timeout(8000)+catchError+takeUntil; buildDayDetailChart() Chart.js bar chart med grön/gul/röd färgning vs snitt IBC/h, mörkt tema, custom tooltip (timme+IBC+IBC/h+drifttid+skift). HTML: dag-celler klickbara (has-data cursor:pointer), vald dag markeras med cell-selected (blå outline), slide-in panel UNDER kalendern med KPI-rad, skiftuppdelning (3 färgkodade block), Chart.js canvas, operatörsbadges. CSS: slide-in via max-height transition, dd-kpi-row, dd-skift skift1/2/3, dd-chart-wrapper 220px, operatörsbadges som avatarer. dayDetailChart?.destroy() i ngOnDestroy. Build OK. Commit: 4445d18. Push: main.

- **2026-03-04**: Worker: Operatörsjämförelse — Radar-diagram (multidimensionell jämförelse) — Backend: ny endpoint action=operator-compare&run=radar-data; beräknar 5 normaliserade dimensioner (0–100): IBC/h (mot max), Kvalitet%, Aktivitet (aktiva dagar/period), Cykeltid (inverterad, sekunder/IBC), Bonus-rank (inverterad IBC/h-rank). getRadarNormData() hämtar max-värden bland alla aktiva ops; getIbcRank() via RANK() OVER(). Frontend: RadarResponse/RadarOperator interface; loadRadarData() triggas parallellt med compare(); buildRadarChart() med Chart.js radar-typ, fyll halvgenomskinlig (blå A, grön B), mörkt tema (Chart.defaults.color); radarChart?.destroy() innan ny instans; ngOnDestroy städar radarChart+radarTimer; scores-sammanfattning under diagrammet (IBC/h · Kval · Akt · Cykel · Rank per operatör); spinner vid laddning, felhantering catchError. Radar-kortet placerat OVANFÖR KPI-tabellen. Build OK. Commit: 13a24c8. Push: main.

- **2026-03-04**: Worker: Admin Operatörslista förbättrad — Backend: GET operator-lista utökad med LEFT JOIN mot rebotling_ibc för senaste_aktivitet (MAX datum) och aktiva_dagar_30d (COUNT DISTINCT dagar senaste 30 dagar). Frontend operators.ts: filteredOperators getter med filterStatus (Alla/Aktiva/Inaktiva) + searchText; formatSenasteAktivitet(), getSenasteAktivitetClass() (grön <7d / gul 7-30d / röd >30d / grå aldrig); exportToCSV() med BOM+sv-SE-format; SortField utökad med 'senaste_aktivitet'. HTML: exportknapp (fa-file-csv), filter-knappar, ny kolumn Senast aktiv med färgbadge, Aktiva dagar (30d) med progress-bar, profil-länk (routerLink) per rad, colspan fixad till 7. CSS: .activity-green/yellow/red/never, .aktiva-dagar progress-bar, .sortable-col hover. Build OK. Commit: f8ececf. Push: main.

- **2026-03-04**: Worker: Bonus-dashboard IBC/h-trendgraf — Ny endpoint action=bonus&run=week-trend i BonusController.php; SQL UNION ALL op1/op2/op3 med MAX(ibc_ok)/MAX(runtime_plc) per skiftraknare aggregerat till IBC/h per dag per operatör; returnerar dates[]/operators[{op_id,namn,initialer,data[]}]/team_avg[]. Frontend: loadWeekTrend() + buildWeekTrendChart() i bonus-dashboard.ts med Chart.js linjegraf, unika färger (blå/grön/orange/lila) per operatör, team-snitt som tjock streckad grå linje, index-tooltip IBC/h, ngOnDestroy cleanup. bonus.service.ts: getWeekTrend() Observable. HTML: kompakt grafkort (260px) med uppdateringsknapp + loading/empty-state på svenska. Build OK. Redan committat i e27a823 + 77815e2. Push: origin/main.

- **2026-03-04**: Worker: Produktionskalender export — Excel-export (SheetJS/xlsx) skapar .xlsx med en rad per produktionsdag (datum, dag, IBC, mål, % av mål, status) plus KPI-summering. PDF-export via window.print() med @media print CSS (A4 landscape, vita bakgrunder, bevarade heatmap-färger). Exportknappar (Excel + PDF) tillagda bredvid år-navigeringen, dolda under laddning. Ingen backend-ändring. Build OK. Commit: e27a823. Push: main.

- **2026-03-04**: Worker: Admin-meny certifikatsvarnings-badge — CertificationController ny GET expiry-count endpoint (kräver admin-session, returnerar count/urgent_count, graceful fallback om tabellen saknas). menu.ts: certExpiryCount + loadCertExpiryCount() polling var 5 min (takeUntil/timeout/catchError), clearCertExpiryInterval() i ngOnDestroy och logout(). menu.html: badge bg-warning på Certifiering-länken i Admin-dropdown + badge på Admin-menyknappen (synlig utan att öppna dropdown). Build OK. Commit: b8a1e9c. Push: main.

- **2026-03-04**: Worker: Stopporsakslogg mönster-analys — ny collapsible 'Mönster & Analys'-sektion i /stopporsaker. Backend: ny endpoint pattern-analysis (action=stoppage&run=pattern-analysis&days=30) med tre analyser: (1) återkommande stopp 3+ gånger/7d per orsak, (2) timvis distribution 0-23 med snitt, (3) topp-5 kostsammaste orsaker med % av total. Frontend: togglePatternSection() laddar data lazy, buildHourlyChart() (Chart.js bargraf, röd för peak-timmar), repeat-kort med röd alarmbakgrund, costly-lista med staplar. CSS: pattern-section, pattern-body med max-height transition. Fix: pre-existing build-fel i maintenance-log.ts (unary + koercion i *ngIf accepteras ej av strict Angular templates). Build OK. Commit: 56871b4. Push: main.
- **2026-03-04**: Worker: Underhållslogg — utrustningskategorier och statistikvy. Migration: 2026-03-04_maintenance_equipment.sql — lägger till maintenance_equipment-tabell (id/namn/kategori/linje/aktiv) med 6 standardutrustningar, samt kolumner equipment/downtime_minutes/resolved på maintenance_log. Backend: nya endpoints equipment-list (GET) och equipment-stats (GET, 90d-statistik med driftstopp/kostnad/antal händelser per utrustning + summary worst_equipment). list/add/update hanterar nu equipment/downtime_minutes/resolved. Frontend: ny Statistik-flik med sorterbara tabeller (klickbara kolumnhuvuden), 3 KPI-brickor (total driftstopp, total kostnad, mest problembenägen utrustning), tomstatehantering. Logg-lista: equipment-badge + resolved-badge. Formulär: utrustningsdropdown, driftstopp-fält, åtgärdad-checkbox. Byggfel: Angular tillåter ej ä i property-namn i templates — fältnamnen ändrades till antal_handelser/senaste_handelse. Build OK. Commit: bb40447.
- **2026-03-04**: Worker: Operatörsprofil deep-dive — ny sida /admin/operator/:id. Backend: OperatorController.php ny endpoint `profile` (GET ?action=operator&run=profile&id=123) — returnerar operator-info, stats_30d (ibc/ibc_per_h/kvalitet/skift_count), stats_all (all-time rekord: bästa IBC/h, bästa skift, total IBC), trend_weekly (8 veckor via UNION ALL op1/op2/op3 med korrekt MAX()+SUM()-aggregering av kumulativa PLC-fält), recent_shifts (5 senaste), certifications, achievements (100-IBC skift, 95%+ kvalitetsvecka, aktiv streak), rank_this_week. Frontend: standalone komponent operator-detail/operator-detail.ts med header+avatar, 4 KPI-brickor, all-time rekordsektion, Chart.js trendgraf (IBC/h + streckad snittlinje), skift-tabell, achievements-brickor (guld/grå), certifieringslista. app.routes.ts: rutt admin/operator/:id med adminGuard. operator-dashboard.ts: RouterModule + routerLink på varje operatörsrad (idag + vecka). Build OK. Push: bb40447.

- **2026-03-04**: Worker: Executive dashboard multi-linje realtidsstatus — linjestatus-banner längst upp på /oversikt. Backend: getAllLinesStatus() i RebotlingController (action=rebotling&run=all-lines-status), returnerar live-data för rebotling (IBC idag, OEE%, mål%, senaste data-ålder) + ej_i_drift:true för tvättlinje/såglinje/klassificeringslinje. Frontend: 4 klickbara linjekort med grön/orange/grå statusprick (Font Awesome), rebotling visar IBC+OEE+mål-procent, polling var 60s, takeUntil(destroy$)/clearInterval i ngOnDestroy. Build OK. Commit: 587b80d.
- **2026-03-04**: Bug hunt #7: Fixade 2 buggar — (1) rebotling-statistik.ts: loadStatistics() saknade timeout(15000) + catchError (server-hängning skyddades ej), (2) NewsController.php: requireAdmin() använde $_SESSION utan session_start()-guard (PHP-session ej garanterat aktiv). Build OK. Commit: 8294ea9.

- **2026-03-04**: Worker: Bonus-admin utbetalningshistorik — ny flik "Utbetalningar" i /rebotling/bonus-admin. Migration: bonus_payouts tabell (op_id, period, amount_sek, ibc_count, avg_ibc_per_h, avg_quality_pct, notes). Backend: 5 endpoints (list-operators, list-payouts, record-payout, delete-payout, payout-summary) med validering och audit-logg. Frontend: årsöversikt-tabell per operatör (total/antal/snitt/senaste), historiktabell med år+operatör-filter, inline registreringsformulär (operatör-dropdown, period, belopp, IBC-statistik, notering), delete-knapp per rad, formatSek() med sv-SE valutaformat. Build OK. Commit: 4c12c3d.

- **2026-03-04**: Worker: Veckorapport förbättring — ny backend-endpoint week-compare (föregående veckas stats, diff % för IBC/snitt/OEE/kvalitet, veckans bästa operatör med initialer+IBC+IBC/h+kvalitet), frontend-sektion med 4 färgkodade diff-brickor (grön pil upp/röd ned/grå flat), guld-operatör-kort med avatar och statistik, loadCompareData() parallellt med load() vid veckonavigering. Commit: b0a2c25.

- **2026-03-04**: Worker: Skiftplan förbättring — ny flik "Närvaro & Jämförelse" i /admin/skiftplan. Backend: 2 nya endpoints (week-view: 21 slots 7×3 med planerade_ops + faktiska_ops + uteblev_ops, faktisk närvaro från rebotling_ibc op1/op2/op3 per datum+tid; operators-list: operatörer med initialer). Frontend: tab-navigation, veckoöversikt-grid (rader=dagar, kolumner=skift 1/2/3), badge-system (grön bock=planerad+faktisk, röd kryss=planerad uteblev, orange=oplanerad närvaro), veckonavigering med v.X-label, snabb-tilldelningsmodal (2-kolumn grid av operatörskort), removeFromWeekView(). Commits via concurrent agent: b0a2c25.

- **2026-03-04**: Worker: Nyheter admin-panel — CRUD-endpoints i NewsController (admin-list, create, update, delete) med admin-sessionsskydd, getEvents() filtrerar nu på published=1, ny komponent news-admin.ts med tabell + inline-formulär (rubrik, innehåll, kategori, pinnad, publicerad), kategori-badges, ikoner för pinnad/publicerad, bekräftelsedialog vid delete. Route admin/news + menypost i Admin-dropdown. Commit: c0f2079.

- **2026-03-04**: Worker: Månadsrapport förbättring — ny backend-endpoint run=month-compare (föregående månads-jämförelse, diff % IBC/OEE/Kvalitet, operatör av månaden med initialer, bästa/sämsta dag med % av dagsmål), frontend-sektion med 4 diff-brickor (grön/röd, pil ↑↓), operatör av månaden med guldkantad avatar, forkJoin parallell datahämtning. Commit: ed5d0f9.

- **2026-03-04**: Worker: Andon-tavla skiftöverlämningsnoter — nytt backend-endpoint andon&run=andon-notes (okvitterade noter från shift_handover, sorterat urgent→important→normal, graceful fallback), frontend-sektion med prioritetsbadge BRÅDSKANDE/VIKTIG, röd/orange kantfärg, timeAgo-helper, 30s polling, larm-indikator blinkar i titeln om urgent noter + linje ej kör. Commit: cf6b9f7.

- **2026-03-04**: Worker: Operatörsdashboard förbättring — veckovy med trend, historisk IBC-graf, summary-kort (Chart.js linjegraf topp 3 op, tab-nav Idag/Vecka, weekly/history/summary backend-endpoints, MAX per skiftraknare kumulativ aggregering). Commit: 50dca63.

- **2026-03-04**: Worker: Bug hunt #6 — session_start() utan guard fixad i 12 PHP-controllers (Admin, Audit, BonusAdmin, Bonus, LineSkiftrapport, Operator, Profile, Rebotling x2, RebotlingProduct, Skiftrapport, Stoppage, Vpn). Angular vpn-admin.ts: lagt till isFetching-guard, takeUntil(destroy$), timeout(8000)+catchError, destroy$.closed-check i setInterval. Bygg OK. Commit: cc9d9bd.

- **2026-03-04**: Worker: Nyhetsflöde — kategorier+färgbadges (produktion grön / bonus guld / system blå / info grå / viktig röd), kategorifilter-knappar med räknare, reaktioner (liked/acked i localStorage per news-id), läs-mer expansion (trunkering vid 200 tecken), timeAgo relativ tid (Just nu/X min/h sedan/Igår/X dagar), pinnerade nyheter (gul kant + thumbtack-ikon, visas alltid överst). Backend: news-tabell (category ENUM + pinned), NewsController tillägger category+pinned+datetime på alla auto-genererade events + stöder news-tabellen + kategorifiltrering. Migration: 2026-03-04_news_category.sql. Bygg OK. Commit: 4d2e22f.

- **2026-03-04**: Worker: Stopporsaks-logg (/stopporsaker) — Excel-export (SheetJS, kolumnbredder, filtrerad data), CSV-export uppdaterad, kompakt statistikrad (total stopptid/antal händelser/vanligaste orsak/snitt), avancerad filterrad (fr.o.m–t.o.m datumintervall + kategori-dropdown + snabbval Idag/Denna vecka/30d), inline-redigering (Redigera-knapp per rad, varaktighet+kommentar editerbart inline), tidsgräns-badge per rad (Kort <5min grön / Medel 5-15min gul / Långt >15min röd), Backend: duration_minutes direkt updatebar. Bygg OK. Commit: 4d2e22f.

- **2026-03-04**: Worker: Rebotling-admin — produktionsöversikt idag (today-snapshot endpoint, kompakt KPI-rad, polling 30s, färgkodad grön/orange/röd), alert-tröskelkonfiguration (kollapsbar panel, 6 trösklar OEE/prod/PLC/kvalitet, sparas JSON i rebotling_settings.alert_thresholds), veckodagsmål förbättring (kopieringsknapp mån-fre→helg, snabbval "sätt alla till X", idag-märkning med grön/röd status mot snapshot). Backend: 3 nya endpoints (GET alert-thresholds, POST save-alert-thresholds, GET today-snapshot), ALTER TABLE auto-lägger alert_thresholds-kolumn. Bygg OK. Commit: b2e2876.

- **2026-03-04**: Worker: My-bonus achievements — personal best (IBC/h, kvalitet%, bästa skift senaste 365d), streak dagräknare (nuvarande + längsta 60d, pulsanimation vid >5 dagar), achievement-medaljer grid (6 medaljer: Guldnivå/Snabbaste/Perfekt kvalitet/Veckostreak/Rekordstjärna/100 IBC/skift), gråtonade låsta / guldfärgade upplåsta. Backend: BonusController run=personal-best + run=streak. Bygg OK. Commit: af36b73.

- **2026-03-04**: Worker: Veckorapport (/rapporter/vecka) — ny VD-sida, WeeklyReportController (ISO-vecka parse, daglig MAX/SUM-aggregering, operatörsranking UNION ALL op1/op2/op3, veckomål från rebotling_settings), weekly-report.ts standalone Angular-komponent (inline template+styles), 6 KPI-kort (Total IBC, Kvalitet%, IBC/h, Drifttid, Veckans mål%, Dagar på mål), daglig stapeldiagram Chart.js med dagsmål-referenslinje, bästa/sämsta dag-kort, operatörsranking guld/silver/brons, veckonavigering (prev/next), PDF-export window.print(). api.php: weekly-report registrerat. Fix: production-analysis.ts tooltip null→''. Bygg OK. Commit: 0be4dd3 (filer inkl. i 5ca68dd via concurrent agent).

- **2026-03-04**: Worker: Produktionsanalys förbättring — riktig stoppdata stoppage_log, KPI-rad (total stoppid/antal/snitt/värst kategori), daglig staplat stapeldiagram färgkodat per kategori, topplista stopporsaker med kategori-badge, periodväljare 7/14/30/90 dagar, graceful empty-state när tabeller saknas, tidslinje behålls. Migration: stoppage_log+stoppage_reasons tabeller + 11 grundorsaker. angular.json budget 16→32kB. Commit: 5ca68dd.

- **2026-03-04**: Worker: Executive dashboard — insikter+åtgärder auto-analys, OEE-trend-varning (7 vs 7 dagar), dagsmålsprognos, stjärnoperatör, rekordstatus. Backend: run=insights i RebotlingController. Frontend: loadInsights(), insights[]-array, färgkodade insiktskort (danger/warning/success/info/primary). Bygg OK. Commit: c75f806.

- **2026-03-04**: Worker: Underhållslogg ny sida — MaintenanceController (list/add/update/delete/stats, admin-skydd, soft-delete), maintenance_log tabell (SQL-migrering), Angular standalone-komponent MaintenanceLogPage med dark theme, KPI-rad (total tid/kostnad/akuta/pågående), filter (linje/status/fr.o.m datum), CRUD-formulär (modal-overlay), färgkodade badges. api.php uppdaterad. Bygg OK. Commit: 12b1ab5.

- **2026-03-04**: Worker: Bonus-dashboard förbättring — Hall of Fame (IBC/h/kvalitet%/antal skift topp-3 senaste 90d, guld/silver/brons gradient-kort, avatar-initialer), löneprojekton per operatör (tier-matching Outstanding/Excellent/God/Bas/Under, SEK-prognos, månadsframsteg), periodval i ranking-headern (Idag/Denna vecka/Denna månad). Backend: run=hall-of-fame + run=loneprognos i BonusController. bonus.service.ts utökad med interfaces + metoder. Bygg OK. Commit: 310b4ad.

- **2026-03-04**: Worker: Produktionshändelse-annotationer i OEE-trend och cykeltrend — production_events tabell (SQL-migrering), getEvents/addEvent/deleteEvent endpoints i RebotlingController, ProductionEvent interface + HTTP-metoder i rebotling.service.ts, vertikala annotationslinjer i graferna med färgkodning per typ (underhall=orange, ny_operator=blå, mal_andring=lila, rekord=guld), admin-panel (kollapsbar, *ngIf=isAdmin) längst ner på statistiksidan. Bygg OK. Commit: 310b4ad.

- **2026-03-04**: Worker: Certifieringssida förbättring — kompetensmatris-vy (flik Kompetensmatris, tabell op×linje, grön/orange/röd celler med tooltip), snart utgångna-sektion (orange panel < 30 dagar, sorterat), statistiksammanfattning-rad (Totalt/Giltiga/Snart utgår/Utgångna), CSV-export (BOM UTF-8, alla aktiva certifieringar), fliknavigation (Operatörslista|Kompetensmatris), sorteringsval (Namn|Utgångsdatum), utgångsdatum inline i badge-rad, KPI-rad utökad till 5 brickor. Backend: CertificationController GET run=matrix. Bygg OK. Commit: 438f1ef.

- **2026-03-04**: Worker: Såglinje+Klassificeringslinje statistik+skiftrapport förbättring — 6 KPI-kort (Total IBC, Kvalitet%, Antal OK, Kassation, Snitt IBC/dag, Bästa dag IBC), OEE-trendgraf panel med Chart.js dual-axel (Kvalitet% vänster, IBC/dag höger), WCM 85% referenslinje, ej-i-drift-banner. Skiftrapport: 6 sammanfattningskort + empty-state. Backend: SaglinjeController + KlassificeringslinjeController GET run=oee-trend&dagar=N. Bonus: CertificationController GET run=matrix + Tvättlinje admin WeekdayGoal-stöd. Bygg: OK. Commit: 0a398a9.

- **2026-03-04**: Worker: Skiftöverlämning förbättring — kvittens (acknowledge endpoint + optimistic update), 4 filterflikar (Alla/Brådskande/Öppna/Kvitterade) med räknarbadge, sammanfattningsrad med totaler, timeAgo() klientsida, audience-dropdown (Alla/Ansvarig/Teknik), char-counter 500-gräns, auto-fokus på textarea, formulär minimera/expandera. SQL-migrering: acknowledged_by/at + audience-kolumn. Bygg OK. Commit 1540fcc.

- **2026-03-04**: Worker: Live-ranking förbättring — rekordindikator (gyllene REKORDDAG!/Nara rekord!/Bra dag! med glow-animation), teamtotal-sektion (LAG IDAG X IBC + dagsmål + procent + progress bar), skiftprognos (taktbaserad slutprognos, visas efter 1h av skiftet), skiftnedräkning i header (HH:MM kvar, uppdateras varje minut), kontextuella roterande motivationsmeddelanden (3 nivåer: >100%/80-100%/<80%, byter var 10s). Backend getLiveRanking utökad med ibc_idag_total, rekord_ibc, rekord_datum. Bygg OK. Commit 1540fcc.

- **2026-03-04**: Worker: Andon-tavla förbättring — skifttimer nedräkning (HH:MM:SS kvar av skiftet 06–22, progress-bar, färgkodad), senaste stopporsaker (ny andon&run=recent-stoppages endpoint, stoppage_log JOIN stoppage_reasons 24h, kategorifärger, tom-state, 30s polling), produktionsprognosbanner (taktbaserad slutprognos, 4 nivåer rekord/ok/warn/critical, visas efter 1h). Bygg OK. Commit 8fac87f.

- **2026-03-04**: Worker: Bug hunt #5 — 5 buggar fixade: (1) menu.ts updateProfile() HTTP POST saknade takeUntil/timeout/catchError — minnesläcka fixad; (2+3) SaglinjeController.php session_start() utan PHP_SESSION_NONE-guard på 2 ställen + saknad datumvalidering i getStatistics(); (4+5) TvattlinjeController.php session_start() utan guard på 3 ställen + saknad datumvalidering i getStatistics(). Alla andra granskade komponenter (historik, andon, shift-handover, operators, operator-dashboard, monthly-report, shift-plan, certifications, benchmarking, production-analysis) bedöms rena. Bygg OK. Commit: 0092eaf.

- **2026-03-04**: Worker: Operatörsjämförelse (/admin/operator-compare) — KPI-tabell sida-vid-sida (total IBC, kvalitet%, IBC/h, antal skift, drifttid), vinnare markeras grön, veckovis trendgraf senaste 8 veckor (Chart.js, blå=Op A, orange=Op B), periodväljare 14/30/90d. Backend: OperatorCompareController.php (operators-list + compare, MAX/MIN per-skifts-aggregering, admin-krav). api.php: operator-compare registrerat. Bygg: OK. Commit + push: b63feb9.

- **2026-03-04**: Worker-agent — Feature: Tvättlinje statistik+skiftrapport förbättring. Frontend tvattlinje-statistik: 6 KPI-kort (tillagd Snitt IBC/dag 30d och Bästa dag), OEE-trendgraf panel (Chart.js linjegraf, Kvalitet%+IBC/dag, WCM 85% referenslinje, välj 14/30/60/90d), graceful empty-state 'ej i drift'-banner när backend returnerar tom data. Frontend tvattlinje-skiftrapport: utökat från 4 till 6 sammanfattningskort (Total IBC, Snitt IBC/skift tillagda). Backend TvattlinjeController: ny endpoint GET ?run=report&datum=YYYY-MM-DD (daglig KPI-sammanfattning) + GET ?run=oee-trend&dagar=N (daglig statistik N dagar) — båda returnerar graceful empty-state om linjen ej är i drift. Bygg: OK (inga fel, bara pre-existing warnings). Commit: ingick i 287c8a3.

- **2026-03-04**: Worker: Kvalitetstrendkort (7-dagars rullande snitt, KPI-brickor, periodväljare 14/30/90d) + OEE-vattenfall (staplat bar-diagram, KPI-brickor A/P/Q/OEE, förlustvis uppdelning) i rebotling-statistik — redan implementerat i tidigare session, bygg verifierat OK.

- **2026-03-04**: Worker-agent — Feature: Historisk jämförelse (/rebotling/historik). Ny publik sida med 3 KPI-kort (total IBC innevarande år, snitt/månad, bästa månaden), stapeldiagram per månad (grön=över snitt, röd=under snitt), år-mot-år linjegraf per ISO-vecka (2023-2026), detaljerad månadsstabell med trend-pilar. Backend: HistorikController.php (monthly+yearly endpoints, publik). Frontend: historik.ts standalone Angular+Chart.js, OnInit+OnDestroy+destroy$+takeUntil+timeout(8000). Route: /rebotling/historik utan authGuard. Nav-länk i Rebotling-dropdown. Bygg: OK. Commit + push: 4442ed5.

- **2026-03-04**: Bug Hunt #4 — Fixade subscription-läckor och PHP session-bugg. Detaljer: (1) news.ts: fetchRebotlingData/fetchTvattlinjeData — 4 subscriptions saknade takeUntil(destroy$), nu fixat. (2) menu.ts: loadLineStatus forkJoin och loadVpnStatus saknade takeUntil(destroy$), loadVpnStatus saknade även timeout+catchError — nu fixat; null-guard tillagd i next-handler. (3) KlassificeringslinjeController.php: session_start() anropades utan session_status()-check i POST-handlarna för settings och weekday-goals — ersatt med if (session_status() === PHP_SESSION_NONE) session_start(). (4) bonus-admin.ts: 8 subscriptions (getSystemStats, getConfig, updateWeights, setTargets, setWeeklyGoal, getOperatorForecast, getPeriods, approveBonuses) saknade takeUntil(destroy$) — nu fixat. Bygg: OK. Commit + push: ja.

- **2026-03-04**: Worker-agent — Feature: Månadsrapport förbättring. Backend (RebotlingController): lade till basta_vecka, samsta_vecka, oee_trend, top_operatorer, total_stopp_min i monthly-report endpoint. Frontend (monthly-report): ny OEE-trend linjegraf (monthlyOeeChart, grön linje + WCM 85% streckad referens), topp-3 operatörer-sektion (medallängd + IBC), bästa/sämsta vecka KPI-kort, total stillestånd KPI-kort, markerade bäst/sämst-rader i veckosammanfattning. Bygg: OK. Commit + push: pågår.

- **2026-03-04**: Worker-agent — Feature: Klassificeringslinje förberedelsearbete inför driftsättning. Ny KlassificeringslinjeController.php med getSettings/setSettings, getWeekdayGoals/setWeekdayGoals, getSystemStatus (returnerar "Linjen ej i drift"), stub-metoder för live/statistik/running-status. Ny klassificeringslinje-admin Angular-sida (KlassificeringslinjeAdminPage) med EJ I DRIFT-banner, systemstatus-kort, driftsinställningsformulär, veckodagsmål-tabell. Migration: 2026-03-04_klassificeringslinje_settings.sql. Route/meny lämnas åt annan agent. Bygg: OK. Commit + push: d01b2d8.

- **2026-03-04**: Worker-agent — Feature: Såglinje förberedelsearbete inför driftsättning. Ny SaglinjeController.php med getSettings/setSettings, getWeekdayGoals/setWeekdayGoals, getSystemStatus (returnerar 'Linjen ej i drift'). Ny saglinje-admin sida (Angular standalone component) med EJ I DRIFT-banner, systemstatus-kort, driftsinställningsformulär, veckodagsmål-tabell. Route /saglinje/admin (adminGuard) och nav-länk i Såglinje-dropdown. Migration: 2026-03-04_saglinje_settings.sql med saglinje_settings + saglinje_weekday_goals. Bygg: OK.

- **2026-03-04**: Worker-agent — Feature: Notifikationsbadge i navbar för urgenta skiftöverlämningsnotat. Röd badge visas på Rebotling-dropdown och Skiftöverlämning-länken när urgenta notat finns (12h). Backend: ny endpoint shift-handover&run=unread-count, kräver inloggad session. Frontend: urgentNoteCount + loadUrgentCount() + notifTimer (60s polling, takeUntil, timeout 4s, catchError). Fix: WeekdayStatsResponse-interface i rebotling.service.ts flyttad till rätt position (före klassen) för att lösa pre-existing build-fel.

---

- **2026-03-04**: Worker-agent — Feature: Veckodag-analys i rebotling-statistik. Stapeldiagram visar snitt-IBC per veckodag (mån-lör), bästa dag grön, sämsta röd. Datatabell med max/min/OEE/antal dagar. Backend: getWeekdayStats() endpoint i RebotlingController.php, aggregerar per skift->dag->veckodag. Frontend: ny sektion längst ner på statistiksidan, weekdayChart canvas (nytt ID, ingen konflikt). Byggt + committat + pushat.

---

## 2026-03-04 — Excel-export förbättring (worker-agent)
- Förbättrade `exportExcel()` i rebotling-skiftrapport, tvattlinje-skiftrapport och saglinje-skiftrapport
- Använder nu `aoa_to_sheet` med explicit header-array + data-rader (istället för `json_to_sheet`)
- Kolumnbredder (`!cols`) satta för alla ark — anpassade per kolumntyp (ID smal, kommentar bred 40ch)
- Fryst header-rad (`!freeze` ySplit:1) i alla ark — scrolla ned utan att tappa kolumnnamnen
- Rebotling: sammanfattningsbladet fick också kolumnbredder och fryst header
- Filnamn uppdaterat med prefix `rebotling-` för tydlighet
- Bygg OK, inga nya fel



## 2026-03-04 — Feature: Operatörsdashboard — commit 4fb35a1
Worker-agent byggde /admin/operator-dashboard: adminvy för skiftledare med 4 KPI-kort (aktiva idag, snitt IBC/h, bäst idag, totalt IBC) och operatörstabell med initialer-avatar (hash-färg), IBC/h, kvalitet%, minuter sedan aktivitet och status-badge (Bra/OK/Låg/Inaktiv). Backend: OperatorDashboardController.php med UNION ALL op1/op2/op3 från rebotling_skiftrapport. 60s polling. Bygg OK, pushad till GitHub.
---
## 2026-03-04 — Feature: OEE WCM referenslinjer — commit 6633497

- `rebotling-statistik.ts`: WCM 85% (grön streckad) och Branschsnitt 70% (orange streckad) tillagda som referenslinjer i OEE-trend-grafen
- `rebotling-statistik.html`: Legend med dashed-linjer visas ovanför OEE-trendgrafen
- `environments/environment.ts`: Skapad (pre-existing build-fel fixat, saknad fil)



## 2026-03-03 23:07 — Bug hunt #3: 6 buggar fixade — commit 20686bb

- `shift-plan.ts`: Saknat `timeout()` + `catchError` på alla 4 HTTP-anrop — HTTP-anrop kunde hänga oändligt
- `live-ranking.ts`: Saknat `withCredentials: true` — session skickades ej till backend
- `live-ranking.ts`: Redundant `Subscription`/`dataSub`-pattern borttagen (takeUntil hanterar cleanup)
- `production-calendar.ts`: Saknat `withCredentials: true` — session skickades ej till backend
- `benchmarking.ts`: setTimeout-referens sparas nu i `chartTimer` och clearas i ngOnDestroy — förhindrar render på förstörd komponent
- `CertificationController.php`: `session_status()`-kontroll saknad före `session_start()` — PHP-varning om session redan aktiv

---
## 2026-03-03 — Digital skiftöverlämning — commit ca4b8f2

### Nytt: /rebotling/overlamnin

**Syfte:** Ersätter muntlig informationsöverföring vid skiftbyte med en digital överlämningslogg.
Avgående skift dokumenterar maskinstatus, problem och uppgifter. Inkommande skift ser de tre
senaste dagarnas anteckningar direkt när de börjar.

**Backend — `noreko-backend/classes/ShiftHandoverController.php` (ny):**

- `GET &run=recent` — hämtar senaste 3 dagars anteckningar (max 10), sorterat nyast först.
  - Returnerar `time_ago` på svenska ("2 timmar sedan", "Igår", "3 dagar sedan").
  - `skift_label` beräknas: "Skift 1 — Morgon" etc.
- `POST &run=add` — sparar ny anteckning. Kräver inloggad session (`$_SESSION['user_id']`).
  - Validering: note max 1000 tecken, skift_nr 1–3, priority whitelist.
  - Slår upp op_name mot operators-tabellen om op_number angivits.
  - Returnerar det nyskapade note-objektet direkt för optimistisk UI-uppdatering.
- `POST/DELETE &run=delete&id=N` — tar bort anteckning.
  - Kräver admin ELLER att `created_by_user_id` matchar inloggad användare.

**DB — `noreko-backend/migrations/2026-03-04_shift_handover.sql`:**
- Ny tabell `shift_handover` med id, datum, skift_nr, note, priority (ENUM), op_number,
  op_name, created_by_user_id, created_at. Index på datum och (datum, skift_nr).

**Frontend — `noreko-frontend/src/app/pages/shift-handover/` (ny):**
- Standalone-komponent med `destroy$ + takeUntil`, `isFetching`-guard, `clearInterval` i ngOnDestroy.
- Header visar aktuellt skift baserat på klockslag (06–14 = Morgon, 14–22 = Eftermiddag, 22–06 = Natt).
- Formulärpanel alltid synlig: textarea (max 1000 tecken), toggle-knappar för Normal/Viktig/Brådskande,
  skift-selector (auto-satt men justerbar), skicka-knapp.
- Anteckningskort: prioritetsfärgad vänsterkant (grå/orange/röd), skift-badge, datum, anteckningstext,
  operatörsnamn, time_ago. Radera-knapp visas om admin eller ägare.
- Auto-poll var 60s med `timeout(5000)` + `catchError`.
- "Uppdaterades XX:XX" i header efter varje lyckad fetch.

**Routing & nav:**
- Route: `{ path: 'rebotling/overlamnin', canActivate: [authGuard], ... }` i `app.routes.ts`.
- Nav-länk under Rebotling-dropdown (ikon: `fas fa-exchange-alt`, synlig för inloggade).

---

## 2026-03-03 — Kvalitetstrendkort + Waterfalldiagram OEE — commit d44a4fe

### Nytt: Två analysvyer i Rebotling Statistik

**Syfte:** VD vill se om kvaliteten försämras gradvis (Kvalitetstrendkort) och förstå exakt VAR OEE-förlusterna uppstår (Waterfalldiagram OEE).

**Backend — `noreko-backend/classes/RebotlingController.php`:**

- `GET ?action=rebotling&run=quality-trend&days=N` (ny endpoint):
  - SQL med MAX-per-skift-mönster, aggregerat per dag.
  - 7-dagars rullande medelvärde beräknat i PHP med array_slice/array_sum.
  - KPI: snitt, min, max, trendindikator (up/down/stable) via jämförelse sista 7 d mot föregående 7 d.
  - Returnerar `{ success, days: [{date, quality_pct, rolling_avg, ibc_ok, ibc_totalt}], kpi }`.

- `GET ?action=rebotling&run=oee-waterfall&days=N` (ny endpoint):
  - MAX-per-skift-aggregat för runtime_plc, rasttime, ibc_ok, ibc_ej_ok.
  - Tillgänglighet = runtime / (runtime + rast) * 100.
  - Prestanda = (ibc_ok * 4 min) / runtime * 100 (15 IBC/h standard, cap vid 100).
  - Kvalitet = ibc_ok / ibc_totalt * 100.
  - OEE = A * P * Q / 10000.
  - Returnerar alla komponenter + förluster (availability_loss, performance_loss, quality_loss).

**Service — `noreko-frontend/src/app/services/rebotling.service.ts`:**
- `getQualityTrend(days)` och `getOeeWaterfall(days)` metoder.
- Nya interfaces: `QualityTrendDay`, `QualityTrendResponse`, `OeeWaterfallResponse`.

**Frontend — `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts`:**
- Properties: `qualityTrendChart`, `qualityTrendDays=30`, `qualityTrendData`, `qualityTrendKpi`, `oeeWaterfallChart`, `oeeWaterfallDays=30`, `oeeWaterfallData`.
- `loadQualityTrend()`: hämtar data via service, renderar Chart.js linjegraf.
- `renderQualityTrendChart()`: canvas `qualityTrendChart`, 3 datasets (daglig/rullande/mållinje), Y 0-100%.
- `loadOeeWaterfall()`: hämtar data, renderar horisontellt stacked bar chart.
- `renderOeeWaterfallChart()`: canvas `oeeWaterfallChart`, grön+grå stack, indexAxis 'y'.
- Båda charts destroyed i ngOnDestroy. Laddas i ngOnInit.

**HTML — `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.html`:**
- Kvalitetstrendkort: dagar-väljare 14/30/90, 4 KPI-brickor (snitt/lägsta/bästa/trend med pil-ikon), linjegraf 300px.
- Waterfalldiagram OEE: dagar-väljare 7/30/90, OEE-summering, 4 KPI-brickor med förlust-siffror och färgkodning, horisontellt bar chart 260px.

---

## 2026-03-03 — Operatörscertifiering — commit 22bfe7c

### Nytt: /admin/certifiering — admin-sida för linjecertifikat

**Syfte:** Produktionschefen behöver veta vilka operatörer som är godkända att köra respektive linje. Sidan visar certifieringsstatus med färgkodade badges och flaggar utgångna eller snart utgående certifieringar.

**Backend — `noreko-backend/migrations/2026-03-04_certifications.sql`:**
- Ny tabell `operator_certifications`: op_number, line, certified_by, certified_date, expires_date, notes, active, created_at.
- Index på op_number, line och expires_date.

**Backend — `noreko-backend/classes/CertificationController.php`:**
- `GET &run=all` — hämtar alla certifieringar, JOIN mot operators för namn, grupperar per operatör. Beräknar `days_until_expiry` i PHP: `(strtotime(expires_date) - time()) / 86400`. Negativa = utgången, NULL = ingen utgångsgräns.
- `POST &run=add` — lägger till certifiering, validerar linje mot whitelist och datumformat. Kräver admin-session.
- `POST &run=revoke` — sätter active=0 på certifiering. Kräver admin-session.
- Registrerad i `api.php` under nyckeln `certifications`.

**Frontend — `noreko-frontend/src/app/pages/certifications/`:**
- `certifications.ts`: Standalone-komponent med destroy$/takeUntil. KPI-beräkningar (totalCertifiedOperators, expiringSoon, expired) som getters. Avatar-funktioner (getInitials/getAvatarColor) kopierade från operators-sidan. Badge-klassificering: grön (>30 d kvar eller ingen gräns), orange (≤30 d), röd (utgången, strikethrough).
- `certifications.html`: Sidhuvud, varningsbanner (visas om expired>0 eller expiringSoon>0), KPI-brickor, linje-filterknappar, operatörskort-grid, kollapsbart lägg till-formulär. Återkalla-knapp per certifiering med confirm-dialog.
- `certifications.css`: Dark theme (#1a202c/#2d3748), responsivt grid, badge-stilar, avatar-cirkel.

**Routing + Nav:**
- Route `admin/certifiering` med `adminGuard` i `app.routes.ts`.
- Nav-länk med `fas fa-certificate`-ikon under Admin-dropdown i `menu.html`.

---

## 2026-03-03 — Annotationer i OEE-trend och cykeltrend-grafer — commit 078e804

### Nytt: Vertikala annotationslinjer i rebotling-statistik

**Syfte:** VD och produktionschefen ska direkt i OEE-trendgrafen och cykeltrendgrafen kunna se varför en dal uppstod — t.ex. "Lång stopptid: 3.2h" eller "Låg prod: 42 IBC". Annotationer förvandlar grafer från datapunkter till berättande verktyg.

**Backend — `noreko-backend/classes/RebotlingController.php`:**
- Ny metod `getAnnotations()` + dispatch `elseif ($action === 'annotations')`.
- Endpoint: `GET ?action=rebotling&run=annotations&start=YYYY-MM-DD&end=YYYY-MM-DD`
- Tre datakällor i separata try-catch:
  1. **Stopp** — `rebotling_skiftrapport` GROUP BY dag, HAVING SUM(rasttime) > 120 min. Label: "Lång stopptid: Xh".
  2. **Låg produktion** — samma tabell, HAVING SUM(ibc_ok) < (dagsmål/2). Label: "Låg prod: N IBC". Deduplicerar mot stopp-annotationer.
  3. **Audit-log** — kontrollerar `information_schema.tables` om tabellen finns, hämtar CREATE/UPDATE-händelser (LIMIT 5). Svenska etiketter i PHP-mappning.
- Returnerar: `{ success: true, annotations: [{ date, type, label }] }`.
- Fel i valfri källa loggas med `error_log()` — övriga källor returneras ändå.

**Service — `noreko-frontend/src/app/services/rebotling.service.ts`:**
- Ny metod `getAnnotations(startDate, endDate)` → `GET ?action=rebotling&run=annotations`.
- Nytt interface `ChartAnnotation { date, dateShort, type, label }`.
- Nytt interface `AnnotationsResponse { success, annotations?, error? }`.

**Frontend — `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts`:**
- Custom Chart.js-plugin `annotationPlugin` (id: `'verticalAnnotations'`) definieras och registreras globalt med `Chart.register()`.
  - `afterDraw` ritar en streckad vertikal linje (röd=stopp, orange=low_production, grön=audit) på x-axeln via `getPixelForValue(xIndex)`.
  - Etikett (max 20 tecken) ritas 3px till höger om linjen, 12px under grafens övre kant.
- Ny class-property: `chartAnnotations: ChartAnnotation[] = []`.
- Ny metod `loadAnnotations(startDate, endDate)` med `timeout(8000)` + `takeUntil(this.destroy$)` + `catchError(() => of(null))`. Mappar API-svar till `ChartAnnotation[]` (lägger till `dateShort = date.substring(5)`). Vid framgång renderas OEE-trend och/eller cykeltrend om om de redan är inladdade.
- `loadOEE()`: beräknar start/end-datum (senaste 30 dagar) och anropar `loadAnnotations()` innan OEE-datan hämtas.
- `loadCycleTrend()`: anropar `loadAnnotations()` om `chartAnnotations.length === 0` (undviker dubbelanrop).
- `renderOEETrendChart()` och `renderCycleTrendChart()`: skickar `verticalAnnotations: { annotations: this.chartAnnotations }` i `options.plugins` (castat med `as any` för TypeScript-kompatibilitet).

---

## 2026-03-03 — Korrelationsanalys — bästa operatörspar — commit ad4429e

### Nytt: Sektion "Bästa operatörspar — korrelationsanalys" i `/admin/operators`

**Syfte:** VD och skiftledare ska kunna se vilka operatörspar som presterar bäst tillsammans, baserat på faktisk produktionsdata. Ger underlag för optimal skiftplanering.

**Backend — `noreko-backend/classes/OperatorController.php`:**
- Ny privat metod `getPairs()` + dispatch `$run === 'pairs'`.
- Endpoint: `GET ?action=operators&run=pairs`
- SQL: UNION ALL av alla tre parvisa kombinationer (op1/op2, op1/op3, op2/op3) från `rebotling_skiftrapport` (senaste 90 dagar).
- Grupperar på `LEAST(op_a, op_b) / GREATEST(op_a, op_b)` → normaliserade par.
- `HAVING shifts_together >= 3`, `ORDER BY avg_ibc_per_hour DESC`, `LIMIT 20`.
- JOIN mot `operators`-tabellen för namn på respektive operatörsnummer.
- Returnerar: `op1_num`, `op1_name`, `op2_num`, `op2_name`, `shifts_together`, `avg_ibc_per_hour`, `avg_quality`.

**Service — `noreko-frontend/src/app/services/operators.service.ts`:**
- Ny metod `getPairs()` → `GET ?action=operators&run=pairs`.

**Frontend — `noreko-frontend/src/app/pages/operators/`:**
- `operators.ts`: tre nya properties (`pairsData`, `pairsLoading`, `showPairs`) + metod `loadPairs()` med `timeout(8000)` + `catchError` + `takeUntil(destroy$)`. Anropas i `ngOnInit`.
- `operators.html`: ny toggle-sektion med responsivt `.pairs-grid` — visar parvisa avatarer (återanvänder `getInitials()` / `getAvatarColor()`), namn och tre stat-pills (IBC/h, kvalitet%, antal skift).
- `operators.css`: `.pairs-grid`, `.pair-card`, `.pair-avatar`, `.pair-plus`, `.pair-name-text`, `.pair-stats`, `.pair-stat-pill` + varianter `.pair-stat-ibc` / `.pair-stat-quality` / `.pair-stat-shifts`. Fullständigt responsivt för mobile.

---

## 2026-03-03 — Prediktiv underhållsindikator i rebotling-admin — commit 153729e

### Nytt: Sektion "Maskinstatus & Underhållsprediktor" i `/admin/rebotling`

**Syfte:** Produktionschefen ska tidigt se om cykeltiden ökar stadigt under de senaste veckorna — ett tecken på maskinslitage (ventiler, pumpar, dubbar). En tidig varning förebygger haveri och produktionsstopp.

**Backend — `noreko-backend/classes/RebotlingController.php`:**
- Ny metod `getMaintenanceIndicator()` + dispatch `elseif ($action === 'maintenance-indicator')`.
- Endpoint: `GET ?action=rebotling&run=maintenance-indicator`
- SQL: aggregerar `MAX(ibc_ok)` + `MAX(runtime_plc)` per `(DATE, skiftraknare)` → summerar per vecka (senaste 8 veckor, 56 dagar).
- Cykeltid = `SUM(shift_runtime) / SUM(shift_ibc)` (minuter per IBC).
- Baslinje = snitt av de 4 första veckorna. Aktuell = senaste veckan.
- Status: `ok` / `warning` (>15% ökning) / `danger` (>30% ökning).
- Returnerar: `status`, `message`, `weeks[]`, `baseline_cycle_time`, `current_cycle_time`, `trend_pct`.

**Frontend — `noreko-frontend/src/app/pages/rebotling-admin/rebotling-admin.ts` + `.html`:**
- Ny sektion (card) längst ned på admin-sidan — INTE en ny flik.
- `Chart.js` linjegraf: orange linje för cykeltid per vecka + grön streckad baslinje.
- KPI-brickor: baslinje, aktuell cykeltid, trend-% (färgkodad grön/gul/röd).
- Statusbanner: grön vid ok, gul vid warning, röd vid danger.
- Polling var 5 min via `setInterval` + `clearInterval` i `ngOnDestroy`.
- `takeUntil(this.destroy$)` + `timeout(8000)` + `catchError`.
- `maintenanceChart?.destroy()` i `ngOnDestroy` för att undvika memory-läcka.
- `ngAfterViewInit` implementerad för att rita om grafen om data redan är laddad.

---

## 2026-03-03 — Månadsrapport med PDF-export — commit e9e7590

### Nytt: `/rapporter/manad` — auto-genererad månadsöversikt

**Syfte:** VD vill ha en månadssammanfattning att dela med styrelsen eller spara som PDF. Visar total produktion, OEE-snitt, bästa/sämsta dag, operatörsranking och veckoöversikt.

**Backend — `noreko-backend/classes/RebotlingController.php`:**
- Ny metod `getMonthlyReport()` + dispatch `elseif ($action === 'monthly-report')`.
- Endpoint: `GET ?action=rebotling&run=monthly-report&month=YYYY-MM`
- Aggregering med korrekt `MAX() per (DATE, skiftraknare)` → `SUM()` på per-skift-undernivå.
- OEE beräknas per dag med `Availability × Performance × Quality`-formeln.
- Månadsnamn på svenska (Januari–December).
- Månadsmål: `dagsmål × antal vardagar i månaden` (hämtat från `rebotling_settings`).
- Operatörsranking: UNION på `op1/op2/op3` i `rebotling_skiftrapport` + JOIN `operators`, sorterat på IBC/h.
- Returnerar: `summary`, `best_day`, `worst_day`, `daily_production`, `week_summary`, `operator_ranking`.

**Frontend — `noreko-frontend/src/app/pages/monthly-report/`:**
- Standalone Angular-komponent (`MonthlyReportPage`), `OnInit + OnDestroy + AfterViewChecked`.
- `destroy$` + `takeUntil`, `chart?.destroy()` i `ngOnDestroy`.
- **Sektion 1:** 6 KPI-kort i CSS-grid — Total IBC, Mål-%, Snitt IBC/dag, Produktionsdagar, Snitt Kvalitet, Snitt OEE — med färgkodning grön/gul/röd.
- **Sektion 2:** Chart.js stapeldiagram (en stapel per dag, färgad efter % av dagsmål) + kvalitets-linje på höger Y-axel.
- **Sektion 3:** Bästa/sämsta dag sida vid sida (grön/röd vänsterbård).
- **Sektion 4:** Operatörsranking — guld/silver/brons för topp 3.
- **Sektion 5:** Veckosammanfattningstabell.
- **Sektion 6:** PDF-export via `window.print()` + `@media print` CSS (ljus bakgrund, döljer navbar/knappar).

**Routing & Nav:**
- Route: `{ path: 'rapporter/manad', canActivate: [authGuard], ... }` i `app.routes.ts`.
- Nytt "Rapporter"-dropdown i menyn (synligt för inloggade) med länk "Månadsrapport" → `/rapporter/manad`.

---

## 2026-03-03 — Benchmarking-vy: Denna vecka vs Rekordveckan — commit 9001021

### Nytt: `/rebotling/benchmarking` — rekordtavla och historik

**Syfte:** VD och operatörer motiveras av att se rekord och kunna jämföra innevaranda vecka mot den bästa veckan någonsin. Skapar tävlingsanda och ger historisk kontext.

**Backend — `noreko-backend/classes/RebotlingController.php`:**
- Ny metod `getBenchmarking()` + dispatch `elseif ($action === 'benchmarking')`.
- Returnerar ett objekt med fem nycklar: `current_week`, `best_week_ever`, `best_day_ever`, `top_weeks` (topp-10 veckor), `monthly_totals` (senaste 13 månader).
- Korrekt aggregering: `MAX() per (DATE, skiftraknare)` → `SUM() per vecka/månad` (hanterar kumulativa PLC-fält).
- OEE beräknas inline (Availability × Performance × Quality) med `idealRatePerMin = 15/60`.
- Veckoetiketter: `V{wk} {yr}` med ISO-veckonummer (`WEEK(datum, 1)`).

**Service — `noreko-frontend/src/app/services/rebotling.service.ts`:**
- Ny metod `getBenchmarking()` → `GET ?action=rebotling&run=benchmarking`.
- Nya interfaces: `BenchmarkingWeek`, `BenchmarkingTopWeek`, `BenchmarkingMonthly`, `BenchmarkingBestDay`, `BenchmarkingResponse`.

**Frontend — `noreko-frontend/src/app/pages/benchmarking/` (3 nya filer):**
- `benchmarking.ts`: Standalone Angular 20 component, `OnInit + OnDestroy + destroy$ + takeUntil + clearInterval`, 60s polling.
- `benchmarking.html`: Fyra sektioner — KPI-kort, bästa dag, topp-10 tabell, månadsöversikt bar chart.
- `benchmarking.css`: Dark theme (`#1a202c`/`#2d3748`/`#e2e8f0`), guld-/blå-accenter, pulse-animation för nytt rekord.

**Sektion 1 — KPI-jämförelse:**
- Vänster kort (blå): innevar. vecka — IBC totalt, IBC/dag, Kvalitet%, OEE%, aktiva dagar.
- Höger kort (guld): rekordveckan — samma KPI:er.
- Diff-badge: "X IBC kvar till rekordet" eller "NYTT REKORD DENNA VECKA!" (pulserar).
- Progress-bar 0–100% med färgkodning (röd/orange/blå/grön).

**Sektion 2 — Bästa dagen:** Guldkort med datum, IBC-total, Kvalitet%.

**Sektion 3 — Topp-10 tabell:** Rank-ikoner (trophy/medal/award), guld-rad för rekordveckan, blå rad för innevarnade vecka, procentkolumn "Vs. rekord".

**Sektion 4 — Månadsöversikt Chart.js:** Bar chart, guld=bästa månaden, blå=innevarnade, röd streckad snittlinje. Tooltip visar Kvalitet%.

**Routing:** `app.routes.ts` — `{ path: 'rebotling/benchmarking', canActivate: [authGuard], loadComponent: ... }`.

**Nav:** `menu.html` — "Benchmarking"-länk (med trophy-ikon) under Rebotling-dropdown, synlig för inloggade användare.

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
[2026-03-03 23:00] Skiftkommentar-agent: kommentarsfält i skiftrapport levererat, commit 1feb15e
[2026-03-03 23:00] Andon-agent: Andon-tavla /rebotling/andon levererad, commit ddbade9
[2026-03-03 23:15] Bonusprognos-agent: bonus i kr levererat, commit e472997
[2026-03-03 23:05] Pareto-agent: Pareto-diagram stopporsaker levererat, commit 0f4865c

## 2026-03-04 — Worker: Senaste händelser på startsidan
- Lade till "Senaste händelser"-sektion i news.html (längst ner på startsidan)
- Uppdaterade NewsController.php: fallback-produktion visas alltid (ej bara om inga andra händelser), deduplicering av typ+datum, query för OEE-dagar begränsat till 14 dagar
- Skapade environments/environment.ts (saknades — orsakade byggfel för operator-dashboard)
- Bygget OK — inga errors, bara warnings

## 2026-03-04 — Feature: Tvattlinje forberedelse — backend + admin
- TvattlinjeController.php: Lade till `getSettings()`/`setSettings()` (key-value tabell `tvattlinje_settings`), `getSystemStatus()` (returnerar null-varden tills linjen ar i drift), `getWeekdayGoals()`/`setWeekdayGoals()` (individuella mal per veckodag i `tvattlinje_weekday_goals`)
- handle() utokad med routing for `settings`, `weekday-goals`, `system-status`
- Migration: `noreko-backend/migrations/2026-03-04_tvattlinje_settings.sql` skapad (tvattlinje_settings + tvattlinje_weekday_goals tabeller med defaultvarden)
- tvattlinje-admin.ts: Ny `WeekdayGoal`-interface, `loadWeekdayGoals()`/`saveWeekdayGoals()`, `loadNewSettings()`/`saveNewSettings()`, `loadSystemStatus()` nu mot `system-status` endpoint, `getPlcAge()`, `getDbStatusLabel()`
- tvattlinje-admin.html: Ny systemstatus-sektion med null-saker falt (PLC ej sedd = "---"), ny driftsinstellningar-sektion (dagmal/takt_mal/skift_start/skift_slut), ny veckodagsmaltabell (man-son med input + status-badge), "ej i drift"-banner
- Byggt OK, committat och pushat
[2026-03-04] Lead: Historik-agent klar (4442ed5+611dbff). Startar 3 workers: Kvalitetstrend+OEE-vattenfall (a35e472a), Operatörsjämförelse /admin/operator-compare (a746769c), Tvättlinje-statistik pågår (a59ff05a)
[2026-03-04] Lead: Operatörsjämförelse route+nav tillagd (fe14455) — /admin/operator-compare med adminGuard i app.routes.ts + menu.html
[2026-03-04] Worker: Live-ranking förbättring — rekordindikator guld/orange/gul, teamtotal+progress, prognos, skiftnedräkning, kontextuella motton — 1540fcc
[2026-03-04] Worker: Skiftöverlämning förbättring — kvittens+acknowledge, 4 filterflikar, sammanfattningsrad, audience-badge, timeAgo, kollapsbart formulär — se a938045f
[2026-03-04] Worker: Såglinje+Klassificeringslinje statistik+skiftrapport — 6 KPI-kort, OEE-trendgraf dual-axel, ej-i-drift-banner, WCM 85% referenslinje — 0a398a9
[2026-03-04] Worker: Certifieringssida — kompetensmatris (operatör×linje grid ✅⚠️❌), snart-utgångna-sektion, CSV-export, 5 KPI-brickor, 2 flikar — 438f1ef
[2026-03-04] Worker: Produktionshändelse-annotationer i OEE-trend — production_events tabell, admin-panel i statistik, triangelmarkeringar per typ — se a0594b1f
[2026-03-04] Worker: Bonus-dashboard — Hall of Fame (IBC/h/kvalitet/skift topp-3 guld/silver/brons), löneprojekton widget, Idag/Vecka/Månad periodväljare — 310b4ad
[2026-03-04] Lead: Underhållslogg route+nav tillagd (admin/underhall, adminGuard)
[2026-03-04] Worker: Executive dashboard — Insikter & Åtgärder (OEE-trend varning, dagsmålsprognos, stjärnoperatör, rekordstatus) — c75f806
[2026-03-04] Worker: Produktionsanalys — riktig stoppdata stoppage_log, KPI-rad 4 kort, dagligt staplat diagram (maskin/material/operatör/övrigt), topplista orsaker, tom-state — 5ca68dd
[2026-03-04] Lead: Veckorapport route+nav tillagd (/rapporter/vecka, authGuard)
[2026-03-04] Worker: My-bonus achievements — personal best (IBC/h/kvalitet/skift+datum), streak räknare (aktuell+längsta 60d), 6 achievement-medaljer (guld/grå), @keyframes streakPulse
[2026-03-04] Worker: Rebotling-admin — today-snapshot (6 KPI polling 30s), alert-trösklar (6 konfigurerbara, sparas JSON), veckodagsmål kopiering+snabbval+idag-märkning — b2e2876
[2026-03-04] Worker: Stopporsaks-logg — SheetJS Excel-export (filtrerad data), stats-bar (antal/total/snitt/vanligaste), filter (snabbval+datum+kategori), inline-redigering, tidsgräns-badge — 4d2e22f
[2026-03-04] Worker: Nyhetsflöde — kategorier (produktion/bonus/system/info/viktig)+badges, 👍✓ reaktioner localStorage, läs-mer expansion, timeAgo, pinnade nyheter gul kant
[2026-03-04] Worker: Rebotling-skiftrapport — shift-trend linjegraf timupplösning vs genomsnittsprofil, prev/next navigering — 6af3e1e
[2026-03-04] Worker: Produktionsanalys Pareto — ny flik "Pareto-analys (80/20)" med kombinationsdiagram (staplar+kumulativ %+röd 80%-linje), 3 KPI-brickor, period-toggle 7/30/90d, detaljlista med rangordning. Backend: pareto-stoppage endpoint i RebotlingController med kumulativ %-beräkning
[2026-03-04] Worker: Min Bonus — anonymiserad kollegajämförelse: ny "Din placering"-sektion med rank/#total/IBC-h/kvalitet%, progress bar mot toppen, period-toggle (Idag/Vecka/Månad), motivationstext per rank, backend my-ranking endpoint med auth-skydd (op_id måste matcha session operator_id)
[2026-03-04] Worker: Rebotling statistik — cykeltid per operatör: horisontellt Chart.js bar-diagram (indexAxis y), färgkodning mot median (grön/röd/blå), rang-tabell med snitt/bäst/sämst/antal skift/total IBC, period-selector 7/14/30/90d. Backend: cycle-by-operator endpoint i RebotlingController, UNION op1/op2/op3 från rebotling_skiftrapport, JOIN operators, outlier-filter 30-600 sek — 12ddddb
[2026-03-04] Worker: Notifikationscentral (klockikon) verifierad — redan implementerad i 022b8df. Bell-ikon i navbar för inloggade (loggedIn), badge med urgentNoteCount+certExpiryCount, dropdown med länk till overlamnin+certifiering, .notif-dropdown CSS, inga extra polling-anrop (återanvänder befintliga timers)
[2026-03-04] BugHunt #11: andon.ts — null-safety minuter_sedan_senaste_ibc (number|null + null-guard i statusEtikett), switch default-return i ibcKvarFarg/behovdTaktFarg; my-bonus.ts — chart-refs nullas i ngOnDestroy; news-admin.ts — withCredentials:true på alla HTTP-anrop (sessions kräver det för admin-list/create/update/delete); operator-trend.ts — oanvänd AfterViewInit-import borttagen; BonusController/BonusAdminController/MaintenanceController PHP — session_start read_and_close för att undvika session-låsning
[2026-03-04] Worker: Historik-sida — CSV/Excel-export (SheetJS), trendpil per månad (↑↓→ >3%), progressbar mot snitt per rad, ny Trend-kolumn i månadsdetaljatabell, disable-state på knappar — e6a36f5
[2026-03-04] Worker: Executive dashboard förbättringar — veckoframgångsmätare (IBC denna vecka vs förra, progressbar grön/gul/röd, OEE+kvalitet+toppop KPI-rad), senaste nyheter (3 senaste via news&run=admin-list, kategori-badges), 6 snabblänkar (Andontavla/Skiftrapport/Veckorapport/Statistik/Bonus/Underhåll), lastUpdated property satt vid lyckad fetch — 3d14b95
[2026-03-04] Worker: Benchmarking — emoji-medaljer (🥇🥈🥉) med glow-animationer, KPI-sammanfattning (4 brickor: veckor/rekord/snitt/OEE), personbästa-kort (AuthService-integration, visar stats om inloggad operatör finns i personalBests annars motiveringstext), CSV-export topplista (knapp i sidhuvud+sektion), rekordmånad guld-stjärnanimation i legend, silver+brons radmarkering i tabellen

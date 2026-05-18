# BUGS.md — Rapporterade buggar

## BUG-001: ibc_ok LAG()-subtraktion fel (FIXAD)
**Rapporterad:** 2026-05-15  
**Status:** Fixad, committad `a78eecaf`, deployad till dev  
**Symptom:** Statistik för operatörer och produktion visade för höga/fel IBC-värden på dagar med flera skift.  
**Rotorsak:** `ibc_ok` i `rebotling_skiftrapport` och `rebotling_ibc` nollställs per skift (`skiftraknare`), inte per dag. ~40 SQL-metoder i `RebotlingController.php` och `RebotlingAnalyticsController.php` använde `LAG()`-subtraktion som antog att fältet var kumulativt per dag.  
**Fix:** Ersatt med `MAX(ibc_ok) GROUP BY skiftraknare` i alla berörda metoder.

---

## BUG-002: Effektivitet dippar runt raster (FIXAD)
**Rapporterad:** 2026-05-15  
**Status:** Fixad, committad `431949ff`, deployad till dev och PLC-server 2026-05-16  
**Symptom:** `produktion_procent` per cykel i `rebotling_ibc` dippade kraftigt runt raster.  
**Rotorsak:** `Rebotling.php` (PLC-backend) räknade ut IBC/h med rasttid inkluderat i nämnaren. D4007 (`runtime_plc`) uppdateras bara när skiftet är klart och kunde inte användas för löpande beräkning.  
**Fix:** Beräknar drifttid från `rebotling_onoff`-events och subtraherar rasttid från `rebotling_runtime` innan IBC/h räknas ut.

---

## BUG-003: Effektivitetsstaplar capped vid 100% (FIXAD)
**Rapporterad:** 2026-05-16  
**Status:** Fixad, committad `6340216b`, deployad till dev  
**Symptom:** Stapeldiagrammet på statistiksidan visar 100% för alla dagar oavsett faktisk effektivitet. Exempel: 5 maj visar 8 IBC med 100% — omöjligt korrekt.  
**Rotorsak:** `rebotling-statistik.ts` rad 1181 och 909 har `Math.min(100, ...)` som hårdcappar värdet.  
**Filer:** `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts` rad 909 och 1181  
**Fix:** Tagit bort `Math.min(100, ...)` på båda raderna. Y-axeln är redan konfigurerad för >100% (`suggestedMax: 115`). Effektivitet kan nu visas korrekt över 100%.

---

## BUG-004: ProduktionsTaktController — fel ibc_ok-modell (FIXAD)
**Rapporterad:** 2026-05-16  
**Status:** Fixad, committad `9454b78b`, deployad till dev 2026-05-16  
**Symptom:** Taktwidgetens IBC-räknare (current-rate, 4h-snitt, dagens snitt, veckans snitt, 15-min alert, timvis historik) visade fel värden på dagar med fler än ett skift.  
**Rotorsak:** `countIbcBetween()` och `getHourlyHistory()` antog att `ibc_ok` var en daglig kumulativ räknare (nollställs vid midnatt) och använde per-dag-delta (MAX per dag minus MAX dag innan). Faktiskt nollställs `ibc_ok` per skift (`skiftraknare`). `GROUP BY DATE(datum)` samlade ihop alla skifts rader per dag och tog MAX — på en tvåskiftsdag gav detta bara sista skiftets värde, inte summan.  
**Filer:** `noreko-backend/classes/ProduktionsTaktController.php`  
**Fix:** `countIbcBetween()` — groupar nu per `skiftraknare`, subtraherar ibc_ok BEFORE window-start per skiftraknare. `getHourlyHistory()` — per-timme per-skiftraknare-delta med LAG()-fönsterfunktion.

---

## BUG-006: RebotlingTrendanalysController — OEE Prestanda deflateras av rast (FIXAD)
**Rapporterad:** 2026-05-16  
**Status:** Fixad, committad 2026-05-16, deployad till dev  
**Symptom:** OEE Prestanda (P) och OEE-total på trendanalysdashboarden visade systematiskt lägre värden än korrekt. Typisk deflatering ~6–10% (ett 30-min rast på 8h skift ger ~6% lägre P).  
**Rotorsak:** `hamtaDagligData()` och `veckosammanfattning()` beräknade drifttid som `strtotime($sista_cykel) - strtotime($forsta_cykel)` — elapsed wall-clock time inklusive rast och oplanerade stopp. P-formeln `($total * CYKELTID) / $drifttid` delade IBC-produktion på bruttotid istället för nettotid.  
**Filer:** `noreko-backend/classes/RebotlingTrendanalysController.php`  
**Fix:** Ersatt elapsed-time-beräkning med LEFT JOIN mot `rebotling_skiftrapport`: `MAX(drifttid) GROUP BY datum, skiftraknare` summeras per dag (samma mönster som RebotlingAnalyticsController). `drifttid` i skiftrapport är D4007 netto i minuter → multiplicerat med 60 för sekunder i OEE-beräkning. Fungerar som fallback (0) om skiftrapport saknas för dagen.

---

## BUG-007: Månadsvy-staplar visar fel effektivitet vs dagvy (FULLT FIXAD 2026-05-16)
**Rapporterad:** 2026-05-16  
**Status:** FIXAD — commit `0d6486df` + detta commit.  
**Symptom:** Månadsvyns stapeldiagram och KPI-kort visade annan effektivitet än dagvyns KPI för samma dag.  
**Rotorsak:** Tre separata beräkningsplatser använde `target/avg_cykeltid×100` (ignorerar tomgångstid).  
**Fix (commit `0d6486df`):** Stapeldiagrammet (`efficiencyArr`) — netto drifttid per period från onoff/rast/stopp-events.  
**Fix-tillägg (2026-05-16):** Tre ytterligare inkonsistenser åtgärdade:  
1. **KPI-kortet** (`properEff`): Månad/år-vy använde `target/avg_cykeltid` — nu `net_runtime_minutes` från summary.  
2. **Periodceller** (`cell.efficiency`): Kalendercellfärg för månad/år-vy — nu `IBC×target/netRuntimeByKey×100` via `computeNetRuntimeByKey()`.  
3. **Tabelldata** (`updateTable`): Effektivitet och runtime — nu netto drifttid per period via samma hjälpfunktion.  
**Ny hjälpfunktion:** `computeNetRuntimeByKey(data, keyFn)` — DRY-extrakt av netto-runtime-logiken.  
**Filer:** `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts`

---

## BUG-008: produktion_procent exploderar mot slutet av skift (FIXAD)
**Rapporterad:** 2026-05-16  
**Status:** Fixad, committad 2026-05-16, deployad till PLC-server 2026-05-16  
**Symptom:** `produktion_procent` i `rebotling_ibc` börjar rimligt (~5-10%) men växer exponentiellt under skiftets gång, exempelvis till 1830% (skift 124) eller 330% (skift 125). Värdena dippar sedan till normala (~61-86%) omedelbart efter ett maskin-stopp/start.  
**Observerade data (skift 124, 2026-05-14):** ibc_count=29→487%, ibc_count=30→634%, ibc_count=34→1830%, sedan direkt reset till 61% efter maskinomstart kl 08:49.  
**Observerade data (skift 125, 2026-05-14, EFTER BUG-002-fix):** Startar vid 3% (12:22) och växer till 330% (14:28).  
**Rotorsak (bekräftad):** PHP-servern kör UTC (`date.timezone=UTC` i php.ini), men MySQL lagrar timestamps i lokal tid (CEST = UTC+2). I `handleCycle()` skapades `$now = new DateTime()` som PHP UTC-tid. DB-timestamps i `rebotling_onoff` lagras i CEST. När koden beräknade `diffSinceLast = lastEntryTime->diff($now)` med en DB-timestamp 2 timmar framåt i förhållande till PHP:s UTC-klocka, returnerade `DateInterval::diff()` ett negativt intervall (invert=1). Koden ignorerade `invert`-flaggan och använde absolutvärdet — vilket gav ~7 min istället för ~112 min som `$totalRuntimeMinutes`. Med nämnaren ~7 min och 35 IBCer: `(35×60)/7/20×100 = 1500%+`. Maskinomstart skapade ny `rebotling_onoff`-rad som resettar beräkningsbasen → normalt värde efteråt.  
**Fix:** Ersatt `new DateTime()` med MySQL-tid (`SELECT NOW()`) så `$now` och DB-timestamps är i samma tidszon. Lagt till guard `if ($diffSinceLast->invert === 0)` mot clock-skew. Samma fix på `$now2` i rast-beräkning.  
**Filer:** `noreko-plcbackend/Rebotling.php` rad 201-253 (`handleCycle()`, produktionsprocent-beräkning)

---

## BUG-005: SkiftplaneringController::getShiftDetail — fel ibc_ok-modell (FIXAD)
**Rapporterad:** 2026-05-16  
**Status:** Fixad, committad `9454b78b`, deployad till dev 2026-05-16  
**Symptom:** Faktisk produktion på skiftdetalj-sidan visade fel värde på dagar med fler än ett skift (underskattade om skiftet satte in mitt på dagen).  
**Rotorsak:** Samma som BUG-004 — per-dag-delta (`GROUP BY DATE(datum)`) i stället för per-skift-delta (`GROUP BY skiftraknare`).  
**Filer:** `noreko-backend/classes/SkiftplaneringController.php`  
**Fix:** Ersatt `GROUP BY DATE(datum)` med `GROUP BY skiftraknare`, LEFT JOIN på skiftraknare i stället för dag.

---

## BUG-009: NewsController — IBC underskattas på flerskiftsdagar (FIXAD)
**Rapporterad:** 2026-05-16
**Status:** FIXAD — commit `6fecf74b`
**Symptom:** Nyhetsflödets "Rekordag", "Produktionsrekord" och "Senaste produktionsdagar" visade för lågt IBC-tal på flerskiftsdagar — bara sista skiftets MAX visades istället för dagssumman.
**Rotorsak:** `NewsController.php` rad 289–306, 342–348, 448–452, 481–494, 527–533 använde `MAX(ibc_ok) GROUP BY DATE(datum)` utan LAG()-delta.
**Filer:** `noreko-backend/classes/NewsController.php`
**Fix:** 5 queries omskrivna till LAG()-CTE per (datum, skiftraknare) → delta → SUM per dag. Berörda sektioner: rekordag, hog_oee, produktion, produktionsrekord, oee_milstolpe.

---

## BUG-010: Statistik Produktion-flik — 7-dagars veckotrend layout trasig, smal vänsterspalt (FIXAD)
**Rapporterad:** 2026-05-16
**Fixad:** 2026-05-16
**Status:** Åtgärdad
**Symptom:** På Statistiksidans Produktion-flik är 7-dagars veckotrendssektionen layout-trasig — vänsterkolumnen är onormalt smal.
**Rotorsak:** `StatistikVeckotrendComponent` saknade helt CSS-fil och `styleUrls`. Klasserna `.veckotrend-wrapper`, `.kpi-row`, `.kpi-card`, `.sparkline-container` m.fl. hade inga stilregler → oformatterade/sammanpressade kort.
**Fix:** Skapade `statistik-veckotrend.css` med CSS Grid (auto-fit minmax 160px) för `.kpi-row`, full-width flex `.kpi-card` med `min-width: 0`, och lade till `styleUrls` i komponent-dekoratorn.
**Filer:** `noreko-frontend/src/app/pages/rebotling/statistik/statistik-veckotrend/statistik-veckotrend.css` (ny), `statistik-veckotrend.ts` (styleUrls tillagt)

---

## BUG-011: buildShiftSummaries — ibc_count summeras som tal istf. räknas som event (FIXAD)
**Rapporterad:** 2026-05-16
**Fixad:** 2026-05-16
**Status:** Åtgärdad
**Symptom:** Skiftöversiktskortet (dagvy i rebotling-statistik) visade absurt höga IBC-tal per skift — t.ex. "4920 IBC" i stället för "123 IBC".
**Rotorsak:** `buildShiftSummaries()` i `rebotling-statistik.ts` rad 2028 summerade `c.ibc_count` (den dagliga sekvensräknaren 1, 2, 3, … N) i stället för att räkna varje rad som 1 cycle-event. Summan av 1+2+…+N = N*(N+1)/2 — ca N/2 × korrekt värde. Täckt av `|| 1` som fallback men felet gäller för alla rader med ibc_count ≥ 2.
**Rotorsak (teknisk):** `rebotling_ibc.ibc_count` är en PLC-räknare som stiger sekventiellt per dag (1, 2, 3...). Varje rad i tabellen motsvarar ett IBC-cykelevent. Rätt sätt att räkna cykler i ett skift är att räkna antal rader (+= 1), inte att summera ibc_count-värdet.
**Fix:** `s.ibcCount += (c.ibc_count || 1)` → `s.ibcCount += 1`
**Filer:** `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts` rad 2028

---

## BUG-012 (BUG-71): Månadsvy snitt-effektivitet stämmer ej med staplar (FIXAD)
**Rapporterad:** 2026-05-16
**Status:** FIXAD 2026-05-16
**Symptom:** April månadsvy visade 130% snitt-effektivitet i KPI-kortet men staplarna visade ~94% i genomsnitt. Staplarna visade IBC-antal utan prefix vilket var förvirrande (ser ut som ett effektivitetsvärde).
**Rotorsak:** KPI-kortet använde `data.summary.net_runtime_minutes` (backend) för att beräkna netto-drifttid. Backendkoden subtraherar rast+driftstopp från totalRuntime utan att kontrollera om pauserna faller inom körsegment — detta kan övertolka pauserna och ge ett för litet `netRtMin` → effektivitet > 100%. Stapeldiagrammet beräknar i stället netto-drifttid direkt från events i frontend (onoff minus rast/driftstopp korrekt klippta mot varje on-segment). Dessa gav olika resultat för månadsdata.
**Fix:** Ny hjälpmetod `computeTotalNetRuntimeMinutes()` — exakt samma algoritm som stapeldiagrammets `netRuntimeByKey`-beräkning men summerar totalt. `updateStatistics()` använder nu frontend-beräknad netto-drifttid för månad/år-vy (dagvy får behålla backend-värdet). Bar-etiketter fick prefixet "IBC:" för att göra det tydligt att det är antal IBC och inte effektivitet (UX-fix).
**Filer:** `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts`

---

## BUG-013 (BUG-72): Månadsvy Y-axel går till 900% effektivitet (FIXAD)
**Rapporterad:** 2026-05-16
**Fixad:** 2026-05-16
**Status:** FIXAD
**Symptom:** Februari 2026 månadsvy: Y-axeln sträckte sig till ~900%. En stapel visade ~900% effektivitet vilket är uppenbart fel.
**Rotorsak:** `IBC × target / netRuntime × 100` exploderar om netRuntime är extremt kort (t.ex. <5 min på en testdag).
**Fix:** Kräver minst 5 min netRuntime (`netMin >= 5`) + cap 250% (`Math.min(250, ...)`) i `prepareChartData`, `updatePeriodCellsData` och `updateTable` i `rebotling-statistik.ts`.

---

## BUG-014 (BUG-73): Negativa IBC-värden i stapeldiagram (-59 IBC 25 feb) (FIXAD)
**Rapporterad:** 2026-05-16
**Fixad:** 2026-05-16
**Status:** FIXAD
**Symptom:** 25 februari visade -59 IBC i nyhetsflödet. Negativa IBC är aldrig möjliga.
**Rotorsak:** `NewsController.php` hade 5 LAG()-delta-queries UTAN `GREATEST(0, ...)` — ger negativa delta_ibc om PLC-räknaren återställs (sk1=100, sk2=41 → delta=-59).
**Fix:** (1) `noreko-backend/classes/NewsController.php`: `GREATEST(0, ...)` lagt till i alla 5 LAG()-delta. (2) `rebotling-statistik.ts`: `Math.max(0, ...)` på cycleCountArr. (3) `rebotling-statistik.html`: guard `c.ibc_ok >= 0 ? c.ibc_ok : 0` i rådata-modal.
**Filer:** `noreko-backend/classes/NewsController.php`, `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts`, `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.html`

---

## BUG-015: Statistiksidan navigation — browser back-knapp fungerar inte (FIXAD)
**Rapporterad:** 2026-05-16
**Status:** Fixad, deployad till dev 2026-05-16
**Symptom:** Browser back-knappen fungerade inte korrekt på rebotling/statistik:
- Klick på "Föregående"/"Nästa" månad/år/dag pushade inte ny history-entry → browser back hoppade förbi navigationen
- Klick på år-breadcrumben (navigateToYear) pushade inte history → browser back från årsvy fungerade inte
- Heatmap-läget syncades inte till URL alls → browser back/reload startade i fel vy
- `applyStateFromUrl` parsade inte 'heatmap' som giltig viewMode → URL-återställning av heatmap ignorerades
**Rotorsak:** `navigatePrevious()`, `navigateNext()` och `navigateToYear()` anropade `syncStateToUrl()` (default `replaceUrl: true`) istället för `syncStateToUrl(false)`. `enterHeatmapMode()` anropade inte `syncStateToUrl()` alls.
**Filer:** `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts`
**Fix:**
- `navigateToYear()`: `syncStateToUrl()` → `syncStateToUrl(false)`
- `navigatePrevious()`: `syncStateToUrl()` → `syncStateToUrl(false)`
- `navigateNext()`: `syncStateToUrl()` → `syncStateToUrl(false)`
- `enterHeatmapMode()`: Tillagd `syncStateToUrl(false)` anrop
- `applyStateFromUrl()`: Tillagd `'heatmap'` i accept-listan för viewMode
- queryParams-subscription: Anropar nu `loadHeatmap()` om `viewMode === 'heatmap'` (istf. alltid `loadStatistics()`)

---

## BUG-015 (BUG-74): Årsvy vs månadsvy visar helt olika IBC-tal för samma period (FIXAD)
**Rapporterad:** 2026-05-16
**Status:** FIXAD 2026-05-16
**Symptom:** April 2026: årsvy visar 449 IBC men månadsvy visar 1370 IBC för exakt samma period (3× diskrepans).
**Rotorsak (verifierad):** Ingen LIMIT-klausul hittades. Backend returnerade korrekt 1370 cykler för april i år-svaret. Rotorsaken var att frontend:s `prepareChartData`, `updatePeriodCellsData` och `updateTable` alla använde `new Date(cycle.datum).getMonth()` för att grupera cykler i månadshink för årsvy. JS `new Date()` parsning av MySQL-datumsträng (`"YYYY-MM-DD HH:MM:SS"`) är implementation-beroende och kan i vissa browser-/timezone-konfigurationer kasta datum till fel månad (t.ex. sent på kvällen nära månadsslut i UTC+2). Resultatet: cykler hamnade i fel månadshink → årsvy visade fel tal (449 istf 1370 för april, eftersom maj-cykler (449) hamnade i april-hinken).
**Fix:** Tre ändringar i frontend (`rebotling-statistik.ts`): `prepareChartData`, `updatePeriodCellsData`, `updateTable` — år-vy nu extraherar månadsnummer direkt ur datum-strängen (`cycle.datum.substring(5,7)`) istf. `new Date().getMonth()`. Deterministic, timezone-oberoende. Backend kompletterad med `by_month` och `by_day` fält i `getStatistics`-svaret för framtida server-side aggregering.
**Verifiering:** `by_month` bekräftar 2026-04: 1370, 2026-05: 449 — stämmer med `SELECT COUNT(*) FROM rebotling_ibc WHERE datum BETWEEN '2026-04-01' AND '2026-04-30'` = 1370.
**Filer:** `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts`, `noreko-backend/classes/RebotlingController.php`

---

## BUG-016: Tomma staplar i år/månadsvy färgas röda (FIXAD)
**Rapporterad:** 2026-05-16
**Fixad:** 2026-05-16
**Status:** FIXAD — detta commit
**Symptom:** Månader/dagar utan produktion (0 IBC) visades som 0-höga röda staplar i bar-diagrammet. Rött signalerar "dålig prestation" — för dagar/månader utan produktion är det missvisande.
**Rotorsak:** `barColors` och `barBorderColors` i `createBarChart()` använde bara effektivitetsvärdet (eff=0 → röd) utan att kolla om perioden faktiskt hade produktion.
**Fix:** Kontrollerar `countData[i] === 0` innan effektivitetsfärg väljs — nollperioder får grå färg (`rgba(100,100,100,0.35)`).
**Filer:** `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts` (createBarChart)

---

## BUG-017: Bar chart tooltip visar ASCII istf. korrekt svenska (FIXAD)
**Rapporterad:** 2026-05-16
**Fixad:** 2026-05-16
**Status:** FIXAD — detta commit
**Symptom:** Bar chart-tooltip visade `(over mal)`, `(nara mal)`, `(under mal)` med felstavade svenska ord.
**Rotorsak:** Strängarna i `createBarChart` tooltip-callback använde ASCII utan åäö: `'over mal'`, `'nara mal'`.
**Fix:** Ersatt med korrekt svenska: `'över mål'`, `'nära mål'`, `'under mål'`.
**Filer:** `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts` (createBarChart tooltip callback)

---

## BUG-013 (partiellt fixad): Månadsvy Y-axel kan gå till 900%+ effektivitet — nu cappat 250% (BEKRÄFTAT FIXAD av ägaren 2026-05-18)
**Rapporterad:** 2026-05-16
**Status:** BEKRÄFTAT FIXAD 2026-05-18 — ägaren verifierade: Feb Y-axel 300% (var 900%), eff 53% (var 115%). Cap 250% + netRuntime-formel ger korrekta värden
**Symptom:** Február 2026 månadsvy: Y-axeln sträckte sig till ~900%. En stapel visade ~900% effektivitet.
**Rotorsak:** `IBC × target / netRuntime × 100` exploderar om netRuntime är extremt kort (t.ex. <5 min på en testdag). Inga sanity-gränser fanns.
**Fix (2026-05-16):**
1. Kräver minst 5 min `netRuntime` för att använda net-runtime-formeln — kortare perioder faller tillbaka på `target/avg_cykeltid`.
2. Hårdcapp på 250% i `prepareChartData` (efficiencyArr), `updatePeriodCellsData` (cell.efficiency) och `updateTable` (avgEff).
**Filer:** `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts`
**Kvarstående:** Backend kan fortfarande returnera data med extremt kort onoff_event-segment som leder till hög eff%. En mer robust fix kräver backend-sida minimum-drifttid-filter.

---

## BUG-016 (BUG-75): Årsvy saknar Maj-stapel (EJ FIXAD)
**Rapporterad:** 2026-05-16
**Status:** EJ åtgärdad
**Symptom:** Årsvy 2026 visar ingen stapel för maj trots att det finns produktionsdata i maj.
**Rotorsak (hypotes):** Troligen relaterat till BUG-015 (LIMIT trunkerar data) — maj-data hamnar utanför gränsen. Alternativt: frontend-gruppen för år-vy initierar 12 månader men maj-buckets fylls aldrig pga. datumformat/timezone-mismatch (månadsnamn-nyckel).
**Filer:** `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts` prepareChartData år-vy gruppering, `noreko-backend/classes/RebotlingController.php` getStatistics LIMIT
**Fix:** Del av BUG-015-fix (höj LIMIT). Verifiera även att årets alla 12 månader initieras korrekt som buckets.

---

## BUG-017 (BUG-76): URL-param month= skickas med vid view=year (FIXAD)
**Rapporterad:** 2026-05-16
**Status:** FIXAD 2026-05-16
**Symptom:** När man navigerar till årsvy innehåller URL:en `month=`-parametern trots att den inte är relevant för år-vy.
**Rotorsak:** `syncStateToUrl()` satte alltid month och dates i queryParams oavsett viewMode.
**Fix:** `syncStateToUrl()` i rebotling-statistik.ts: view=year → month=null, dates=null. view=month → dates=null. view=day → dates sätts bara om selectedPeriods finns. Angular Router tar bort null-params automatiskt.
**Filer:** `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts`

---

## BUG-018: cycle-trend IBC/h underskattas med ~40% — ibc_ok är frusen snapshot i rebotling_ibc (EJ FIXAD)
**Rapporterad:** 2026-05-16
**Status:** EJ åtgärdad — KRITISK
**Symptom:** Cykeltrend-diagrammet (`statistik-cykeltrend`, IBC/h-linje) visar ~16 IBC/h för dagar med faktisk IBC/h ≈ 27. Feluppskattning ~40%.
**Verifierat dag:** 2026-04-29 (220 cykler, 3 skift: sk116/117/118)
- Skiftrapport (auktoritativ): ibc_ok=211, drifttid=469 min → **IBC/h = 27.0**
- API `run=cycle-trend` via MAX(ibc_ok) från rebotling_ibc: ibc_ok=124, runtime=460 min → **IBC/h = 16.2**
- Avvikelse: -10.8 IBC/h, **-40% fel**
**Rotorsak:** `ibc_ok` i `rebotling_ibc` är INTE kumulativt per skift — det är ett fruset PLC-snapshot-värde som lagrades tidigt i skiftet och aldrig uppdateras. Skiftets faktiska slutvärde finns i `rebotling_skiftrapport.ibc_ok`. Konkret för 2026-04-29:
- sk116: MAX(ibc_ok) rebotling_ibc=**0**, skiftrapport=40
- sk117: MAX(ibc_ok) rebotling_ibc=**40**, skiftrapport=84
- sk118: MAX(ibc_ok) rebotling_ibc=**84**, skiftrapport=87
**Berörd endpoint:** `run=cycle-trend` — `getCycleTrend()` rad ~1599–1679 i `RebotlingController.php`
**Filer:** `noreko-backend/classes/RebotlingController.php` `getCycleTrend()`
**Fix:** Byt ut `MAX(COALESCE(ibc_ok,0))` från `rebotling_ibc` mot JOIN på `rebotling_skiftrapport` för det korrekta slutvärdet per skiftraknare: `LEFT JOIN rebotling_skiftrapport sr ON sr.skiftraknare = base.skiftraknare` + `COALESCE(sr.ibc_ok, MAX(base.ibc_ok))`.

---

## BUG-019: cycle-trend bur_ej_ok underskattas med ~67% — samma froze-snapshot-problem (EJ FIXAD)
**Rapporterad:** 2026-05-16
**Status:** EJ åtgärdad
**Symptom:** Kassationsdata (`bur_ej_ok`) i cykeltrend är systematiskt underskattad.
**Verifierat dag:** 2026-04-29
- Skiftrapport (auktoritativ): bur_ej_ok=**12** (sk116=1, sk117=3, sk118=8)
- API via MAX(bur_ej_ok) från rebotling_ibc: bur_ej_ok=**4** (sk116=0, sk117=1, sk118=3)
- Avvikelse: -8 enheter, **-67% fel**
**Rotorsak:** Samma som BUG-018 — `bur_ej_ok` i `rebotling_ibc` är ett fruset snapshot-värde.
**Filer:** `noreko-backend/classes/RebotlingController.php` `getCycleTrend()` — samma query som BUG-018
**Fix:** Ingår i BUG-018-fix — JOIN mot `rebotling_skiftrapport` för slutvärden på ibc_ok och bur_ej_ok.

---

## BUG-018 (BUG-77): Årsvy vs månadsvy effektivitet systematiskt fel (FIXAD)
**Rapporterad:** 2026-05-16
**Status:** FIXAD 2026-05-16 — Bekräftat av ägaren: April månadsvy nu 39% ≈ årsvy 38%
**Symptom:** Årsvy och månadsvy visar helt olika effektivitet för samma månader:
- April: årsvy 38% (röd) vs månadsvy 130% (grön)
- Februari: årsvy 50% vs månadsvy 115%
- Mars: årsvy 75% vs månadsvy 120%
**Rotorsak:** `getStatistics()` har en LIMIT som trunkerar cykler vid årsvy (BUG-015: april 449 vs 1370 cykler). Effektivitetsformeln `IBC × target / netRuntime × 100` ger fel svar när täljaren (IBC) är ~3× för liten men nämnaren (netRuntime från onoff_events) är korrekt. 449/1370 × 130% ≈ 43% — stämmer med rapporterade 38%.
**Filer:** `noreko-backend/classes/RebotlingController.php` getStatistics() LIMIT-klausul
**Fix:** Del av BUG-015-fix — ta bort/höj LIMIT för cycles-query i getStatistics(). Skickat till BUG-015-agenten.
**Prioritet:** KRITISK — årsvy är helt opålitlig

---

## BUG-079: Månadsvy KPI-snitt stämmer ej med staplarnas snitt (FIXAD — ingår i BUG-012-fix)
**Rapporterad:** 2026-05-16
**Status:** FIXAD — samma fix som BUG-012 (computeTotalNetRuntimeMinutes)
**Symptom (maj):** Månadsvy maj visar 115% snitt-effektivitet i KPI-kortet men staplarnas genomsnitt ≈ (58+98+105+85)/4 = 86.5%.
**Notering:** BUG-007-fix är bekräftad — Maj 5 dagvy=60% ≈ månadsvy stapel 58% (de matchar nu). Problemet är KPI-snittet, inte staplarna.
**Rotorsak:** Samma som BUG-012 — KPI-effektivitetssnittet viktar inte på samma sätt som staplarna, eller använder annan datakälla (data.summary.net_runtime_minutes vs computeNetRuntimeByKey per dag).
**Filer:** `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts` KPI-kort avgEfficiency-beräkning

---

## BUG-080: "NON UN" orange etikett i chart-hörnet — ej konfigurerad/broken (FIXAD)
**Rapporterad:** 2026-05-16
**Status:** FIXAD 2026-05-16
**Symptom:** En orange etikett med texten "NON UN" (produktnamn) visades i övre vänstra hörnet av dagvyns linjediagram på statistiksidan — utan någon separator-linje under.
**Rotorsak:** I `beforeDatasetsDraw`-pluginen ritades produktbyte-etiketter (`pb.namn`) för ALLA produkter i `produktByten`-arrayen, inkl. index 0 (det allra första produktsegmentet). Koden hade redan `if (pb.index > 0)` för att hoppa över den vertikala linjen vid index 0, men etiketten ritades fortfarande utanför detta if-block — därav den hängande oranga etiketten i chart-hörnet.
**Fix:** Hela blocket (linje + etikett) är nu inlineat i `if (pb.index > 0)` — etiketten ritas bara vid faktiska produktbyten (inte vid startpositionen).
**Filer:** `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts` rad 1722–1737 (`beforeDatasetsDraw` plugin, `produktByten.forEach`)

---

## BUG-081: State-leak — klick på månads-pill från dagvy ger månadsvy med 1 stapel (FIXAD)
**Rapporterad:** 2026-05-16
**Status:** FIXAD — commit `4217b2a6`
**Symptom:** Användare är i dagvy (t.ex. Maj 5). Klickar på "Maj"-pill/breadcrumb för att gå till månadsvy. Månadsvy visar bara 1 stapel (Maj 5) istf. alla maj-dagar.
**Rotorsak:** `applyStateFromUrl()` läste `dates=`-parametern och satte `selectedPeriods` oavsett vilket `view=` som angavs. Om routern triggade `applyStateFromUrl` under övergången (eller vid initial sidladdning med en gammal URL) filtrerades månadsdata ned till bara det angivna datumet.
**Fix (Alt C):** I `applyStateFromUrl()` — `dates=`-parametern ignoreras och `selectedPeriods` töms om `viewMode !== 'day'`. `navigateToMonth()` sätter redan `selectedPeriods = []` + `syncStateToUrl` tar bort `dates` för monthview (BUG-017), men det dubbla skyddet i `applyStateFromUrl` stänger alla race-condition-vägar.
**Filer:** `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts` — `applyStateFromUrl()` rad ~305

---

## BUG-082: Stapel-labels inkonsekvent format — 2025 visar "IBC: 696" men 2026 visar "1494"
**Rapporterad:** 2026-05-16
**Status:** STÄNGD — BUG VAR REDAN FIXAD (dubbeldokumentation)
**Symptom:** Årsvy 2025 visar "IBC: 696" som stapel-label men årsvy 2026 visar "1494" (utan "IBC: "-prefix). Inkonsekvent format beroende på år.
**Rotorsak (undersökt 2026-05-16):** Koden i `createBarChart()` rad 1955 har REDAN `IBC: ${count}` — det finns bara EN fillText-anrop för count-labels, och den har prefixet. BUG-012 fixade detta i en tidigare commit. Det finns ingen andra kodväg som skriver labels utan prefix. Buggen var troligen rapporterad baserat på cachad/gammal deploy. Koden är korrekt.
**Filer:** `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts` rad 1955
**Åtgärd:** Ingen kodändring behövdes — koden var redan korrekt. BUGS.md uppdaterad 2026-05-16.

---

## BUG-083: Dubbla snabba klick på navigations-pil hoppar 2 steg (debounce saknas)
**Rapporterad:** 2026-05-16
**Status:** FIXAD — 8b65612b
**Symptom:** Dubbla snabba klick på föregående/nästa-pil hoppar 2 steg (t.ex. 2024→2022, missar 2023). Gäller troligen dag/månad/år-navigation.
**Rotorsak:** `navigatePrevious()`/`navigateNext()` saknar debounce eller "is-navigating"-guard. Varje klick triggar ett API-anrop och state-ändring direkt.
**Filer:** `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts` `navigatePrevious()`, `navigateNext()`
**Fix tillämpat:** `if (this.loading) return;` i början av `navigatePrevious()` och `navigateNext()`. Också `[disabled]="loading"` på föregående-knappen i HTML.

---

## BUG-084: Framtida år/period tillåts utan guard — visar 0% rött istf. "ingen data"
**Rapporterad:** 2026-05-16
**Status:** FIXAD — 8b65612b
**Symptom:** Man kan navigera till år 2027 (och framtida månader/dagar) via nästa-pilen. Visas som 0% röda staplar istf. en tydlig "Framtida period — ingen data tillgänglig"-indikation.
**Rotorsak:** `navigateNext()` saknar kontroll mot aktuellt datum. Ingen guard i `applyStateFromUrl()` heller — man kan skriva in framtida datum manuellt i URL:en och få samma fel.
**Filer:** `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts` `navigateNext()`, eventuellt `applyStateFromUrl()`
**Fix tillämpat:** Ny metod `isAtCurrentPeriod()` — returnerar true om innevarande period redan är i nutid. Guard `if (this.isAtCurrentPeriod()) return;` i `navigateNext()`. Knappen disabled via `[disabled]="isAtCurrentPeriod() || loading"` i HTML. **Komplett fix (2026-05-18):** `applyStateFromUrl()` capar nu `currentYear` vid `new Date().getFullYear()` — år > innevarande år kan inte längre sättas via URL-manipulation.

---

## BUG-085: Kvalitet & OEE-flik visar "Idag" 0% trots månadsvy April vald — period-väljare synkar inte
**Rapporterad:** 2026-05-16
**Status:** FIXAD — 8b65612b
**Symptom:** På statistiksidans "Kvalitet & OEE"-flik visas "Idag" med 0% oavsett vilken period som valts (t.ex. månadsvy April). Period-väljaren i fliken håller kvar "Idag" och hämtar inte data för den valda månaden/perioden.
**Rotorsak:** `StatistikOeeGaugeComponent` hade helt intern period-state ('today'/'7d'/'30d') utan koppling till förälderns datumspann.
**Filer:** `statistik-oee-gauge.ts`, `statistik-oee-gauge.html`, `rebotling-statistik.html`, `rebotling.service.ts`, `RebotlingAnalyticsController.php`
**Fix tillämpat:** `@Input() fromDate`/`toDate` till OEE gauge — när de är satta används `period=custom` med parent's datumspann. `ngOnChanges` reagerar på period-ändringar i föräldern. Backend `getRealtimeOee()` utökat med `period=custom&from=YYYY-MM-DD&to=YYYY-MM-DD`. Period-knapparna döljs i custom-läge och ersätts med ett datum-badge. Föräldern binder `[fromDate]="getDateRange().start" [toDate]="getDateRange().end"`.

---

## BUG-086: Cykeltid per operator-chart visar bara första bokstaven (M K E R B O T) istf. fulla namn
**Rapporterad:** 2026-05-16
**Status:** FIXAD
**Symptom:** Diagrammet "Cykeltid per operator" på statistiksidan visar bara en bokstav per operatör (M, K, E, R, B, O, T) istf. fullständiga operatörnamn.
**Rotorsak:** Frontend-koden mappade `op.initialer` istf. `op.namn` för chart-labels i `statistik-cykeltid-operator.ts` rad 91. Backend returnerade redan `op.namn` korrekt.
**Fix:** Ändrade `chartData.map(op => op.initialer)` → `chartData.map(op => op.namn)` i `renderCycleByOpChart()`. Ingen label-rotation behövdes — diagrammet är horisontellt (indexAxis: 'y'). HTML-tabellen visade redan `op.namn` korrekt.
**Fil:** `noreko-frontend/src/app/pages/rebotling/statistik/statistik-cykeltid-operator/statistik-cykeltid-operator.ts`

---

## BUG-087: Analys-flik — Veckojämförelse/SPC tre problem
**Rapporterad:** 2026-05-16
**Status:** DELVIS FIXAD — Del 1 åtgärdad 2026-05-16
**Symptom (3 delbugg):**
1. **Stavfel:** "Veckojamforelse" saknar å — borde vara "Veckojämförelse" ✓ FIXAT
2. **SPC period-synk:** SPC-diagrammet visar alltid 7 dagar fast månadsvy är vald — ignorerar vald period (samma problem som BUG-085 men i Analys-fliken)
3. **Conceptual bug:** "Förra veckan"-staplar läggs på "Denna veckan"-dagar — x-axeln innehåller dagarna i nuvarande vecka men stapeldata är från förra veckan → missvisande jämförelse som antyder att det är samma dag
**Rotorsak:**
- Del 1: Hårdkodat ASCII-sträng utan åäö i komponent/template — FIXAT
- Del 2: SPC-komponenten har egen fast period (7 dagar), synkar inte med huvud-statistiksidans viewMode/currentMonth
- Del 3: Jämförelselogiken plottar förra veckans data på nuvarande veckans x-axelpositioner utan tydlig distinktion — bör antingen ha separata x-axlar eller tydlig labels "V18 (denna)" vs "V17 (förra)"
**Filer:** `noreko-frontend/src/app/pages/rebotling/statistik/statistik-veckojamforelse/statistik-veckojamforelse.html`
**Del 1 fix tillämpat:** Ändrade "Veckojamforelse -- IBC per dag" → "Veckojämförelse — IBC per dag", "forra veckan" → "förra veckan", "Forra veckan" → "Förra veckan" i statistik-veckojamforelse.html. Build + deploy 2026-05-16.
**Del 2 & 3 (öppna):** Kräver mer analys — komponentens @Input-bindningar och SPC-period-logik måste undersökas. Skippas i detta pass.

---

## BUG-088: Detaljerad Statistik — NaN min och NaN% för nästan alla 10-min-perioder (KRITISK)
**Rapporterad:** 2026-05-16
**Status:** FIXAD — commit 9a11f12b
**Symptom:** "Detaljerad Statistik"-tabellen (10-minutersperioder i dagvy) visar `NaN min` och `NaN%` för nästan alla rader. Bara första raden (07:10) visar korrekta värden.
**Rotorsak (bekräftad):** PHP PDO returnerar MySQL `ROUND(TIMESTAMPDIFF(SECOND,...)/60.0, 2)` som sträng (`"5.23"`) i `fetchAll(PDO::FETCH_ASSOC)`. I `updateTable()` saknades `parseFloat()` vid `.map(c => c.cycle_time)` och `c.target_cycle_time || 3`. Utan parseFloat gav `reduce((sum, t) => sum + t, 0)` strängkonkatenering (`"05.234.87..."`) istf. addition → division = NaN. Rad 0 visade korrekt värde om den råkade ha tomma validCycleTimes (null från LAG i första cykeln).
**Fix:** 
1. `parseFloat(c.cycle_time)` + filter `!isNaN(t) && t > 0 && t <= 30` i updateTable()
2. `parseFloat(c.target_cycle_time || 3)` i avgTarget-beräkningen  
3. Dag-vy tableNetRuntime nu beräknad via `computeNetRuntimeByKey` med 10-min-nyckel (istf. tom Map)
**Filer:** `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts` — `updateTable()`
**Prioritet:** KRITISK — regression som gör detaljerad statistik oanvändbar

---

## BUG-089: Effektivitet skiljer mellan dag- och månadsvy — månadsvy visar fel (trolig regression)
**Rapporterad:** 2026-05-16
**Status:** EJ åtgärdad — trolig regression
**Symptom:** Effektivitet i månadsvy stämmer inte med dagvyn för samma dag. Månadsvy visar fel värden.
**Rotorsak (hypotes):** BUG-085-fixet (commit 8b65612b) kan ha ändrat `getDateRange()` att returnera `{ start, end }` istf. `{ from, to }`. Alla kodvägar som använder `getDateRange().from` eller `getDateRange().to` (t.ex. `computeNetRuntimeByKey()`, effektivitetsberäkning i månadsvy) får då `undefined` → NaN → felaktig effektivitet. Sannolikt samma rotorsak som BUG-088.
**Filer:** `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts` — `getDateRange()`, `computeNetRuntimeByKey()`, `prepareChartData()`
**Fix:** Kontrollera alla anrop till `getDateRange()` i filen — om returnformatet ändrades måste alla konsumenter uppdateras konsekvent. BUG-088-agenten (a092f2a) undersöker samma regression.

---

## BUG-090: Status-tidslinje slutar vid 14:45, resten av dygnet saknas + 00:00-07:02 visas som "Stopp"
**Rapporterad:** 2026-05-16
**Status:** EJ åtgärdad
**Symptom (2 delbugg):**
1. **Trunkerad tidslinje:** Statuslinjen slutar vid ca 14:45 — resten av dygnet (14:45–24:00) saknas trots att produktionen kan fortsätta
2. **Felaktig "Stopp"-label:** Perioden 00:00–07:02 visas som "Stopp" trots att inget skift är planerat — ska vara "Ingen produktion planerad" eller dolt
**Rotorsak:**
- Del 1: Tidslinje-komponenten begränsar sig till events inom datumintervallet — om sista event är 14:45 slutar linjen där istf. att sträcka sig till dygnslutet. Alternativt begränsar ett API-anrop data till visst antal events (LIMIT).
- Del 2: 00:00–07:02 har en `driftstopp`- eller `onoff`-status=0 (stopp) i databasen — systemet tolkar detta som "maskinen är stoppad" men det är natt utan planerat skift.
**Filer:** Tidslinje-komponent i statistiksidan eller dashboard. Sök på "status", "tidslinje", "timeline", "stopp" i rebotling-komponenter.
**Fix:**
1. Sträck tidslinjen alltid till dygnslutet (eller nu + 2h)
2. Lägg till "oplanerad tid"-kategori: om stopptid är utanför skifttider (06:00–22:00 eller liknande), visa "Ingen produktion planerad" istf. "Stopp"

---

## BUG-091: Klassificeringslinje Statistik visar 0% röd och 0 kassation röd — borde visa "Ingen data"
**Rapporterad:** 2026-05-16
**Status:** EJ åtgärdad
**Symptom:** Klassificeringslinjens statistiksida visar KPI-kort med 0% (röd) och Kassation 0 (röd). Linjen är EJ i drift — inga data ska finnas — men istf. "Ingen data tillgänglig" visas felaktigt 0-värden med rödmarkering.
**Rotorsak:** Statistiksidans KPI-kort saknar guard för "ingen data". `0%` behandlas som dålig prestation och färgas röd, men korrekt beteende vid noll-data är att visa ett neutralt "–" eller "Ingen data".
**Filer:** Klassificeringslinjens statistikkomponent (sök i `src/app/pages/klassificeringslinje/` eller liknande). Troligen delad med rebotling-statistik-mönster.
**Fix:** Om `total_cycles === 0`: visa "–" istf. "0%" och använd neutral grå istf. röd. Lägg till `*ngIf="data?.total_cycles > 0; else noData"` med ett tydligt "Ingen produktionsdata tillgänglig"-meddelande.

---

## BUG-092: Tab-switching trasig i årsvy — klick på "Analys" (och andra tabbar) ger ingen effekt
**Rapporterad:** 2026-05-16
**Status:** FIXAD (komplett) — 2026-05-18
**Symptom:** I årsvy fungerar inte flikar — klick ger ingen effekt och Översikt-fliken visas kvar. Dag- och månadsvy fungerade korrekt.
**Rotorsak (del 1, commit 8e6e7152):** `queryParams`-subscriptionen jämförde `incomingMonth === this.currentMonth` för att avgöra om URL-ändringen var självgenererad. I årsvy saknas `month`-parametern → `parseInt(params['month'], 10)` = `NaN`. `NaN === integer` är alltid `false` → early-return misslyckades → `loadStatistics()` anropades dubbelt. Fix: `monthMatches = incomingView === 'year' ? true : incomingMonth === this.currentMonth`.
**Rotorsak (del 2 — kvarstående bugg trots del 1):** CSS-överlapp mellan `.stats-tab-bar` (`position: sticky; top: 0; z-index: 10`) och `.header` (`position: sticky; top: 0; z-index: 100`). När användaren scrollar ~250px (förbi stats-page-header) hamnar tab-baren bakom app-headern — klick på flik-knappar fångades upp av headern istf. tab-knapparna. Effekten noterades tydligast i årsvy eftersom användare typiskt scrollar längre ner för att se period-celler och årsdiagram.
**Fix (del 2, commit TBD):** `ngAfterViewInit()` mäter `.header`-elementets faktiska höjd och sätter CSS-variabeln `--app-header-h` på `:root`. `@HostListener('window:resize')` uppdaterar variabeln vid resize. Tab-bar CSS: `top: var(--app-header-h, 0px)` — tab-baren sticker nu rätt under headern istf. att överlappa den.
**Filer:** `rebotling-statistik.ts` (ngAfterViewInit + updateTabBarStickyOffset), `rebotling-statistik.css` (.stats-tab-bar top)

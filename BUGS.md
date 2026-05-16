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

## BUG-012 (BUG-71): Månadsvy snitt-effektivitet stämmer ej med staplar (EJ FIXAD)
**Rapporterad:** 2026-05-16
**Status:** EJ åtgärdad
**Symptom:** April månadsvy visar 130% snitt-effektivitet i KPI-kortet men staplarna visar ~94% i genomsnitt. Dessutom visar staplarna IBC-antal som stapelns tooltip-värde men höjden representerar effektivitet — förvirrande UX.
**Rotorsak (hypotes):** KPI-kortet (avgEfficiency) beräknas med en annan formel eller datakälla än stapeldiagrammets efficiencyArr. Kan också bero på att snitt-beräkningen i KPI inte viktar per drifttid.
**Filer:** `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts`
**Fix:** Synkronisera KPI-effektivitetformel med stapelformel. Överväg separata staplar för IBC-antal och effektivitet.

---

## BUG-013 (BUG-72): Månadsvy Y-axel går till 900% effektivitet (EJ FIXAD)
**Rapporterad:** 2026-05-16
**Status:** EJ åtgärdad — KRITISK
**Symptom:** Februari 2026 månadsvy: Y-axeln sträcker sig till ~900%. En stapel visar ~900% effektivitet vilket är uppenbart fel.
**Rotorsak (hypotes):** BUG-007-fixet beräknar `IBC × target / netRuntime × 100`. Om netRuntime för perioden är extremt liten (t.ex. en kort testdag i februari med få cykler men kort onoff-segment) exploderar formeln. Saknar sanity cap.
**Filer:** `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts` efficiencyArr-beräkning
**Fix:** Lägg till rimlighetsgräns, t.ex. `Math.min(250, ...)`, och/eller kräv minst 30 min netRuntime för att använda formeln.

---

## BUG-014 (BUG-73): Negativa IBC-värden i stapeldiagram (-59 IBC 25 feb) (EJ FIXAD)
**Rapporterad:** 2026-05-16
**Status:** EJ åtgärdad — KRITISK
**Symptom:** 25 februari visar -59 IBC i stapeldiagrammet. Negativa IBC är aldrig möjliga.
**Rotorsak (hypotes):** LAG()-delta på rebotling_skiftrapport.ibc_ok ger negativa värden när skift 2 har lägre ibc_ok än skift 1 (t.ex. sk1=100, sk2=41 → delta=41-100=-59). ibc_ok återställs per skiftraknare (inte kumulativt), så LAG()-subtraktion är fel — varje skifts ibc_ok är redan dess nettovärde.
**Filer:** Troligen backend-endpoint för årsvy/månadsvy aggregering, alternativt frontend buildTableData
**Fix:** Byt ut LAG()-delta på rebotling_skiftrapport.ibc_ok mot direkt MAX(ibc_ok) GROUP BY skiftraknare — samma fix som BUG-001. Lägg till `Math.max(0, delta)` som fallback.

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

## BUG-015 (BUG-74): Årsvy vs månadsvy visar helt olika IBC-tal för samma period (EJ FIXAD)
**Rapporterad:** 2026-05-16
**Status:** EJ åtgärdad — KRITISK
**Symptom:** April 2026: årsvy visar 449 IBC men månadsvy visar 1370 IBC för exakt samma period (3× diskrepans).
**Rotorsak (hypotes):** Backend `getStatistics()` har troligen en LIMIT-klausul som trunkerar data vid årsvy — månadsvy hämtar bara april och träffar inte gränsen. Alternativt: årsvy returnerar aggregerad data (MAX per skiftraknare) medan månadsvy returnerar råcykler — formlerna ger olika tal.
**Filer:** `noreko-backend/classes/RebotlingController.php` getStatistics(), `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts`
**Fix:** Kontrollera SQL-query för getStatistics — ta bort/höj LIMIT för år-perioder. Alternativt hämta aggregerad månadsdata från backend (SUM per dag) istf. råcykler.
**Prioritet:** KRITISK — gör årsvy opålitlig

---

## BUG-016 (BUG-75): Årsvy saknar Maj-stapel (EJ FIXAD)
**Rapporterad:** 2026-05-16
**Status:** EJ åtgärdad
**Symptom:** Årsvy 2026 visar ingen stapel för maj trots att det finns produktionsdata i maj.
**Rotorsak (hypotes):** Troligen relaterat till BUG-015 (LIMIT trunkerar data) — maj-data hamnar utanför gränsen. Alternativt: frontend-gruppen för år-vy initierar 12 månader men maj-buckets fylls aldrig pga. datumformat/timezone-mismatch (månadsnamn-nyckel).
**Filer:** `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts` prepareChartData år-vy gruppering, `noreko-backend/classes/RebotlingController.php` getStatistics LIMIT
**Fix:** Del av BUG-015-fix (höj LIMIT). Verifiera även att årets alla 12 månader initieras korrekt som buckets.

---

## BUG-017 (BUG-76): URL-param month= skickas med vid view=year (EJ FIXAD)
**Rapporterad:** 2026-05-16
**Status:** EJ åtgärdad
**Symptom:** När man navigerar till årsvy innehåller URL:en `month=`-parametern trots att den inte är relevant för år-vy.
**Rotorsak:** Navigation till år-vy rensas inte `month`-parametern från URL:en, troligen i `navigateToYear()` eller `goBack()`-logiken.
**Filer:** `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts` navigeringsmetoder
**Fix:** Vid navigation till view=year: ta bort month-parametern från URL. Vid navigation till view=month: ta bort dates-parametern.

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

## BUG-018 (BUG-77): Årsvy vs månadsvy effektivitet systematiskt fel (EJ FIXAD)
**Rapporterad:** 2026-05-16
**Status:** EJ åtgärdad — GIGANTISK. Sannolikt samma rotorsak som BUG-015.
**Symptom:** Årsvy och månadsvy visar helt olika effektivitet för samma månader:
- April: årsvy 38% (röd) vs månadsvy 130% (grön)
- Februari: årsvy 50% vs månadsvy 115%
- Mars: årsvy 75% vs månadsvy 120%
**Rotorsak:** `getStatistics()` har en LIMIT som trunkerar cykler vid årsvy (BUG-015: april 449 vs 1370 cykler). Effektivitetsformeln `IBC × target / netRuntime × 100` ger fel svar när täljaren (IBC) är ~3× för liten men nämnaren (netRuntime från onoff_events) är korrekt. 449/1370 × 130% ≈ 43% — stämmer med rapporterade 38%.
**Filer:** `noreko-backend/classes/RebotlingController.php` getStatistics() LIMIT-klausul
**Fix:** Del av BUG-015-fix — ta bort/höj LIMIT för cycles-query i getStatistics(). Skickat till BUG-015-agenten.
**Prioritet:** KRITISK — årsvy är helt opålitlig

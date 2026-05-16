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

## BUG-007: Månadsvy-staplar visar fel effektivitet vs dagvy (EJ FIXAD)
**Rapporterad:** 2026-05-16  
**Status:** EJ åtgärdad  
**Symptom:** Månadsvyns stapeldiagram visar annan effektivitet än dagvyns KPI för samma dag. Exempel: 5 maj dagvy visar 60%, månadsvy visar ~100%.  
**Rotorsak:** Två olika formler används:  
- **Dagvy:** `total_IBC × target_cykeltid / net_runtime_minutes × 100` — tar hänsyn till all drifttid inkl. tomgång mellan cykler  
- **Månadsvy (stapel):** `target_cykeltid / avg_cykeltid × 100` — mäter bara hur snabbt cyklerna kördes, ignorerar tomgång/väntetid  
Med få IBCs på en dag (t.ex. 8 st) kan cyklerna råka ha körtid ≈ target → stapeln visar 100%, trots låg faktisk produktivitet.  
**Filer:** `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts` rad 1170–1188 (månad/år-stapelberäkning)  
**Fix (ej implementerad):** Beräkna netto drifttid per dag från `data.onoff_events`, `data.rast_events`, `data.driftstopp_events` — använd sedan `IBC × target / net_runtime × 100` per period, samma formel som dagvy.

---

## BUG-008: produktion_procent exploderar mot slutet av skift (EJ FIXAD)
**Rapporterad:** 2026-05-16  
**Status:** EJ åtgärdad — BUG-002-fixet löste inte detta  
**Symptom:** `produktion_procent` i `rebotling_ibc` börjar rimligt (~5-10%) men växer exponentiellt under skiftets gång, exempelvis till 1830% (skift 124) eller 330% (skift 125). Värdena dippar sedan till normala (~61-86%) omedelbart efter ett maskin-stopp/start.  
**Observerade data (skift 124, 2026-05-14):** ibc_count=29→487%, ibc_count=30→634%, ibc_count=34→1830%, sedan direkt reset till 61% efter maskinomstart kl 08:49.  
**Observerade data (skift 125, 2026-05-14, EFTER BUG-002-fix i koden):** Startar vid 3% (12:22) och växer till 330% (14:28).  
**Rotorsak (hypotes):** Felet uppstår i `handleCycle()` → runtime-beräkning via `rebotling_onoff`. När maskinen kör kontinuerligt (bara 1 `running=1`-rad) beräknas: `lastEntryTime = lastRunningStart` → diff=0, sedan `diffSinceLast = lastEntryTime → $now`. Matematiskt borde detta ge korrekt runtime. MEN: `$now` i PHP sätts till serverns klocka vid körning, vilket kan ge avvikelser vid exekveringsfördröjningar. Det som FAKTISKT driver uppgången är dock fortfarande oklar — beräknad runtime ~112 min vid peak (08:47) borde ge ~121%, inte 1830%. Hypotes: `hourlyTarget` beräknas med ett extremt lågt värde, ELLER ibcCount är felaktigt (t.ex. räknar rader från alla skifts). Kräver ytterligare utredning med PHP-loggning.  
**Filer:** `noreko-plcbackend/Rebotling.php` rad 155-271 (`handleCycle()`, produktionsprocent-beräkning)  
**Prioritet:** HÖG — värden är oanvändbara för live-visning och historisk analys  
**Föreslagen fix:** Logga `$totalRuntimeMinutes`, `$ibcCount`, `$hourlyTarget` och `$produktion_procent` till error_log vid varje cykel. Identifiera exakt vilket värde som avviker och varför.

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

## BUG-010: Statistik Produktion-flik — 7-dagars veckotrend layout trasig, smal vänsterspalt (EJ FIXAD)
**Rapporterad:** 2026-05-16
**Status:** EJ åtgärdad
**Symptom:** På Statistiksidans Produktion-flik är 7-dagars veckotrendssektionen layout-trasig — vänsterkolumnen är onormalt smal.
**Filer:** `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.html` och/eller `rebotling-statistik.css`
**Fix:** Granska CSS/HTML för veckotrend-sektionen, trolig col/flex-width-bugg.

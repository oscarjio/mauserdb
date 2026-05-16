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
**Status:** Fixad, committad `431949ff`, deployad till dev  
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
**Status:** Fixad i denna session  
**Symptom:** Taktwidgetens IBC-räknare (current-rate, 4h-snitt, dagens snitt, veckans snitt, 15-min alert, timvis historik) visade fel värden på dagar med fler än ett skift.  
**Rotorsak:** `countIbcBetween()` och `getHourlyHistory()` antog att `ibc_ok` var en daglig kumulativ räknare (nollställs vid midnatt) och använde per-dag-delta (MAX per dag minus MAX dag innan). Faktiskt nollställs `ibc_ok` per skift (`skiftraknare`). `GROUP BY DATE(datum)` samlade ihop alla skifts rader per dag och tog MAX — på en tvåskiftsdag gav detta bara sista skiftets värde, inte summan.  
**Filer:** `noreko-backend/classes/ProduktionsTaktController.php`  
**Fix:** `countIbcBetween()` — groupar nu per `skiftraknare`, subtraherar ibc_ok BEFORE window-start per skiftraknare. `getHourlyHistory()` — per-timme per-skiftraknare-delta med LAG()-fönsterfunktion.

---

## BUG-005: SkiftplaneringController::getShiftDetail — fel ibc_ok-modell (FIXAD)
**Rapporterad:** 2026-05-16  
**Status:** Fixad i denna session  
**Symptom:** Faktisk produktion på skiftdetalj-sidan visade fel värde på dagar med fler än ett skift (underskattade om skiftet satte in mitt på dagen).  
**Rotorsak:** Samma som BUG-004 — per-dag-delta (`GROUP BY DATE(datum)`) i stället för per-skift-delta (`GROUP BY skiftraknare`).  
**Filer:** `noreko-backend/classes/SkiftplaneringController.php`  
**Fix:** Ersatt `GROUP BY DATE(datum)` med `GROUP BY skiftraknare`, LEFT JOIN på skiftraknare i stället för dag.

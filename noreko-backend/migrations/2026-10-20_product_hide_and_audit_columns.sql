-- Oscar 2026-10-20 — adminsidan: dölj i stället för radera, Senast ändrad/Ändrad av.
--
-- LÄGGER BARA TILL kolumner. Ingen ändring av befintlig data, ingen DROP, ingen
-- omindexering. Koden (TvattlinjeProductController.php, RebotlingProductController.php)
-- är redan skriven för att TÅLA att dessa kolumner saknas — innan denna migration körs
-- beter sig produktlistan precis som idag (alla produkter aktiva, dölj/visa ger ett
-- tydligt 409-fel, "Senast ändrad"/"Ändrad av" visas som "–").
--
-- KÖR INTE DENNA FIL — Oscar godkänner och kör den själv. Kompatibel med koden som
-- redan kör i produktion (prod känner inte till kolumnerna och påverkas inte).
--
-- dold          = 0/1, döljer produkten ur val för nya skift (ersätter hård DELETE).
-- andrad_tid    = tidpunkt för senaste ändring (skapande räknas som en ändring).
-- andrad_av     = inloggad användare som gjorde senaste ändringen.

ALTER TABLE tvattlinje_products
  ADD COLUMN dold TINYINT(1) NOT NULL DEFAULT 0 AFTER cycle_time_minutes,
  ADD COLUMN andrad_tid DATETIME NULL AFTER dold,
  ADD COLUMN andrad_av VARCHAR(100) NULL AFTER andrad_tid;

ALTER TABLE rebotling_products
  ADD COLUMN dold TINYINT(1) NOT NULL DEFAULT 0 AFTER cycle_time_minutes,
  ADD COLUMN andrad_tid DATETIME NULL AFTER dold,
  ADD COLUMN andrad_av VARCHAR(100) NULL AFTER andrad_tid;

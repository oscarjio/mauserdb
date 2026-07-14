-- =====================================================================
-- iter30 bug E — service_intervals: linje-kolumn
-- =====================================================================
-- Syfte: koppla varje serviceintervall (maskin) till rätt produktionslinje
-- så att IBC-räkningen sedan senaste service hämtas från rätt _ibc-tabell
-- (rebotling_ibc / tvattlinje_ibc). Tidigare hårdkodades rebotling_ibc,
-- vilket räknade tvättlinjemaskiners serviceintervall mot rebotlings produktion.
--
-- KÖR ENDAST MOT DEV-DB. Prod-DB och prod-kod är ORÖRDA — ägaren deployar
-- prod separat och manuellt.
--
-- Bakåtkompatibelt: DEFAULT 'rebotling' bevarar nuvarande beteende för
-- befintliga rader. Koden (MaintenanceController) upptäcker om kolumnen
-- saknas via SHOW COLUMNS och faller då tillbaka på 'rebotling' — deploy-ordning
-- mellan kod och denna migration spelar därför ingen roll.
-- =====================================================================

ALTER TABLE service_intervals
    ADD COLUMN linje VARCHAR(32) NOT NULL DEFAULT 'rebotling' AFTER maskin_namn;

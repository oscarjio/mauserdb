-- 2026-06-03: Lägg till saknade PLC-kolumner i tvattlinje-tabeller
-- PLC-backendfilen (TvattLinje.php) försöker skriva fält som saknas i prod.
-- Kör detta skript EN gång på produktionsdatabasen.

-- tvattlinje_ibc: produktions- och operatörskolumner från PLC D4000-D4009
ALTER TABLE tvattlinje_ibc
  ADD COLUMN IF NOT EXISTS skiftraknare  INT            DEFAULT 1       AFTER ibc_count,
  ADD COLUMN IF NOT EXISTS op1           INT            DEFAULT NULL    AFTER skiftraknare,
  ADD COLUMN IF NOT EXISTS op2           INT            DEFAULT NULL    AFTER op1,
  ADD COLUMN IF NOT EXISTS op3           INT            DEFAULT NULL    AFTER op2,
  ADD COLUMN IF NOT EXISTS produkt       INT            DEFAULT NULL    AFTER op3,
  ADD COLUMN IF NOT EXISTS ibc_ok        INT            DEFAULT NULL    AFTER produkt,
  ADD COLUMN IF NOT EXISTS ibc_ej_ok     INT            DEFAULT NULL    AFTER ibc_ok,
  ADD COLUMN IF NOT EXISTS omtvaatt      INT            DEFAULT NULL    AFTER ibc_ej_ok,
  ADD COLUMN IF NOT EXISTS runtime_plc   INT            DEFAULT NULL    AFTER omtvaatt,
  ADD COLUMN IF NOT EXISTS rasttime      INT            DEFAULT NULL    AFTER runtime_plc,
  ADD COLUMN IF NOT EXISTS lopnummer     INT            DEFAULT NULL    AFTER rasttime,
  ADD COLUMN IF NOT EXISTS effektivitet  DECIMAL(5,2)  DEFAULT NULL    AFTER lopnummer;

-- tvattlinje_onoff: skiftraknare används av PLC för att hämta aktuellt skiftnummer
ALTER TABLE tvattlinje_onoff
  ADD COLUMN IF NOT EXISTS skiftraknare INT DEFAULT NULL;

-- tvattlinje_skiftrapport: PLC skriver skiftsammanfattning med dessa fält
ALTER TABLE tvattlinje_skiftrapport
  ADD COLUMN IF NOT EXISTS omtvaatt      INT  DEFAULT 0    AFTER antal_ej_ok,
  ADD COLUMN IF NOT EXISTS op1           INT  DEFAULT NULL AFTER omtvaatt,
  ADD COLUMN IF NOT EXISTS op2           INT  DEFAULT NULL AFTER op1,
  ADD COLUMN IF NOT EXISTS op3           INT  DEFAULT NULL AFTER op2,
  ADD COLUMN IF NOT EXISTS product_id    INT  DEFAULT NULL AFTER op3,
  ADD COLUMN IF NOT EXISTS drifttid      INT  DEFAULT 0    AFTER product_id,
  ADD COLUMN IF NOT EXISTS rasttime      INT  DEFAULT 0    AFTER drifttid,
  ADD COLUMN IF NOT EXISTS driftstopptime INT DEFAULT 0    AFTER rasttime,
  ADD COLUMN IF NOT EXISTS lopnummer     INT  DEFAULT NULL AFTER driftstopptime,
  ADD COLUMN IF NOT EXISTS skiftraknare  INT  DEFAULT 1    AFTER lopnummer;

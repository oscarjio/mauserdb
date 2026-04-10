-- Tvättlinje: Lägg till PLC-fält i tvattlinje_ibc och tvattlinje_skiftrapport
-- Kördes: 2026-04-10

-- Lägger till PLC-specifika kolumner i tvattlinje_ibc
ALTER TABLE tvattlinje_ibc
  ADD COLUMN IF NOT EXISTS op1 INT DEFAULT NULL COMMENT 'D4000 - Op1 Påsatt (PLC operator_id)',
  ADD COLUMN IF NOT EXISTS op2 INT DEFAULT NULL COMMENT 'D4001 - Op2 Spolplatform (PLC operator_id)',
  ADD COLUMN IF NOT EXISTS op3 INT DEFAULT NULL COMMENT 'D4002 - Op3 Kontrollstation (PLC operator_id)',
  ADD COLUMN IF NOT EXISTS produkt INT DEFAULT NULL COMMENT 'D4003 - Produkt-ID',
  ADD COLUMN IF NOT EXISTS ibc_ok INT DEFAULT NULL COMMENT 'D4004 - Antal IBC OK (kumulativt i skift)',
  ADD COLUMN IF NOT EXISTS ibc_ej_ok INT DEFAULT NULL COMMENT 'D4005 - Antal IBC Ej OK (kumulativt i skift)',
  ADD COLUMN IF NOT EXISTS omtvaatt INT DEFAULT NULL COMMENT 'D4006 - Antal Omtvätt (kumulativt i skift)',
  ADD COLUMN IF NOT EXISTS runtime_plc INT DEFAULT NULL COMMENT 'D4007 - Körtid PLC exkl rast (minuter)',
  ADD COLUMN IF NOT EXISTS rasttime INT DEFAULT NULL COMMENT 'D4008 - Rasttid PLC (minuter)',
  ADD COLUMN IF NOT EXISTS lopnummer INT DEFAULT NULL COMMENT 'D4009 - Löpnummer (max i skift)',
  ADD COLUMN IF NOT EXISTS skiftraknare INT DEFAULT NULL COMMENT 'Skiftindex (ökande counter)',
  ADD COLUMN IF NOT EXISTS effektivitet DECIMAL(5,2) DEFAULT NULL COMMENT 'Effektivitet % (beräknad)';

-- Index för prestanda
ALTER TABLE tvattlinje_ibc
  ADD INDEX IF NOT EXISTS idx_tvattlinje_ibc_datum (datum),
  ADD INDEX IF NOT EXISTS idx_tvattlinje_ibc_skift (skiftraknare);

-- Lägger till PLC-specifika kolumner i tvattlinje_skiftrapport
ALTER TABLE tvattlinje_skiftrapport
  ADD COLUMN IF NOT EXISTS op1 INT DEFAULT NULL COMMENT 'D4000 - Op1 Påsatt (PLC operator_id)',
  ADD COLUMN IF NOT EXISTS op2 INT DEFAULT NULL COMMENT 'D4001 - Op2 Spolplatform (PLC operator_id)',
  ADD COLUMN IF NOT EXISTS op3 INT DEFAULT NULL COMMENT 'D4002 - Op3 Kontrollstation (PLC operator_id)',
  ADD COLUMN IF NOT EXISTS omtvaatt INT NOT NULL DEFAULT 0 COMMENT 'D4006 - Omtvätt-antal',
  ADD COLUMN IF NOT EXISTS drifttid INT NOT NULL DEFAULT 0 COMMENT 'D4007 - Körtid PLC exkl rast (minuter)',
  ADD COLUMN IF NOT EXISTS rasttime INT DEFAULT NULL COMMENT 'D4008 - Rasttid PLC (minuter)',
  ADD COLUMN IF NOT EXISTS driftstopptime INT DEFAULT NULL COMMENT 'Driftstopptid (minuter)',
  ADD COLUMN IF NOT EXISTS lopnummer INT DEFAULT NULL COMMENT 'D4009 - Löpnummer',
  ADD COLUMN IF NOT EXISTS skiftraknare INT DEFAULT NULL COMMENT 'Skiftindex',
  ADD COLUMN IF NOT EXISTS product_id INT DEFAULT NULL COMMENT 'Produkt-ID';

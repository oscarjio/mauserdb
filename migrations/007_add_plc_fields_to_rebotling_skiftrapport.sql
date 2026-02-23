-- Migration 007: Lägg till PLC-fält i rebotling_skiftrapport
-- op1/op2/op3 = PLC-operatörs-ID (D4000-D4002), drifttid = runtime_plc (D4007),
-- rasttime (D4008), lopnummer = högsta löpnummer i skiftet (D4009)

ALTER TABLE rebotling_skiftrapport
  ADD COLUMN IF NOT EXISTS `op1`       INT DEFAULT NULL COMMENT 'D4000 - Operatör Tvättplats (PLC operator_id)',
  ADD COLUMN IF NOT EXISTS `op2`       INT DEFAULT NULL COMMENT 'D4001 - Operatör Kontrollstation (PLC operator_id)',
  ADD COLUMN IF NOT EXISTS `op3`       INT DEFAULT NULL COMMENT 'D4002 - Operatör Truckförare (PLC operator_id)',
  ADD COLUMN IF NOT EXISTS `drifttid`  INT DEFAULT NULL COMMENT 'D4007 - Runtime PLC (minuter exkl rast)',
  ADD COLUMN IF NOT EXISTS `rasttime`  INT DEFAULT NULL COMMENT 'D4008 - Rasttid PLC (minuter)',
  ADD COLUMN IF NOT EXISTS `lopnummer` INT DEFAULT NULL COMMENT 'D4009 - Högsta löpnummer i skiftet';

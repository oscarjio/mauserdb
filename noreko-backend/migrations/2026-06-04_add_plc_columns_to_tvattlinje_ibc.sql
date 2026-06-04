-- Migration: Add PLC columns to tvattlinje_ibc
-- Date: 2026-06-04
-- Description: Adds operator, quality, runtime and shift tracking columns
--              to tvattlinje_ibc to match the rebotling_ibc schema.
--              All columns are NULL-able so existing rows are unaffected.

ALTER TABLE tvattlinje_ibc
  ADD COLUMN op1          INT NULL AFTER ibc_count,
  ADD COLUMN op2          INT NULL AFTER op1,
  ADD COLUMN op3          INT NULL AFTER op2,
  ADD COLUMN ibc_ok       INT NULL AFTER op3,
  ADD COLUMN ibc_ej_ok    INT NULL AFTER ibc_ok,
  ADD COLUMN runtime_plc  INT NULL AFTER ibc_ej_ok,
  ADD COLUMN rasttime     INT NULL AFTER runtime_plc,
  ADD COLUMN skiftraknare INT NULL AFTER rasttime,
  ADD COLUMN lopnummer    INT NULL AFTER skiftraknare;

-- Bugg: tvattlinje_skiftrapport id=11 hade plc_start=2026-06-11 07:08 (dag-off-by-one
-- vid rapportskapande). Första faktiska PLC-cykel är 2026-06-12 07:07.
-- Rot-orsak: plc_start-fönstret saknade AND DATE(i.datum)=base.datum → cykler från
-- föregående dag inkluderades. Kodfixen i LineSkiftrapportController.php rad ~200.
UPDATE tvattlinje_skiftrapport
SET plc_start = '2026-06-12 07:08:16'
WHERE id = 11
  AND DATE(plc_start) = '2026-06-11';

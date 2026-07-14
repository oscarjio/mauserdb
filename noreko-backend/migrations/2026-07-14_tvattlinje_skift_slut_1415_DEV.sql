-- ITER17 K (ENDAST DEV): sätt skift_slut = '14:15' (06:00→14:15 = 495 min planerad skifttid)
-- så plannedShiftMinutesWeekday() returnerar 495 i stället för null (>10h-cap på 22:00-värdet).
-- Systemet fungerar korrekt även utan detta (fallback 495/480), så detta är kosmetiskt.
-- Kör ENDAST på DEV-databasen — INTE prod (Oscar bestämmer prod-skiftschema separat).
UPDATE tvattlinje_settings SET value = '14:15' WHERE setting = 'skift_slut';

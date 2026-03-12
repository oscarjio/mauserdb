-- Kassationsorsak-statistik: Utvidgar befintliga tabeller med skift-typ
-- Befintliga tabeller: kassationsorsak_typer, kassationsregistrering
-- Skapad: 2026-03-12

-- Lägg till skift_typ kolumn i kassationsregistrering om den saknas
ALTER TABLE kassationsregistrering
  ADD COLUMN IF NOT EXISTS skift_typ ENUM('dag','kväll','natt') DEFAULT NULL AFTER skiftraknare;

-- Uppdatera befintliga rader: härleda skift_typ från skifträknare (1=dag, 2=kväll, 3=natt)
UPDATE kassationsregistrering
SET skift_typ = CASE
    WHEN skiftraknare = 1 THEN 'dag'
    WHEN skiftraknare = 2 THEN 'kväll'
    WHEN skiftraknare = 3 THEN 'natt'
    ELSE NULL
END
WHERE skift_typ IS NULL AND skiftraknare IS NOT NULL;

-- Lägg till index för snabbare statistikfrågor
ALTER TABLE kassationsregistrering
  ADD INDEX IF NOT EXISTS idx_orsak_datum (orsak_id, datum),
  ADD INDEX IF NOT EXISTS idx_registrerad_av (registrerad_av),
  ADD INDEX IF NOT EXISTS idx_skift_typ (skift_typ);

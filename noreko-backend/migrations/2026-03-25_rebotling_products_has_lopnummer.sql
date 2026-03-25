-- Lägg till has_lopnummer flagga på rebotling_products
-- Används för att avaktivera löpnummer på produkter som inte har det
ALTER TABLE rebotling_products ADD COLUMN IF NOT EXISTS has_lopnummer TINYINT(1) NOT NULL DEFAULT 1;

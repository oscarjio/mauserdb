-- Lägg till notification_emails-kolumn i rebotling_settings
-- Används för att skicka e-post vid brådskande skiftnoteringar
ALTER TABLE rebotling_settings
    ADD COLUMN IF NOT EXISTS notification_emails TEXT NULL DEFAULT NULL;

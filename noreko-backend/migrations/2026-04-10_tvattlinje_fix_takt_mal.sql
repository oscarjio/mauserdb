-- Fix takt_mal for tvattlinje: default was incorrectly set to 15 (minutes per IBC).
-- Actual avg cycle time is ~3 min, so target should be 3.
UPDATE tvattlinje_settings SET value = '3' WHERE setting = 'takt_mal';

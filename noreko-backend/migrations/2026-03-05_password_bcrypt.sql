-- Migration: Lösenordshashing SHA1(MD5) -> bcrypt
-- Datum: 2026-03-05
--
-- Bcrypt-hashar är 60 tecken långa (vs SHA1:s 40 tecken).
-- Kolumnen utökas till VARCHAR(255) för att stödja bcrypt och framtida algoritmer.
--
-- Befintliga lösenord migreras transparent vid inloggning:
--   1. Login testar först password_verify() (bcrypt)
--   2. Om det misslyckas testas sha1(md5()) (legacy)
--   3. Om legacy-check lyckas uppdateras hashen till bcrypt automatiskt
--
-- Ingen data förändras — alla befintliga SHA1-hashar fungerar tills
-- användaren loggar in, då de uppgraderas automatiskt.

ALTER TABLE `users` MODIFY COLUMN `password` VARCHAR(255) NOT NULL;

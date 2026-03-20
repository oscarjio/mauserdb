-- Migration: Lagg till index pa username i login_attempts for per-konto lockout
-- Datum: 2026-03-20
-- Session: #209 Worker A

-- Index for att effektivt kunna rakna misslyckade forsok per anvandarnamn
-- (skyddar mot distribuerade brute force-attacker fran flera IP-adresser)
ALTER TABLE `login_attempts`
    ADD INDEX `idx_username_created` (`username`, `created_at`);

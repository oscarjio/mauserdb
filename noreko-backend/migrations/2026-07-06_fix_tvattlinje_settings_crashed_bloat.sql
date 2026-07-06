-- =============================================================================
-- 2026-07-06 — Fixa kraschad + uppsvälld tvattlinje_settings
-- -----------------------------------------------------------------------------
-- PROBLEM: tvattlinje_settings hade 9 579 855 rader (max id ~9,58M) och var
-- "marked as crashed" (ERROR 1194). Orsak: legacy-tabellen saknade UNIK nyckel
-- på `setting`, så ensureSettingsTable()::`INSERT IGNORE` la till 4 rader per
-- HTTP-request i månader (~2,4M requests). run=settings full-scannade 9,5M rader
-- (WHERE setting IS NOT NULL ORDER BY id DESC) → ~17s → timeout/500.
--
-- FIX: bygg en ren InnoDB-tabell med UNIK nyckel på `setting`, seeda de riktiga
-- inställningsvärdena, och swap:a in atomiskt med RENAME. Den unika nyckeln gör
-- framtida INSERT IGNORE idempotent (ingen ny ackumulering). Den kraschade
-- gamla tabellen sparas som _broken_backup för granskning (kan droppas sen).
--
-- Kör i ett svep. RENAME TABLE är atomiskt.
-- =============================================================================

DROP TABLE IF EXISTS tvattlinje_settings_clean;

CREATE TABLE tvattlinje_settings_clean (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    antal_per_dag INT NOT NULL DEFAULT 150,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    timtakt       INT NOT NULL DEFAULT 20,
    skiftlangd    DECIMAL(4,1) NOT NULL DEFAULT 8.0,
    setting       VARCHAR(100) NULL,
    value         VARCHAR(255) NULL,
    UNIQUE KEY uq_setting (setting)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Legacy-konfigrad (setting=NULL) — bevarar antal_per_dag/timtakt/skiftlangd
INSERT INTO tvattlinje_settings_clean (antal_per_dag, timtakt, skiftlangd)
VALUES (150, 20, 8.0);

-- Key-value-inställningar (senaste kända värden)
INSERT INTO tvattlinje_settings_clean (setting, value) VALUES
    ('dagmal',      '140'),
    ('takt_mal',    '3'),
    ('skift_start', '06:00'),
    ('skift_slut',  '22:00');

-- Atomisk swap
RENAME TABLE tvattlinje_settings      TO tvattlinje_settings_broken_backup,
             tvattlinje_settings_clean TO tvattlinje_settings;

-- Städa (valfritt, kör manuellt när verifierat):
-- DROP TABLE tvattlinje_settings_broken_backup;

-- Operatörsbonus — konfiguration + utbetalningslogg
-- 2026-03-12

CREATE TABLE IF NOT EXISTS `bonus_konfiguration` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `faktor`       ENUM('ibc_per_timme', 'kvalitet', 'narvaro', 'team_bonus') NOT NULL,
    `vikt`         DECIMAL(5,2) NOT NULL COMMENT 'Procentandel av total bonus (summa=100)',
    `mal_varde`    DECIMAL(10,2) NOT NULL COMMENT 'Malvarde for 100% bonus',
    `max_bonus_kr` DECIMAL(10,2) NOT NULL COMMENT 'Max bonus i kronor for denna faktor',
    `beskrivning`  TEXT DEFAULT NULL,
    `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by`   INT DEFAULT NULL,
    UNIQUE KEY `uq_faktor` (`faktor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed-data
INSERT INTO `bonus_konfiguration` (`faktor`, `vikt`, `mal_varde`, `max_bonus_kr`, `beskrivning`) VALUES
    ('ibc_per_timme', 40.00, 12.00, 500.00, 'IBC per timme — genomsnittlig produktionstakt'),
    ('kvalitet',      30.00, 98.00, 400.00, 'Kvalitet — andel godkanda IBC i procent'),
    ('narvaro',       20.00, 100.00, 200.00, 'Narvaro — narvaro i procent'),
    ('team_bonus',    10.00, 95.00, 100.00, 'Team-bonus — andel uppnadda linjemal i procent')
ON DUPLICATE KEY UPDATE
    vikt         = VALUES(vikt),
    mal_varde    = VALUES(mal_varde),
    max_bonus_kr = VALUES(max_bonus_kr),
    beskrivning  = VALUES(beskrivning);

CREATE TABLE IF NOT EXISTS `bonus_utbetalning` (
    `id`                  INT AUTO_INCREMENT PRIMARY KEY,
    `operator_id`         INT NOT NULL,
    `operator_namn`       VARCHAR(100) NOT NULL,
    `period_start`        DATE NOT NULL,
    `period_slut`         DATE NOT NULL,
    `ibc_per_timme_snitt` DECIMAL(10,2) DEFAULT 0,
    `kvalitet_procent`    DECIMAL(5,2) DEFAULT 0,
    `narvaro_procent`     DECIMAL(5,2) DEFAULT 0,
    `team_mal_procent`    DECIMAL(5,2) DEFAULT 0,
    `bonus_ibc`           DECIMAL(10,2) DEFAULT 0,
    `bonus_kvalitet`      DECIMAL(10,2) DEFAULT 0,
    `bonus_narvaro`       DECIMAL(10,2) DEFAULT 0,
    `bonus_team`          DECIMAL(10,2) DEFAULT 0,
    `total_bonus`         DECIMAL(10,2) DEFAULT 0,
    `skapad_at`           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_operator_id`   (`operator_id`),
    KEY `idx_period`        (`period_start`, `period_slut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

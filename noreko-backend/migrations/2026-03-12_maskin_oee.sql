-- Migration: maskin_oee — OEE-konfiguration per maskin
-- Datum: 2026-03-12
-- Beskrivning: Tabell for OEE-mal och ideal cykeltider per maskin

CREATE TABLE IF NOT EXISTS `maskin_oee_config` (
    `id`                INT AUTO_INCREMENT PRIMARY KEY,
    `maskin_id`         INT NOT NULL,
    `planerad_tid_min`  DECIMAL(8,2) NOT NULL DEFAULT 480 COMMENT 'Planerad drifttid per dag (min), standard 8h',
    `ideal_cykeltid_sek` DECIMAL(8,2) NOT NULL DEFAULT 120 COMMENT 'Ideal cykeltid per IBC (sekunder)',
    `oee_mal_pct`       DECIMAL(5,2) NOT NULL DEFAULT 85.00 COMMENT 'OEE-mal i procent',
    `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by`        INT DEFAULT NULL,
    UNIQUE KEY `uk_maskin_id` (`maskin_id`),
    KEY `idx_maskin_id` (`maskin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed-data: OEE-konfiguration for de 6 befintliga maskinerna
INSERT IGNORE INTO `maskin_oee_config` (`maskin_id`, `planerad_tid_min`, `ideal_cykeltid_sek`, `oee_mal_pct`) VALUES
(1, 480, 120, 85.00),
(2, 480, 90, 85.00),
(3, 480, 60, 85.00),
(4, 480, 30, 85.00),
(5, 480, 45, 85.00),
(6, 480, 60, 85.00);

-- Tabell for daglig OEE-snapshot per maskin (for trend-diagram)
CREATE TABLE IF NOT EXISTS `maskin_oee_daglig` (
    `id`                INT AUTO_INCREMENT PRIMARY KEY,
    `maskin_id`         INT NOT NULL,
    `datum`             DATE NOT NULL,
    `planerad_tid_min`  DECIMAL(8,2) NOT NULL DEFAULT 0,
    `drifttid_min`      DECIMAL(8,2) NOT NULL DEFAULT 0,
    `stopptid_min`      DECIMAL(8,2) NOT NULL DEFAULT 0,
    `total_output`      INT NOT NULL DEFAULT 0,
    `ok_output`         INT NOT NULL DEFAULT 0,
    `kassation`         INT NOT NULL DEFAULT 0,
    `ideal_cykeltid_sek` DECIMAL(8,2) NOT NULL DEFAULT 120,
    `tillganglighet_pct` DECIMAL(5,2) DEFAULT NULL,
    `prestanda_pct`     DECIMAL(5,2) DEFAULT NULL,
    `kvalitet_pct`      DECIMAL(5,2) DEFAULT NULL,
    `oee_pct`           DECIMAL(5,2) DEFAULT NULL,
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_maskin_datum` (`maskin_id`, `datum`),
    KEY `idx_datum` (`datum`),
    KEY `idx_maskin_id` (`maskin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed demo-data for senaste 30 dagarna
-- Vi insertar via en procedur-liknande approach med variabler
INSERT IGNORE INTO `maskin_oee_daglig`
    (`maskin_id`, `datum`, `planerad_tid_min`, `drifttid_min`, `stopptid_min`,
     `total_output`, `ok_output`, `kassation`, `ideal_cykeltid_sek`,
     `tillganglighet_pct`, `prestanda_pct`, `kvalitet_pct`, `oee_pct`)
SELECT
    m.id AS maskin_id,
    DATE_SUB(CURDATE(), INTERVAL seq.n DAY) AS datum,
    480 AS planerad_tid_min,
    ROUND(480 - (RAND() * 60 + 10), 2) AS drifttid_min,
    ROUND(RAND() * 60 + 10, 2) AS stopptid_min,
    FLOOR(RAND() * 80 + 120) AS total_output,
    FLOOR(RAND() * 75 + 115) AS ok_output,
    FLOOR(RAND() * 8 + 1) AS kassation,
    oc.ideal_cykeltid_sek,
    NULL, NULL, NULL, NULL
FROM maskin_register m
JOIN maskin_oee_config oc ON oc.maskin_id = m.id
CROSS JOIN (
    SELECT 0 AS n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
    UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9
    UNION SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14
    UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19
    UNION SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24
    UNION SELECT 25 UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29
) seq
WHERE m.aktiv = 1;

-- Uppdatera beraknade OEE-varden
UPDATE `maskin_oee_daglig`
SET
    tillganglighet_pct = ROUND((drifttid_min / planerad_tid_min) * 100, 2),
    prestanda_pct = ROUND(LEAST((total_output * ideal_cykeltid_sek / 60) / drifttid_min * 100, 100), 2),
    kvalitet_pct = CASE WHEN total_output > 0 THEN ROUND((ok_output / total_output) * 100, 2) ELSE 0 END,
    oee_pct = ROUND(
        (drifttid_min / planerad_tid_min) *
        LEAST((total_output * ideal_cykeltid_sek / 60) / drifttid_min, 1) *
        CASE WHEN total_output > 0 THEN (ok_output / total_output) ELSE 0 END * 100
    , 2)
WHERE tillganglighet_pct IS NULL;

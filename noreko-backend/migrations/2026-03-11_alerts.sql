-- =============================================================
-- Alerts / Notifieringssystem för VD
-- 2026-03-11
-- =============================================================

-- Tabell: alerts — sparar triggade varningar
CREATE TABLE IF NOT EXISTS `alerts` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type`             ENUM('oee_low','stop_long','scrap_high') NOT NULL,
    `message`          VARCHAR(500) NOT NULL,
    `value`            DECIMAL(10,2) DEFAULT NULL COMMENT 'Det uppmätta värdet som triggade alerten',
    `threshold`        DECIMAL(10,2) DEFAULT NULL COMMENT 'Tröskelvärdets gränsvärde',
    `severity`         ENUM('warning','critical') NOT NULL DEFAULT 'warning',
    `acknowledged`     TINYINT(1) NOT NULL DEFAULT 0,
    `acknowledged_by`  INT UNSIGNED DEFAULT NULL COMMENT 'user_id som kvitterade',
    `acknowledged_at`  DATETIME DEFAULT NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_acknowledged` (`acknowledged`),
    INDEX `idx_type` (`type`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabell: alert_settings — konfigurerbara tröskelvärden
CREATE TABLE IF NOT EXISTS `alert_settings` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type`            ENUM('oee_low','stop_long','scrap_high') NOT NULL,
    `threshold_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `enabled`         TINYINT(1) NOT NULL DEFAULT 1,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by`      INT UNSIGNED DEFAULT NULL COMMENT 'user_id som senast ändrade',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Standardinställningar (OEE < 60%, stopp > 30 min, kassation > 10%)
INSERT IGNORE INTO `alert_settings` (`type`, `threshold_value`, `enabled`) VALUES
    ('oee_low',    60.00, 1),
    ('stop_long',  30.00, 1),
    ('scrap_high', 10.00, 1);

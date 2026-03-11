-- Migration: 2026-03-11_production-goals.sql
-- Skapar tabell för produktionsmål (dagsmål och veckamål)

CREATE TABLE IF NOT EXISTS `rebotling_production_goals` (
    `id`           INT          NOT NULL AUTO_INCREMENT,
    `period_type`  ENUM('daily','weekly') NOT NULL DEFAULT 'daily',
    `target_count` INT          NOT NULL DEFAULT 0,
    `created_by`   INT          NULL DEFAULT NULL,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_period_type` (`period_type`),
    KEY `idx_created_at`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Produktionsmål för rebotling (dagsmål och veckamål)';

-- Standardvärden: dagsmål 200 IBC, veckamål 1000 IBC
INSERT IGNORE INTO `rebotling_production_goals` (`id`, `period_type`, `target_count`, `created_by`)
VALUES
  (1, 'daily',   200, NULL),
  (2, 'weekly', 1000, NULL);

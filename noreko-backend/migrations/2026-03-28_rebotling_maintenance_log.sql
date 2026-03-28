-- Migration: Skapa rebotling_maintenance_log
-- Session #373, Worker A, 2026-03-28
-- Används av RebotlingAdminController::saveMaintenanceLog()
-- Anropas från frontend: rebotling-admin.ts saveMaintenanceLog()

CREATE TABLE IF NOT EXISTS `rebotling_maintenance_log` (
  `id`                INT          NOT NULL AUTO_INCREMENT,
  `action_text`       TEXT         NOT NULL,
  `logged_by_user_id` INT          DEFAULT NULL,
  `logged_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logged_at` (`logged_at`),
  KEY `idx_user_id` (`logged_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

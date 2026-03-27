-- Session #355: Ensure rebotling_kv_settings table exists
-- This table is used by RebotlingController and RebotlingAdminController
-- for live ranking settings, column config, and service status.
-- It exists on prod but was missing from the schema dump.

CREATE TABLE IF NOT EXISTS `rebotling_kv_settings` (
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

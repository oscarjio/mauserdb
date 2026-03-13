-- Kapacitet-konfiguration per rebotling-station
-- Teoretisk kapacitet, mal-utnyttjandegrad och bemanningsfaktor
-- Skapad: 2026-03-13

CREATE TABLE IF NOT EXISTS `kapacitet_config` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `station_id` VARCHAR(30) NOT NULL,
  `station_namn` VARCHAR(60) NOT NULL,
  `teoretisk_kapacitet_per_timme` DECIMAL(8,2) NOT NULL DEFAULT 30.00,
  `mal_utnyttjandegrad_pct` DECIMAL(5,2) NOT NULL DEFAULT 85.00,
  `ibc_per_operator_timme` DECIMAL(8,2) NOT NULL DEFAULT 15.00,
  `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_station_id` (`station_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed-data: rebotling-stationer
INSERT INTO `kapacitet_config` (`station_id`, `station_namn`, `teoretisk_kapacitet_per_timme`, `mal_utnyttjandegrad_pct`, `ibc_per_operator_timme`) VALUES
('station_1', 'Station 1', 30.00, 85.00, 15.00),
('station_2', 'Station 2', 30.00, 85.00, 15.00),
('station_3', 'Station 3', 28.00, 85.00, 14.00),
('station_4', 'Station 4', 25.00, 80.00, 12.50),
('station_5', 'Station 5', 30.00, 85.00, 15.00),
('station_6', 'Station 6', 26.00, 82.00, 13.00)
ON DUPLICATE KEY UPDATE
  `station_namn` = VALUES(`station_namn`),
  `teoretisk_kapacitet_per_timme` = VALUES(`teoretisk_kapacitet_per_timme`),
  `mal_utnyttjandegrad_pct` = VALUES(`mal_utnyttjandegrad_pct`),
  `ibc_per_operator_timme` = VALUES(`ibc_per_operator_timme`);

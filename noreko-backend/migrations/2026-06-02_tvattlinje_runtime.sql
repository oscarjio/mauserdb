-- Skapar tvattlinje_runtime som fallback-tabell för rast-hantering
-- Samma struktur som tvattlinje_rast men utan rast_today-kolumnen
CREATE TABLE IF NOT EXISTS `tvattlinje_runtime` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `datum` datetime NOT NULL DEFAULT current_timestamp(),
  `rast_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = arbetar, 1 = på rast',
  PRIMARY KEY (`id`),
  KEY `idx_datum` (`datum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Produktions-SLA/Måluppfyllnad: tabell för dagliga/veckovisa produktionsmål
-- Skapad: 2026-03-12

CREATE TABLE IF NOT EXISTS `produktions_mal` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mal_typ` ENUM('dagligt','veckovist') NOT NULL DEFAULT 'dagligt',
  `target_ibc` INT UNSIGNED NOT NULL DEFAULT 80,
  `target_kassation_pct` DECIMAL(5,2) NOT NULL DEFAULT 5.00,
  `giltig_from` DATE NOT NULL,
  `giltig_tom` DATE NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_mal_typ` (`mal_typ`),
  INDEX `idx_mal_giltig` (`giltig_from`, `giltig_tom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed-data: standardmål
INSERT INTO `produktions_mal` (`mal_typ`, `target_ibc`, `target_kassation_pct`, `giltig_from`, `giltig_tom`, `created_by`, `created_at`) VALUES
('dagligt',   80,  5.00, '2026-01-01', NULL, 1, '2026-01-01 00:00:00'),
('veckovist', 400, 4.00, '2026-01-01', NULL, 1, '2026-01-01 00:00:00');

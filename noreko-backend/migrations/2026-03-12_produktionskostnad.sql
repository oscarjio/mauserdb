-- Produktionskostnad per IBC: konfigurationstabell för kostnadsfaktorer
-- Skapad: 2026-03-12

CREATE TABLE IF NOT EXISTS `produktionskostnad_config` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `faktor` ENUM('energi','bemanning','material','kassation','overhead') NOT NULL,
  `varde` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `enhet` VARCHAR(20) NOT NULL DEFAULT 'kr',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_faktor` (`faktor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed-data: standardvärden
INSERT INTO `produktionskostnad_config` (`faktor`, `varde`, `enhet`) VALUES
('energi',    150.00, 'kr/h'),
('bemanning', 350.00, 'kr/h'),
('material',   50.00, 'kr/IBC'),
('kassation', 200.00, 'kr/IBC'),
('overhead',  100.00, 'kr/h')
ON DUPLICATE KEY UPDATE `varde` = VALUES(`varde`), `enhet` = VALUES(`enhet`);

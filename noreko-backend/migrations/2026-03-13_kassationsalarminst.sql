-- Kassationskvot-alarm: troskelinstallningar for automatisk varning
-- Skapad: 2026-03-13

CREATE TABLE IF NOT EXISTS `rebotling_kassationsalarminst` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `varning_procent` DECIMAL(5,2) NOT NULL DEFAULT 3.00 COMMENT 'Gul varning (%)',
  `alarm_procent`   DECIMAL(5,2) NOT NULL DEFAULT 5.00 COMMENT 'Rod alarm (%)',
  `skapad_av`       INT UNSIGNED NULL,
  `skapad_datum`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_skapad_datum` (`skapad_datum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Standardinstallning
INSERT INTO `rebotling_kassationsalarminst` (`varning_procent`, `alarm_procent`, `skapad_av`)
VALUES (3.00, 5.00, NULL);

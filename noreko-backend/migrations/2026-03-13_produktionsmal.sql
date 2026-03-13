-- Produktionsmal-dashboard: tabell for VD att satta vecko/manadsmal
-- Skapad: 2026-03-13

CREATE TABLE IF NOT EXISTS `rebotling_produktionsmal` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `typ` ENUM('vecka','manad') NOT NULL DEFAULT 'vecka',
  `mal_antal` INT UNSIGNED NOT NULL,
  `start_datum` DATE NOT NULL,
  `slut_datum` DATE NOT NULL,
  `skapad_av` INT UNSIGNED NULL,
  `skapad_datum` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_typ` (`typ`),
  INDEX `idx_datum` (`start_datum`, `slut_datum`),
  INDEX `idx_aktiv` (`typ`, `slut_datum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

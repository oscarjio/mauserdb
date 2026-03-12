-- Batch-spårning: tabeller för att följa IBC-batchar genom produktionslinjen
-- Skapad: 2026-03-12

CREATE TABLE IF NOT EXISTS `batch_order` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_nummer` VARCHAR(100) NOT NULL,
  `planerat_antal` INT UNSIGNED NOT NULL DEFAULT 0,
  `kommentar` TEXT NULL,
  `status` ENUM('pagaende','klar','pausad') NOT NULL DEFAULT 'pagaende',
  `skapad_av` INT UNSIGNED NULL,
  `skapad_datum` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `avslutad_datum` DATETIME NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_batch_status` (`status`),
  INDEX `idx_batch_skapad` (`skapad_datum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `batch_ibc` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id` INT UNSIGNED NOT NULL,
  `ibc_nummer` VARCHAR(100) NULL,
  `operator_id` INT UNSIGNED NULL,
  `startad` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `klar` DATETIME NULL,
  `kasserad` TINYINT(1) NOT NULL DEFAULT 0,
  `cykeltid_sekunder` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_batch_ibc_batch` (`batch_id`),
  INDEX `idx_batch_ibc_operator` (`operator_id`),
  CONSTRAINT `fk_batch_ibc_batch` FOREIGN KEY (`batch_id`) REFERENCES `batch_order` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed-data: exempelbatchar

INSERT INTO `batch_order` (`id`, `batch_nummer`, `planerat_antal`, `kommentar`, `status`, `skapad_av`, `skapad_datum`, `avslutad_datum`) VALUES
(1, 'BATCH-2026-0301', 10, 'Standardtvätt Mauser 1000L', 'klar', 1, '2026-03-01 07:00:00', '2026-03-01 15:30:00'),
(2, 'BATCH-2026-0305', 15, 'Kemikalietvätt specialorder', 'pagaende', 1, '2026-03-05 06:30:00', NULL),
(3, 'BATCH-2026-0310', 8, 'Expresstvätt prioriterad', 'pausad', 1, '2026-03-10 08:00:00', NULL);

-- Seed IBC:er för batch 1 (klar — 10 av 10, 1 kasserad)
INSERT INTO `batch_ibc` (`batch_id`, `ibc_nummer`, `operator_id`, `startad`, `klar`, `kasserad`, `cykeltid_sekunder`) VALUES
(1, 'IBC-10001', 1, '2026-03-01 07:05:00', '2026-03-01 07:52:00', 0, 2820),
(1, 'IBC-10002', 1, '2026-03-01 07:55:00', '2026-03-01 08:40:00', 0, 2700),
(1, 'IBC-10003', 2, '2026-03-01 08:45:00', '2026-03-01 09:30:00', 0, 2700),
(1, 'IBC-10004', 2, '2026-03-01 09:35:00', '2026-03-01 10:25:00', 1, 3000),
(1, 'IBC-10005', 1, '2026-03-01 10:30:00', '2026-03-01 11:15:00', 0, 2700),
(1, 'IBC-10006', 2, '2026-03-01 11:20:00', '2026-03-01 12:05:00', 0, 2700),
(1, 'IBC-10007', 1, '2026-03-01 12:30:00', '2026-03-01 13:15:00', 0, 2700),
(1, 'IBC-10008', 2, '2026-03-01 13:20:00', '2026-03-01 14:05:00', 0, 2700),
(1, 'IBC-10009', 1, '2026-03-01 14:10:00', '2026-03-01 14:55:00', 0, 2700),
(1, 'IBC-10010', 2, '2026-03-01 15:00:00', '2026-03-01 15:30:00', 0, 1800);

-- Seed IBC:er för batch 2 (pågående — 9 av 15 klara, 1 kasserad)
INSERT INTO `batch_ibc` (`batch_id`, `ibc_nummer`, `operator_id`, `startad`, `klar`, `kasserad`, `cykeltid_sekunder`) VALUES
(2, 'IBC-20001', 1, '2026-03-05 06:35:00', '2026-03-05 07:20:00', 0, 2700),
(2, 'IBC-20002', 2, '2026-03-05 07:25:00', '2026-03-05 08:10:00', 0, 2700),
(2, 'IBC-20003', 1, '2026-03-05 08:15:00', '2026-03-05 09:00:00', 0, 2700),
(2, 'IBC-20004', 2, '2026-03-05 09:05:00', '2026-03-05 09:55:00', 1, 3000),
(2, 'IBC-20005', 1, '2026-03-05 10:00:00', '2026-03-05 10:45:00', 0, 2700),
(2, 'IBC-20006', 2, '2026-03-05 10:50:00', '2026-03-05 11:35:00', 0, 2700),
(2, 'IBC-20007', 1, '2026-03-05 11:40:00', '2026-03-05 12:25:00', 0, 2700),
(2, 'IBC-20008', 2, '2026-03-05 12:30:00', '2026-03-05 13:15:00', 0, 2700),
(2, 'IBC-20009', 1, '2026-03-05 13:20:00', '2026-03-05 14:05:00', 0, 2700);

-- Seed IBC:er för batch 3 (pausad — 3 av 8 klara)
INSERT INTO `batch_ibc` (`batch_id`, `ibc_nummer`, `operator_id`, `startad`, `klar`, `kasserad`, `cykeltid_sekunder`) VALUES
(3, 'IBC-30001', 2, '2026-03-10 08:05:00', '2026-03-10 08:50:00', 0, 2700),
(3, 'IBC-30002', 2, '2026-03-10 08:55:00', '2026-03-10 09:40:00', 0, 2700),
(3, 'IBC-30003', 1, '2026-03-10 09:45:00', '2026-03-10 10:30:00', 0, 2700);

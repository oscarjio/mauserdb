-- Leveransplanering: kundordrar och produktionskapacitet
-- Migration: 2026-03-12

CREATE TABLE IF NOT EXISTS `kundordrar` (
    `id`                     INT AUTO_INCREMENT PRIMARY KEY,
    `kundnamn`               VARCHAR(255) NOT NULL,
    `antal_ibc`              INT NOT NULL DEFAULT 0,
    `bestallningsdatum`      DATE NOT NULL,
    `onskat_leveransdatum`   DATE NOT NULL,
    `beraknat_leveransdatum` DATE DEFAULT NULL,
    `status`                 ENUM('planerad','i_produktion','levererad','forsenad') NOT NULL DEFAULT 'planerad',
    `prioritet`              INT NOT NULL DEFAULT 5,
    `notering`               TEXT DEFAULT NULL,
    `skapad_datum`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `uppdaterad_datum`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `produktionskapacitet_config` (
    `id`                       INT AUTO_INCREMENT PRIMARY KEY,
    `kapacitet_per_dag`        INT NOT NULL DEFAULT 80,
    `planerade_underhallsdagar` TEXT DEFAULT NULL COMMENT 'JSON-array med datum',
    `buffer_procent`           INT NOT NULL DEFAULT 10,
    `uppdaterad_datum`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed kapacitetskonfiguration
INSERT IGNORE INTO `produktionskapacitet_config` (`id`, `kapacitet_per_dag`, `planerade_underhallsdagar`, `buffer_procent`)
VALUES (1, 80, '["2026-03-20","2026-04-03","2026-04-17"]', 10);

-- Seed exempelordrar
INSERT INTO `kundordrar` (`kundnamn`, `antal_ibc`, `bestallningsdatum`, `onskat_leveransdatum`, `beraknat_leveransdatum`, `status`, `prioritet`, `notering`) VALUES
('BASF Ludwigshafen',     120, '2026-02-15', '2026-03-20', '2026-03-18', 'i_produktion', 1, 'Prioriterad kund, express'),
('Brenntag Nordic',        80, '2026-02-20', '2026-03-25', '2026-03-24', 'i_produktion', 3, NULL),
('Perstorp Specialty',     60, '2026-03-01', '2026-03-28', '2026-03-27', 'planerad',      5, NULL),
('AkzoNobel Stenungsund', 150, '2026-03-02', '2026-04-01', '2026-04-05', 'forsenad',      2, 'Stor order, kapacitetsbrist'),
('Borealis AB',            45, '2026-03-05', '2026-03-30', '2026-03-29', 'planerad',      4, NULL),
('Nouryon Gothenburg',     90, '2026-03-08', '2026-04-05', '2026-04-04', 'planerad',      3, NULL),
('Clariant Nordics',       70, '2026-02-10', '2026-03-10', '2026-03-10', 'levererad',     5, 'Levererad i tid'),
('Evonik Industries',     200, '2026-03-10', '2026-04-15', '2026-04-20', 'forsenad',      1, 'Mycket stor order, kan bli sen'),
('Kemira OY',              55, '2026-03-01', '2026-03-22', '2026-03-22', 'levererad',     4, NULL),
('Solvay Belgium',        100, '2026-03-09', '2026-04-10', '2026-04-09', 'planerad',      2, 'Ny kund, viktig forsta leverans');

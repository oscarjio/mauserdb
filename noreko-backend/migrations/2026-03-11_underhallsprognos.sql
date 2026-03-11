-- Migration: 2026-03-11_underhallsprognos.sql
-- Skapar tabeller för underhållsprognos (prediktivt underhåll)
-- Tabeller: underhall_komponenter, underhall_scheman

-- Tabell för underhållskomponenter (maskiner/komponenter som kräver underhåll)
CREATE TABLE IF NOT EXISTS `underhall_komponenter` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `namn` VARCHAR(100) NOT NULL,
    `maskin` VARCHAR(100) NOT NULL DEFAULT 'Rebotling',
    `kategori` VARCHAR(50) NOT NULL DEFAULT 'Mekaniskt',
    `beskrivning` TEXT NULL,
    `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
    `skapad` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_maskin` (`maskin`),
    KEY `idx_aktiv` (`aktiv`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabell för underhållsscheman (intervall + senaste service per komponent)
CREATE TABLE IF NOT EXISTS `underhall_scheman` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `komponent_id` INT NOT NULL,
    `intervall_dagar` INT NOT NULL DEFAULT 30 COMMENT 'Underhållsintervall i dagar',
    `senaste_underhall` DATETIME NULL COMMENT 'Datum för senaste utförda underhåll',
    `nasta_planerat` DATETIME NULL COMMENT 'Manuellt satt nästa datum (override)',
    `ansvarig` VARCHAR(100) NULL,
    `noteringar` TEXT NULL,
    `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
    `skapad` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `uppdaterad` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_komponent_id` (`komponent_id`),
    KEY `idx_senaste_underhall` (`senaste_underhall`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Standardkomponenter för rebotling-linjen
INSERT IGNORE INTO `underhall_komponenter` (`id`, `namn`, `maskin`, `kategori`, `beskrivning`) VALUES
(1,  'Huvud-pump',            'Rebotling',         'Hydraulik',    'Primär hydraulpump i rebotling-linjen'),
(2,  'Transportband',         'Rebotling',         'Mekaniskt',    'Transportband för IBC-förflyttning'),
(3,  'Hydraulsystem',         'Rebotling',         'Hydraulik',    'Hydraulsystem inkl filter och fluid'),
(4,  'Elektromotor',          'Rebotling',         'Elektriskt',   'Drivmotor till rebotling-linjen'),
(5,  'Luftfilter',            'Rebotling',         'Pneumatik',    'Pneumatiskt luftfilter'),
(6,  'Säkerhetsventil',       'Rebotling',         'Pneumatik',    'Övertrycksventil i pneumatiksystemet'),
(7,  'Smörjsystem',           'Rebotling',         'Mekaniskt',    'Automatiskt smörjsystem'),
(8,  'PLC-backup',            'Rebotling',         'Elektriskt',   'Säkerhetskopiering av PLC-program'),
(9,  'Högtryckstvätt',        'Tvättlinje',        'Rengöring',    'Högtrycksaggregat för IBC-tvätt'),
(10, 'Rörledningar',          'Tvättlinje',        'Mekaniskt',    'Tvättlinjens rörsystem och kopplingar'),
(11, 'Sagband',               'Såglinje',          'Mekaniskt',    'Sagblad och spanningsanordning'),
(12, 'Kalibreringspunkt',     'Klassificeringslinje', 'Kalibrering', 'Vikt- och dimensionskalibrering');

-- Standardscheman (kopplade till komponenterna ovan)
INSERT IGNORE INTO `underhall_scheman` (`id`, `komponent_id`, `intervall_dagar`, `senaste_underhall`, `ansvarig`) VALUES
(1,  1,  90,  DATE_SUB(NOW(), INTERVAL 75  DAY), 'Tekniker'),
(2,  2,  30,  DATE_SUB(NOW(), INTERVAL 28  DAY), 'Tekniker'),
(3,  3,  180, DATE_SUB(NOW(), INTERVAL 200 DAY), 'Tekniker'),
(4,  4,  365, DATE_SUB(NOW(), INTERVAL 300 DAY), 'Elektriker'),
(5,  5,  14,  DATE_SUB(NOW(), INTERVAL 10  DAY), 'Tekniker'),
(6,  6,  30,  DATE_SUB(NOW(), INTERVAL 35  DAY), 'Tekniker'),
(7,  7,  7,   DATE_SUB(NOW(), INTERVAL 5   DAY), 'Tekniker'),
(8,  8,  90,  DATE_SUB(NOW(), INTERVAL 20  DAY), 'IT/Elektriker'),
(9,  9,  30,  DATE_SUB(NOW(), INTERVAL 32  DAY), 'Tekniker'),
(10, 10, 180, DATE_SUB(NOW(), INTERVAL 50  DAY), 'Rörmontör'),
(11, 11, 14,  DATE_SUB(NOW(), INTERVAL 16  DAY), 'Tekniker'),
(12, 12, 90,  DATE_SUB(NOW(), INTERVAL 45  DAY), 'Kalibreringstekniker');

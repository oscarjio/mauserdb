-- Migration: 2026-03-12_stopptidsanalys.sql
-- Stopptidsanalys per maskin — ny tabell för maskin-specifika stopptider
-- Kopplas till maskin_register (skapad i 2026-03-12_maskinunderhall.sql)

CREATE TABLE IF NOT EXISTS `maskin_stopptid` (
    `id`              INT NOT NULL AUTO_INCREMENT,
    `maskin_id`       INT NOT NULL COMMENT 'Referens till maskin_register.id',
    `maskin_namn`     VARCHAR(100) NOT NULL COMMENT 'Denormaliserat maskinnamn (cache)',
    `startad_at`      DATETIME NOT NULL,
    `avslutad_at`     DATETIME DEFAULT NULL,
    `duration_min`    DECIMAL(8,2) DEFAULT NULL COMMENT 'Varaktighet i minuter (beräknas vid avslut)',
    `orsak`           VARCHAR(200) DEFAULT NULL COMMENT 'Fri text eller kategorinamn',
    `orsak_kategori`  ENUM('maskin','material','operatör','planerat','övrigt') NOT NULL DEFAULT 'övrigt',
    `operator_id`     INT DEFAULT NULL COMMENT 'users.id för den som registrerade',
    `operator_namn`   VARCHAR(100) DEFAULT NULL COMMENT 'Denormaliserat operatörnamn',
    `kommentar`       TEXT DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_maskin_id`   (`maskin_id`),
    KEY `idx_startad_at`  (`startad_at`),
    KEY `idx_avslutad_at` (`avslutad_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Maskin-specifika stopptider för rebotling-linjen';

-- Säkerställ att maskin_register finns (om maskinunderhall-migrationen inte körts)
CREATE TABLE IF NOT EXISTS `maskin_register` (
    `id`                        INT AUTO_INCREMENT PRIMARY KEY,
    `namn`                      VARCHAR(100) NOT NULL,
    `beskrivning`               TEXT,
    `service_intervall_dagar`   INT NOT NULL DEFAULT 90,
    `aktiv`                     TINYINT(1) DEFAULT 1,
    `created_at`                TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed-data för maskin_register (IGNORE om redan finns)
INSERT IGNORE INTO `maskin_register` (`id`, `namn`, `beskrivning`, `service_intervall_dagar`) VALUES
(1, 'Tvättmaskin',        'Huvudtvätt för IBC-tankar',         90),
(2, 'Torkugn',            'Torkstation efter tvätt',           180),
(3, 'Inspektionsstation', 'Visuell kontroll och test',          60),
(4, 'Transportband',      'Huvudtransportör rebotling-linje',   30),
(5, 'Etiketterare',       'Automatisk märkningsmaskin',         45),
(6, 'Ventiltestare',      'Testning av IBC-ventiler',           60);

-- Demo-data: stopphändelser senaste 30 dagarna (varierade maskiner)
INSERT INTO `maskin_stopptid`
    (`maskin_id`, `maskin_namn`, `startad_at`, `avslutad_at`, `duration_min`, `orsak`, `orsak_kategori`, `operator_namn`)
VALUES
-- Tvättmaskin (mest stopp)
(1,'Tvättmaskin', DATE_SUB(NOW(), INTERVAL 1  DAY) + INTERVAL 6  HOUR,  DATE_SUB(NOW(), INTERVAL 1  DAY) + INTERVAL 6  HOUR + INTERVAL 35 MINUTE, 35.0,  'PLC-larm',           'maskin',   'Anna Svensson'),
(1,'Tvättmaskin', DATE_SUB(NOW(), INTERVAL 2  DAY) + INTERVAL 8  HOUR,  DATE_SUB(NOW(), INTERVAL 2  DAY) + INTERVAL 8  HOUR + INTERVAL 20 MINUTE, 20.0,  'Sensorkalibrering',  'maskin',   'Erik Lindqvist'),
(1,'Tvättmaskin', DATE_SUB(NOW(), INTERVAL 3  DAY) + INTERVAL 14 HOUR,  DATE_SUB(NOW(), INTERVAL 3  DAY) + INTERVAL 14 HOUR + INTERVAL 55 MINUTE, 55.0,  'Mekaniskt fel',      'maskin',   'Anna Svensson'),
(1,'Tvättmaskin', DATE_SUB(NOW(), INTERVAL 5  DAY) + INTERVAL 9  HOUR,  DATE_SUB(NOW(), INTERVAL 5  DAY) + INTERVAL 9  HOUR + INTERVAL 15 MINUTE, 15.0,  'Rengöring',          'planerat', 'Maria Johansson'),
(1,'Tvättmaskin', DATE_SUB(NOW(), INTERVAL 7  DAY) + INTERVAL 7  HOUR,  DATE_SUB(NOW(), INTERVAL 7  DAY) + INTERVAL 7  HOUR + INTERVAL 45 MINUTE, 45.0,  'Ventilfel',          'maskin',   'Erik Lindqvist'),
(1,'Tvättmaskin', DATE_SUB(NOW(), INTERVAL 10 DAY) + INTERVAL 11 HOUR,  DATE_SUB(NOW(), INTERVAL 10 DAY) + INTERVAL 11 HOUR + INTERVAL 30 MINUTE, 30.0,  'PLC-larm',           'maskin',   'Anna Svensson'),
(1,'Tvättmaskin', DATE_SUB(NOW(), INTERVAL 14 DAY) + INTERVAL 6  HOUR,  DATE_SUB(NOW(), INTERVAL 14 DAY) + INTERVAL 6  HOUR + INTERVAL 25 MINUTE, 25.0,  'Materialbrist',      'material', 'Peter Olsson'),
(1,'Tvättmaskin', DATE_SUB(NOW(), INTERVAL 18 DAY) + INTERVAL 15 HOUR,  DATE_SUB(NOW(), INTERVAL 18 DAY) + INTERVAL 15 HOUR + INTERVAL 60 MINUTE, 60.0,  'Mekaniskt fel',      'maskin',   'Erik Lindqvist'),
(1,'Tvättmaskin', DATE_SUB(NOW(), INTERVAL 22 DAY) + INTERVAL 8  HOUR,  DATE_SUB(NOW(), INTERVAL 22 DAY) + INTERVAL 8  HOUR + INTERVAL 18 MINUTE, 18.0,  'Sensorkalibrering',  'maskin',   'Anna Svensson'),
(1,'Tvättmaskin', DATE_SUB(NOW(), INTERVAL 28 DAY) + INTERVAL 13 HOUR,  DATE_SUB(NOW(), INTERVAL 28 DAY) + INTERVAL 13 HOUR + INTERVAL 40 MINUTE, 40.0,  'Ventilfel',          'maskin',   'Maria Johansson'),
-- Transportband
(4,'Transportband', DATE_SUB(NOW(), INTERVAL 1  DAY) + INTERVAL 10 HOUR, DATE_SUB(NOW(), INTERVAL 1  DAY) + INTERVAL 10 HOUR + INTERVAL 12 MINUTE, 12.0, 'Mekaniskt fel',      'maskin',   'Peter Olsson'),
(4,'Transportband', DATE_SUB(NOW(), INTERVAL 3  DAY) + INTERVAL 7  HOUR, DATE_SUB(NOW(), INTERVAL 3  DAY) + INTERVAL 7  HOUR + INTERVAL 28 MINUTE, 28.0, 'Transportband stopp','maskin',   'Anna Svensson'),
(4,'Transportband', DATE_SUB(NOW(), INTERVAL 6  DAY) + INTERVAL 14 HOUR, DATE_SUB(NOW(), INTERVAL 6  DAY) + INTERVAL 14 HOUR + INTERVAL 8  MINUTE, 8.0,  'Rengöring',          'planerat', 'Erik Lindqvist'),
(4,'Transportband', DATE_SUB(NOW(), INTERVAL 12 DAY) + INTERVAL 9  HOUR, DATE_SUB(NOW(), INTERVAL 12 DAY) + INTERVAL 9  HOUR + INTERVAL 35 MINUTE, 35.0, 'Mekaniskt fel',      'maskin',   'Maria Johansson'),
(4,'Transportband', DATE_SUB(NOW(), INTERVAL 20 DAY) + INTERVAL 6  HOUR, DATE_SUB(NOW(), INTERVAL 20 DAY) + INTERVAL 6  HOUR + INTERVAL 15 MINUTE, 15.0, 'PLC-larm',           'maskin',   'Peter Olsson'),
-- Torkugn
(2,'Torkugn', DATE_SUB(NOW(), INTERVAL 2  DAY) + INTERVAL 12 HOUR, DATE_SUB(NOW(), INTERVAL 2  DAY) + INTERVAL 12 HOUR + INTERVAL 22 MINUTE, 22.0, 'Temperaturfel',      'maskin',   'Anna Svensson'),
(2,'Torkugn', DATE_SUB(NOW(), INTERVAL 8  DAY) + INTERVAL 6  HOUR, DATE_SUB(NOW(), INTERVAL 8  DAY) + INTERVAL 6  HOUR + INTERVAL 50 MINUTE, 50.0, 'Mekaniskt fel',      'maskin',   'Erik Lindqvist'),
(2,'Torkugn', DATE_SUB(NOW(), INTERVAL 15 DAY) + INTERVAL 14 HOUR, DATE_SUB(NOW(), INTERVAL 15 DAY) + INTERVAL 14 HOUR + INTERVAL 17 MINUTE, 17.0, 'Rengöring',          'planerat', 'Maria Johansson'),
(2,'Torkugn', DATE_SUB(NOW(), INTERVAL 25 DAY) + INTERVAL 9  HOUR, DATE_SUB(NOW(), INTERVAL 25 DAY) + INTERVAL 9  HOUR + INTERVAL 33 MINUTE, 33.0, 'Temperaturfel',      'maskin',   'Anna Svensson'),
-- Inspektionsstation
(3,'Inspektionsstation', DATE_SUB(NOW(), INTERVAL 4  DAY) + INTERVAL 8  HOUR, DATE_SUB(NOW(), INTERVAL 4  DAY) + INTERVAL 8  HOUR + INTERVAL 10 MINUTE, 10.0, 'Sensorkalibrering', 'maskin',   'Peter Olsson'),
(3,'Inspektionsstation', DATE_SUB(NOW(), INTERVAL 9  DAY) + INTERVAL 11 HOUR, DATE_SUB(NOW(), INTERVAL 9  DAY) + INTERVAL 11 HOUR + INTERVAL 20 MINUTE, 20.0, 'Kalibrering',       'maskin',   'Anna Svensson'),
(3,'Inspektionsstation', DATE_SUB(NOW(), INTERVAL 16 DAY) + INTERVAL 7  HOUR, DATE_SUB(NOW(), INTERVAL 16 DAY) + INTERVAL 7  HOUR + INTERVAL 45 MINUTE, 45.0, 'Mekaniskt fel',     'maskin',   'Erik Lindqvist'),
-- Etiketterare
(5,'Etiketterare', DATE_SUB(NOW(), INTERVAL 2  DAY) + INTERVAL 9  HOUR, DATE_SUB(NOW(), INTERVAL 2  DAY) + INTERVAL 9  HOUR + INTERVAL 8  MINUTE, 8.0,  'Etikettjam',         'maskin',   'Maria Johansson'),
(5,'Etiketterare', DATE_SUB(NOW(), INTERVAL 11 DAY) + INTERVAL 13 HOUR, DATE_SUB(NOW(), INTERVAL 11 DAY) + INTERVAL 13 HOUR + INTERVAL 14 MINUTE, 14.0, 'Etikettjam',         'maskin',   'Peter Olsson'),
(5,'Etiketterare', DATE_SUB(NOW(), INTERVAL 23 DAY) + INTERVAL 8  HOUR, DATE_SUB(NOW(), INTERVAL 23 DAY) + INTERVAL 8  HOUR + INTERVAL 25 MINUTE, 25.0, 'Materialbrist',      'material', 'Anna Svensson'),
-- Ventiltestare
(6,'Ventiltestare', DATE_SUB(NOW(), INTERVAL 5  DAY) + INTERVAL 10 HOUR, DATE_SUB(NOW(), INTERVAL 5  DAY) + INTERVAL 10 HOUR + INTERVAL 15 MINUTE, 15.0, 'Ventilfel',          'maskin',   'Erik Lindqvist'),
(6,'Ventiltestare', DATE_SUB(NOW(), INTERVAL 17 DAY) + INTERVAL 14 HOUR, DATE_SUB(NOW(), INTERVAL 17 DAY) + INTERVAL 14 HOUR + INTERVAL 30 MINUTE, 30.0, 'Kalibrering',        'maskin',   'Maria Johansson');

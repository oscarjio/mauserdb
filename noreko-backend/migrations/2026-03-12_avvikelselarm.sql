-- Migration: 2026-03-12_avvikelselarm.sql
-- Automatiska avvikelselarm — larmsystem for produktionsavvikelser

CREATE TABLE IF NOT EXISTS `avvikelselarm` (
    `id`                    INT NOT NULL AUTO_INCREMENT,
    `typ`                   ENUM('oee','kassation','produktionstakt','maskinstopp','produktionsmal') NOT NULL,
    `allvarlighetsgrad`     ENUM('kritisk','varning','info') NOT NULL DEFAULT 'varning',
    `meddelande`            VARCHAR(500) NOT NULL,
    `varde_aktuellt`        DECIMAL(10,2) DEFAULT NULL COMMENT 'Aktuellt uppmatt varde',
    `varde_grans`           DECIMAL(10,2) DEFAULT NULL COMMENT 'Gransvarde som overskreds',
    `tidsstampel`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `kvitterad`             TINYINT(1) NOT NULL DEFAULT 0,
    `kvitterad_av`          VARCHAR(100) DEFAULT NULL,
    `kvitterad_datum`       DATETIME DEFAULT NULL,
    `kvitterings_kommentar` TEXT DEFAULT NULL,
    `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_typ`               (`typ`),
    KEY `idx_allvarlighetsgrad`  (`allvarlighetsgrad`),
    KEY `idx_kvitterad`          (`kvitterad`),
    KEY `idx_tidsstampel`        (`tidsstampel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Automatiska avvikelselarm for produktionen';

CREATE TABLE IF NOT EXISTS `larmregler` (
    `id`                INT NOT NULL AUTO_INCREMENT,
    `typ`               ENUM('oee','kassation','produktionstakt','maskinstopp','produktionsmal') NOT NULL,
    `allvarlighetsgrad` ENUM('kritisk','varning','info') NOT NULL DEFAULT 'varning',
    `grans_varde`       DECIMAL(10,2) NOT NULL,
    `aktiv`             TINYINT(1) NOT NULL DEFAULT 1,
    `beskrivning`       VARCHAR(300) NOT NULL,
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_typ` (`typ`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Konfigurerbara larmregler med troeskelvarden';

-- Seed: 5 standardregler
INSERT IGNORE INTO `larmregler` (`id`, `typ`, `allvarlighetsgrad`, `grans_varde`, `aktiv`, `beskrivning`) VALUES
(1, 'oee',              'varning',  65.00, 1, 'OEE under 65% — varning vid lag anlaggningseffektivitet'),
(2, 'kassation',        'varning',   5.00, 1, 'Kassation over 5% — varning vid hog kassationsgrad'),
(3, 'produktionstakt',  'varning',  10.00, 1, 'Produktionstakt under 10 IBC/h — varning vid lag takt'),
(4, 'maskinstopp',      'kritisk',  30.00, 1, 'Maskinstopp langre an 30 minuter — kritiskt larm'),
(5, 'produktionsmal',   'info',      0.00, 1, 'Produktionsmal ej uppnatt vid skiftslut — informationslarm');

-- Seed: 20 exempellarm (spridda over senaste 30 dagarna)
INSERT INTO `avvikelselarm` (`typ`, `allvarlighetsgrad`, `meddelande`, `varde_aktuellt`, `varde_grans`, `tidsstampel`, `kvitterad`, `kvitterad_av`, `kvitterad_datum`, `kvitterings_kommentar`) VALUES
-- Kritiska larm
('maskinstopp', 'kritisk', 'Tvattmaskin stoppad i 55 minuter — mekaniskt fel',            55.00, 30.00, DATE_SUB(NOW(), INTERVAL 1 DAY)  + INTERVAL 6 HOUR,  0, NULL, NULL, NULL),
('maskinstopp', 'kritisk', 'Transportband stoppat i 42 minuter — PLC-larm',               42.00, 30.00, DATE_SUB(NOW(), INTERVAL 2 DAY)  + INTERVAL 9 HOUR,  1, 'Erik Lindqvist', DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 10 HOUR, 'Reparerat, band bytt'),
('maskinstopp', 'kritisk', 'Torkugn stoppad i 50 minuter — temperaturfel',                50.00, 30.00, DATE_SUB(NOW(), INTERVAL 5 DAY)  + INTERVAL 14 HOUR, 1, 'Anna Svensson',  DATE_SUB(NOW(), INTERVAL 5 DAY) + INTERVAL 15 HOUR, 'Termoelement bytt'),
('maskinstopp', 'kritisk', 'Tvattmaskin stoppad i 60 minuter — ventilfel',                60.00, 30.00, DATE_SUB(NOW(), INTERVAL 8 DAY)  + INTERVAL 7 HOUR,  1, 'Peter Olsson',   DATE_SUB(NOW(), INTERVAL 8 DAY) + INTERVAL 8 HOUR,  'Ventil bytt'),
-- Varningslarm OEE
('oee',         'varning', 'OEE pa 58% — under gransvarde 65%',                           58.00, 65.00, DATE_SUB(NOW(), INTERVAL 1 DAY)  + INTERVAL 16 HOUR, 0, NULL, NULL, NULL),
('oee',         'varning', 'OEE pa 52% — under gransvarde 65%',                           52.00, 65.00, DATE_SUB(NOW(), INTERVAL 3 DAY)  + INTERVAL 15 HOUR, 1, 'Maria Johansson', DATE_SUB(NOW(), INTERVAL 3 DAY) + INTERVAL 16 HOUR, 'Okat bemanning'),
('oee',         'varning', 'OEE pa 61% — under gransvarde 65%',                           61.00, 65.00, DATE_SUB(NOW(), INTERVAL 7 DAY)  + INTERVAL 14 HOUR, 1, 'Erik Lindqvist',  DATE_SUB(NOW(), INTERVAL 7 DAY) + INTERVAL 15 HOUR, 'Justerat maskininst'),
('oee',         'varning', 'OEE pa 48% — under gransvarde 65%',                           48.00, 65.00, DATE_SUB(NOW(), INTERVAL 12 DAY) + INTERVAL 13 HOUR, 1, 'Anna Svensson',   DATE_SUB(NOW(), INTERVAL 12 DAY) + INTERVAL 14 HOUR, 'Personal sjuk, halv bemanning'),
-- Varningslarm kassation
('kassation',   'varning', 'Kassationsgrad 7.2% — over gransvarde 5%',                    7.20,  5.00,  DATE_SUB(NOW(), INTERVAL 2 DAY)  + INTERVAL 11 HOUR, 0, NULL, NULL, NULL),
('kassation',   'varning', 'Kassationsgrad 8.5% — over gransvarde 5%',                    8.50,  5.00,  DATE_SUB(NOW(), INTERVAL 4 DAY)  + INTERVAL 10 HOUR, 1, 'Peter Olsson',    DATE_SUB(NOW(), INTERVAL 4 DAY) + INTERVAL 11 HOUR, 'Batch med dalig ravar'),
('kassation',   'varning', 'Kassationsgrad 6.1% — over gransvarde 5%',                    6.10,  5.00,  DATE_SUB(NOW(), INTERVAL 9 DAY)  + INTERVAL 12 HOUR, 1, 'Maria Johansson', DATE_SUB(NOW(), INTERVAL 9 DAY) + INTERVAL 13 HOUR, 'Justerat tvattprogram'),
('kassation',   'varning', 'Kassationsgrad 9.3% — over gransvarde 5%',                    9.30,  5.00,  DATE_SUB(NOW(), INTERVAL 15 DAY) + INTERVAL 8 HOUR,  1, 'Erik Lindqvist',  DATE_SUB(NOW(), INTERVAL 15 DAY) + INTERVAL 9 HOUR, 'Ny leverantor av kemikalier'),
-- Varningslarm produktionstakt
('produktionstakt', 'varning', 'Produktionstakt 7 IBC/h — under gransvarde 10 IBC/h',     7.00,  10.00, DATE_SUB(NOW(), INTERVAL 1 DAY)  + INTERVAL 8 HOUR,  0, NULL, NULL, NULL),
('produktionstakt', 'varning', 'Produktionstakt 5 IBC/h — under gransvarde 10 IBC/h',     5.00,  10.00, DATE_SUB(NOW(), INTERVAL 6 DAY)  + INTERVAL 9 HOUR,  1, 'Anna Svensson',   DATE_SUB(NOW(), INTERVAL 6 DAY) + INTERVAL 10 HOUR, 'Maskinstopp lost'),
('produktionstakt', 'varning', 'Produktionstakt 8 IBC/h — under gransvarde 10 IBC/h',     8.00,  10.00, DATE_SUB(NOW(), INTERVAL 11 DAY) + INTERVAL 7 HOUR,  1, 'Peter Olsson',    DATE_SUB(NOW(), INTERVAL 11 DAY) + INTERVAL 8 HOUR, 'Nyanstallda paverkade takten'),
('produktionstakt', 'varning', 'Produktionstakt 6 IBC/h — under gransvarde 10 IBC/h',     6.00,  10.00, DATE_SUB(NOW(), INTERVAL 20 DAY) + INTERVAL 10 HOUR, 1, 'Maria Johansson', DATE_SUB(NOW(), INTERVAL 20 DAY) + INTERVAL 11 HOUR, 'Halvt skift'),
-- Infolarm produktionsmal
('produktionsmal', 'info', 'Dagligt produktionsmal ej uppnatt: 85 av 100 IBC',            85.00, 100.00, DATE_SUB(NOW(), INTERVAL 1 DAY) + INTERVAL 17 HOUR,  0, NULL, NULL, NULL),
('produktionsmal', 'info', 'Dagligt produktionsmal ej uppnatt: 72 av 100 IBC',            72.00, 100.00, DATE_SUB(NOW(), INTERVAL 3 DAY) + INTERVAL 17 HOUR,  1, 'Erik Lindqvist',  DATE_SUB(NOW(), INTERVAL 3 DAY) + INTERVAL 18 HOUR, 'Maskinstopp paverkade'),
('produktionsmal', 'info', 'Dagligt produktionsmal ej uppnatt: 90 av 100 IBC',            90.00, 100.00, DATE_SUB(NOW(), INTERVAL 10 DAY) + INTERVAL 17 HOUR, 1, 'Anna Svensson',   DATE_SUB(NOW(), INTERVAL 10 DAY) + INTERVAL 18 HOUR, 'Nara malet, ok dag'),
('produktionsmal', 'info', 'Dagligt produktionsmal ej uppnatt: 65 av 100 IBC',            65.00, 100.00, DATE_SUB(NOW(), INTERVAL 18 DAY) + INTERVAL 17 HOUR, 1, 'Peter Olsson',    DATE_SUB(NOW(), INTERVAL 18 DAY) + INTERVAL 18 HOUR, 'Stor stopp kl 10');

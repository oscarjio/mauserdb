-- Migration: 2026-03-03_rebotling_settings_weekday_goals
-- Lägger till veckodagsmål och skifttider för rebotling-admin
-- Kör: mysql mauserdb < 2026-03-03_rebotling_settings_weekday_goals.sql

-- Tabell för veckodagsmål (mån=1 ... sön=7, ISO-veckodag)
CREATE TABLE IF NOT EXISTS `rebotling_weekday_goals` (
    `id`              INT          NOT NULL AUTO_INCREMENT,
    `weekday`         TINYINT      NOT NULL COMMENT '1=Måndag, 2=Tisdag, ..., 7=Söndag (ISO)',
    `daily_goal`      INT          NOT NULL DEFAULT 1000 COMMENT 'Antal IBC detta veckodagsmål',
    `label`           VARCHAR(20)  NOT NULL DEFAULT '' COMMENT 'Ex: Måndag',
    `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_weekday` (`weekday`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Fyll i standardvärden för mån-fre (lägre mål måndag för uppstart)
INSERT IGNORE INTO `rebotling_weekday_goals` (`weekday`, `daily_goal`, `label`) VALUES
(1, 900,  'Måndag'),
(2, 1000, 'Tisdag'),
(3, 1000, 'Onsdag'),
(4, 1000, 'Torsdag'),
(5, 950,  'Fredag'),
(6, 0,    'Lördag'),
(7, 0,    'Söndag');

-- Skifttider-tabell
CREATE TABLE IF NOT EXISTS `rebotling_shift_times` (
    `id`          INT          NOT NULL AUTO_INCREMENT,
    `shift_name`  VARCHAR(50)  NOT NULL COMMENT 'förmiddag | eftermiddag | natt',
    `start_time`  TIME         NOT NULL DEFAULT '06:00:00',
    `end_time`    TIME         NOT NULL DEFAULT '14:00:00',
    `enabled`     TINYINT(1)   NOT NULL DEFAULT 1,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_shift_name` (`shift_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Fyll i standardvärden för tre skift
INSERT IGNORE INTO `rebotling_shift_times` (`shift_name`, `start_time`, `end_time`, `enabled`) VALUES
('förmiddag',   '06:00:00', '14:00:00', 1),
('eftermiddag', '14:00:00', '22:00:00', 1),
('natt',        '22:00:00', '06:00:00', 0);

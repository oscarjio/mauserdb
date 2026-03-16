-- ============================================================
-- INSTALL_ALL.sql
-- Samlade migreringar för mauserdb — kör denna enda fil
-- Genererad: 2026-03-05
-- Säkert att köra flera gånger (IF NOT EXISTS / INSERT IGNORE)
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- add_operators_table.sql
-- ============================================================
CREATE TABLE IF NOT EXISTS operators (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  number INT NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_operators_number (number)
);

-- ============================================================
-- 2026-03-03_bonus_amounts.sql
-- ============================================================
CREATE TABLE IF NOT EXISTS bonus_level_amounts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  level_name VARCHAR(50) NOT NULL UNIQUE,
  amount_sek INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by VARCHAR(100)
);

INSERT IGNORE INTO bonus_level_amounts (level_name, amount_sek) VALUES
  ('brons', 500),
  ('silver', 1000),
  ('guld', 2000),
  ('platina', 3500);

-- ============================================================
-- 2026-03-03_bonus_weekly_goal.sql
-- ============================================================
ALTER TABLE bonus_config
    ADD COLUMN IF NOT EXISTS weekly_bonus_goal DECIMAL(6,2) NOT NULL DEFAULT 80.00
        COMMENT 'Målpoäng per vecka för bonusberäkning';

INSERT IGNORE INTO bonus_config (id, weekly_bonus_goal)
VALUES (1, 80.00)
ON DUPLICATE KEY UPDATE weekly_bonus_goal = IF(weekly_bonus_goal = 0, 80.00, weekly_bonus_goal);

-- ============================================================
-- 2026-03-03_rebotling_settings_weekday_goals.sql
-- ============================================================
CREATE TABLE IF NOT EXISTS `rebotling_weekday_goals` (
    `id`        INT         NOT NULL AUTO_INCREMENT,
    `weekday`   TINYINT     NOT NULL COMMENT '1=Måndag ... 7=Söndag (ISO)',
    `daily_goal` INT        NOT NULL DEFAULT 1000,
    `label`     VARCHAR(20) NOT NULL DEFAULT '',
    `updated_at` TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_weekday` (`weekday`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `rebotling_weekday_goals` (`weekday`, `daily_goal`, `label`) VALUES
(1, 900,  'Måndag'),
(2, 1000, 'Tisdag'),
(3, 1000, 'Onsdag'),
(4, 1000, 'Torsdag'),
(5, 950,  'Fredag'),
(6, 0,    'Lördag'),
(7, 0,    'Söndag');

CREATE TABLE IF NOT EXISTS `rebotling_shift_times` (
    `id`         INT         NOT NULL AUTO_INCREMENT,
    `shift_name` VARCHAR(50) NOT NULL,
    `start_time` TIME        NOT NULL DEFAULT '06:00:00',
    `end_time`   TIME        NOT NULL DEFAULT '14:00:00',
    `enabled`    TINYINT(1)  NOT NULL DEFAULT 1,
    `updated_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_shift_name` (`shift_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `rebotling_shift_times` (`shift_name`, `start_time`, `end_time`, `enabled`) VALUES
('förmiddag',   '06:00:00', '14:00:00', 1),
('eftermiddag', '14:00:00', '22:00:00', 1),
('natt',        '22:00:00', '06:00:00', 0);

-- ============================================================
-- 2026-03-03_tvattlinje_settings_extend.sql
-- (tvattlinje_settings skapas i 2026-03-04_tvattlinje_settings.sql längre ned,
--  ALTER finns redan där nedan — hoppas över här för att undvika fel)
-- ============================================================

-- ============================================================
-- 2026-03-04_bonus_payouts.sql
-- ============================================================
CREATE TABLE IF NOT EXISTS bonus_payouts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  op_id INT NOT NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  amount_sek DECIMAL(10,2) NOT NULL,
  ibc_count INT DEFAULT 0,
  avg_ibc_per_h DECIMAL(6,2) DEFAULT 0,
  avg_quality_pct DECIMAL(5,2) DEFAULT 0,
  notes TEXT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_op_id (op_id),
  INDEX idx_period (period_start)
);

-- ============================================================
-- 2026-03-04_bonus_payouts_status.sql
-- ============================================================
ALTER TABLE bonus_payouts
  ADD COLUMN IF NOT EXISTS period_label VARCHAR(50) NOT NULL DEFAULT '' AFTER period_end,
  ADD COLUMN IF NOT EXISTS bonus_level ENUM('none','bronze','silver','gold','platinum') NOT NULL DEFAULT 'none' AFTER period_label,
  ADD COLUMN IF NOT EXISTS status ENUM('pending','approved','paid') NOT NULL DEFAULT 'pending' AFTER bonus_level,
  ADD COLUMN IF NOT EXISTS approved_by INT NULL AFTER status,
  ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL AFTER approved_by,
  ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL AFTER approved_at;

ALTER TABLE bonus_payouts
  ADD INDEX IF NOT EXISTS idx_status (status);

-- ============================================================
-- 2026-03-04_certifications.sql
-- ============================================================
CREATE TABLE IF NOT EXISTS operator_certifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    op_number INT NOT NULL,
    line VARCHAR(50) NOT NULL,
    certified_by INT DEFAULT NULL,
    certified_date DATE NOT NULL,
    expires_date DATE DEFAULT NULL,
    notes VARCHAR(500) DEFAULT NULL,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_op (op_number),
    INDEX idx_line (line),
    INDEX idx_expires (expires_date)
);

-- ============================================================
-- 2026-03-04_goal_history.sql
-- ============================================================
CREATE TABLE IF NOT EXISTS rebotling_goal_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  goal_type VARCHAR(50) NOT NULL DEFAULT 'dagmal',
  value INT NOT NULL,
  changed_by VARCHAR(100),
  changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_type_time (goal_type, changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 2026-03-04_kassationsorsak.sql
-- ============================================================
CREATE TABLE IF NOT EXISTS kassationsorsak_typer (
  id INT AUTO_INCREMENT PRIMARY KEY,
  namn VARCHAR(100) NOT NULL,
  beskrivning TEXT,
  aktiv TINYINT(1) DEFAULT 1,
  sortorder INT DEFAULT 0
);

INSERT IGNORE INTO kassationsorsak_typer (id, namn, sortorder) VALUES
  (1, 'Skada/deformation', 1),
  (2, 'Kontaminering', 2),
  (3, 'Läckage', 3),
  (4, 'Ventilfel', 4),
  (5, 'Korrosion', 5),
  (99, 'Övrigt', 99);

CREATE TABLE IF NOT EXISTS kassationsregistrering (
  id INT AUTO_INCREMENT PRIMARY KEY,
  datum DATE NOT NULL,
  skiftraknare INT,
  orsak_id INT NOT NULL,
  antal INT NOT NULL DEFAULT 1,
  kommentar VARCHAR(500),
  registrerad_av INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_datum (datum),
  FOREIGN KEY (orsak_id) REFERENCES kassationsorsak_typer(id)
);

ALTER TABLE rebotling_settings ADD COLUMN IF NOT EXISTS min_operators INT DEFAULT 2;

-- ============================================================
-- 2026-03-04_klassificeringslinje_settings.sql
-- ============================================================
CREATE TABLE IF NOT EXISTS klassificeringslinje_settings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  setting VARCHAR(100) NOT NULL UNIQUE,
  value VARCHAR(255),
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO klassificeringslinje_settings (setting, value) VALUES
  ('dagmal', '120'), ('takt_mal', '20'),
  ('skift_start', '06:00'), ('skift_slut', '22:00');

CREATE TABLE IF NOT EXISTS klassificeringslinje_weekday_goals (
  id INT PRIMARY KEY AUTO_INCREMENT,
  weekday TINYINT NOT NULL UNIQUE,
  mal INT NOT NULL DEFAULT 120,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO klassificeringslinje_weekday_goals (weekday, mal) VALUES
  (0,120),(1,120),(2,120),(3,120),(4,120),(5,80),(6,0);

-- ============================================================
-- 2026-03-04_live_ranking_config.sql
-- ============================================================
-- OBS: live_ranking_config sparas inte i rebotling_settings (enrads-config utan key/value-kolumner)
-- Denna inställning hanteras av frontend-konfiguration

-- ============================================================
-- 2026-03-04_maintenance_log.sql
-- ============================================================
CREATE TABLE IF NOT EXISTS maintenance_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  line ENUM('rebotling','tvattlinje','saglinje','klassificeringslinje','allmant') NOT NULL DEFAULT 'rebotling',
  maintenance_type ENUM('planerat','akut','inspektion','kalibrering','rengoring','ovrigt') NOT NULL DEFAULT 'ovrigt',
  title VARCHAR(150) NOT NULL,
  description TEXT NULL,
  start_time DATETIME NOT NULL,
  duration_minutes INT NULL,
  performed_by VARCHAR(100) NULL,
  cost_sek DECIMAL(10,2) NULL,
  status ENUM('planerat','pagaende','klart','avbokat') NOT NULL DEFAULT 'klart',
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_line_date (line, start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 2026-03-04_maintenance_equipment.sql
-- ============================================================
ALTER TABLE maintenance_log
  ADD COLUMN IF NOT EXISTS equipment VARCHAR(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS downtime_minutes INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS cost_sek DECIMAL(10,2) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS resolved TINYINT(1) DEFAULT 0;

CREATE TABLE IF NOT EXISTS maintenance_equipment (
  id INT AUTO_INCREMENT PRIMARY KEY,
  namn VARCHAR(100) NOT NULL,
  kategori ENUM('maskin','transport','verktyg','infrastruktur','övrigt') DEFAULT 'maskin',
  linje VARCHAR(50) DEFAULT 'rebotling',
  aktiv TINYINT(1) DEFAULT 1
);

INSERT IGNORE INTO maintenance_equipment (id, namn, kategori, linje) VALUES
(1,'Rebotling-robot','maskin','rebotling'),
(2,'Konveyor','transport','rebotling'),
(3,'Hydraulik-press','maskin','rebotling'),
(4,'Luftkompressor','infrastruktur','rebotling'),
(5,'PLC-styrenhet','maskin','rebotling'),
(6,'Övrig utrustning','övrigt','rebotling');

-- ============================================================
-- 2026-03-04_news_category.sql + news_priority_published.sql + news_pinned_archived.sql
-- ============================================================
CREATE TABLE IF NOT EXISTS news (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(255) NOT NULL DEFAULT '',
    body       TEXT NOT NULL,
    category   ENUM('produktion','bonus','system','info','viktig','rekord','hog_oee','certifiering','urgent') NOT NULL DEFAULT 'info',
    pinned     TINYINT(1) NOT NULL DEFAULT 0,
    published  TINYINT(1) NOT NULL DEFAULT 1,
    priority   TINYINT UNSIGNED NOT NULL DEFAULT 3,
    arkiveras_efter_dagar INT NULL DEFAULT NULL,
    arkiverad  TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_pinned (pinned),
    INDEX idx_priority (priority),
    INDEX idx_published (published),
    INDEX idx_arkiverad (arkiverad)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lägg till kolumner om tabellen redan finns men saknar dem
ALTER TABLE news
    ADD COLUMN IF NOT EXISTS published TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS priority TINYINT UNSIGNED NOT NULL DEFAULT 3,
    ADD COLUMN IF NOT EXISTS arkiveras_efter_dagar INT NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS arkiverad TINYINT(1) NOT NULL DEFAULT 0;

-- ============================================================
-- 2026-03-04_notification_email_setting.sql
-- ============================================================
ALTER TABLE rebotling_settings
    ADD COLUMN IF NOT EXISTS notification_emails TEXT NULL DEFAULT NULL;

-- ============================================================
-- 2026-03-04_operator_feedback.sql
-- ============================================================
CREATE TABLE IF NOT EXISTS operator_feedback (
  id INT AUTO_INCREMENT PRIMARY KEY,
  operator_id INT NOT NULL,
  skiftraknare INT NULL,
  datum DATE NOT NULL,
  stämning TINYINT NOT NULL COMMENT '1=Dålig 2=Ok 3=Bra 4=Utmärkt',
  kommentar VARCHAR(280) NULL,
  skapad_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_datum (datum),
  INDEX idx_operator (operator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 2026-03-04_production_events.sql
-- ============================================================
CREATE TABLE IF NOT EXISTS production_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_date DATE NOT NULL,
  title VARCHAR(100) NOT NULL,
  description VARCHAR(500) NULL,
  event_type ENUM('underhall','ny_operator','mal_andring','rekord','ovrigt') NOT NULL DEFAULT 'ovrigt',
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_date (event_date)
);

-- ============================================================
-- 2026-03-04_produktionsmal_undantag.sql
-- ============================================================
CREATE TABLE IF NOT EXISTS produktionsmal_undantag (
  datum DATE PRIMARY KEY,
  justerat_mal INT NOT NULL,
  orsak VARCHAR(255) NULL,
  skapad_av INT NULL,
  skapad_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  uppdaterad_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 2026-03-04_saglinje_settings.sql
-- ============================================================
CREATE TABLE IF NOT EXISTS saglinje_settings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  setting VARCHAR(100) NOT NULL UNIQUE,
  value VARCHAR(255),
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO saglinje_settings (setting, value) VALUES
  ('dagmal','50'),('takt_mal','10'),('skift_start','06:00'),('skift_slut','22:00');

CREATE TABLE IF NOT EXISTS saglinje_weekday_goals (
  id INT PRIMARY KEY AUTO_INCREMENT,
  weekday TINYINT NOT NULL UNIQUE,
  mal INT NOT NULL DEFAULT 50,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO saglinje_weekday_goals (weekday, mal) VALUES
  (0,50),(1,50),(2,50),(3,50),(4,50),(5,30),(6,0);

-- ============================================================
-- 2026-03-04_service_interval.sql + service_intervals.sql
-- ============================================================
-- OBS: service_interval-inställningar hanteras via service_intervals-tabellen nedan
-- (rebotling_settings är en enrads-config utan key/value-kolumner)

CREATE TABLE IF NOT EXISTS service_intervals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    maskin_namn VARCHAR(100) NOT NULL,
    intervall_ibc INT NOT NULL DEFAULT 5000,
    senaste_service_datum DATETIME NULL,
    senaste_service_ibc INT NOT NULL DEFAULT 0,
    skapad DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    uppdaterad DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO service_intervals (id, maskin_namn, intervall_ibc, senaste_service_datum, senaste_service_ibc)
VALUES (1, 'Rebotling-linje 1', 5000, NOW(), 0);

-- ============================================================
-- 2026-03-04_shift_handover.sql
-- ============================================================
CREATE TABLE IF NOT EXISTS shift_handover (
    id INT AUTO_INCREMENT PRIMARY KEY,
    datum DATE NOT NULL,
    skift_nr INT NOT NULL,
    note TEXT NOT NULL,
    priority ENUM('normal','important','urgent') DEFAULT 'normal',
    op_number INT DEFAULT NULL,
    op_name VARCHAR(100) DEFAULT NULL,
    created_by_user_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    acknowledged_by INT NULL,
    acknowledged_at DATETIME NULL,
    audience ENUM('alla','ansvarig','teknik') DEFAULT 'alla' NULL,
    INDEX idx_datum (datum),
    INDEX idx_skift (datum, skift_nr),
    INDEX idx_ack (acknowledged_at)
);

-- Lägg till kolumner om tabellen redan finns
ALTER TABLE shift_handover
  ADD COLUMN IF NOT EXISTS acknowledged_by INT NULL,
  ADD COLUMN IF NOT EXISTS acknowledged_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS audience ENUM('alla','ansvarig','teknik') DEFAULT 'alla' NULL;

-- ============================================================
-- 2026-03-04_shift_plan.sql
-- ============================================================
CREATE TABLE IF NOT EXISTS shift_plan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    datum DATE NOT NULL,
    skift_nr INT NOT NULL,
    op_number INT NOT NULL,
    note VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_shift_op (datum, skift_nr, op_number),
    INDEX idx_datum (datum),
    INDEX idx_op (op_number)
);

-- ============================================================
-- 2026-03-04_skiftrapport_kommentar.sql
-- ============================================================
CREATE TABLE IF NOT EXISTS rebotling_skift_kommentar (
  id INT PRIMARY KEY AUTO_INCREMENT,
  datum DATE NOT NULL,
  skift_nr INT NOT NULL,
  kommentar TEXT,
  skapad_av VARCHAR(100),
  skapad_tid TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_skift (datum, skift_nr)
);

-- ============================================================
-- 2026-03-04_stoppage_log.sql
-- OBS: Använder schema från StoppageController.php (code, planned/unplanned, color, sort_order)
-- ============================================================
CREATE TABLE IF NOT EXISTS stoppage_reasons (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `code`       VARCHAR(50)  NOT NULL,
    `name`       VARCHAR(100) NOT NULL,
    `category`   ENUM('planned','unplanned') NOT NULL DEFAULT 'unplanned',
    `color`      VARCHAR(7)   DEFAULT '#6b7280',
    `active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order` INT          NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stoppage_log (
    `id`               INT       NOT NULL AUTO_INCREMENT,
    `line`             VARCHAR(50) NOT NULL DEFAULT 'rebotling',
    `reason_id`        INT       NOT NULL,
    `start_time`       DATETIME  NOT NULL,
    `end_time`         DATETIME  DEFAULT NULL,
    `duration_minutes` INT       DEFAULT NULL,
    `comment`          TEXT,
    `user_id`          INT       DEFAULT NULL,
    `created_at`       DATETIME  DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME  DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_line_start` (`line`, `start_time`),
    INDEX `idx_reason` (`reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO stoppage_reasons (id, code, name, category, color, sort_order) VALUES
    (1, 'PLAN_MAINT', 'Planerat underhåll',  'planned',   '#3b82f6', 0),
    (2, 'BREAKDOWN',  'Haveri/Maskinfel',     'unplanned', '#ef4444', 1),
    (3, 'MATERIAL',   'Materialbrist',        'unplanned', '#f97316', 2),
    (4, 'CHANGEOVER', 'Produktbyte',          'planned',   '#8b5cf6', 3),
    (5, 'CLEANING',   'Rengöring',            'planned',   '#06b6d4', 4),
    (6, 'QUALITY',    'Kvalitetsproblem',     'unplanned', '#eab308', 5),
    (7, 'OPERATOR',   'Personalbrist',        'unplanned', '#ec4899', 6),
    (8, 'OTHER',      'Övrigt',               'unplanned', '#6b7280', 7);

-- ============================================================
-- 2026-03-04_tvattlinje_settings.sql + tvattlinje_alert_thresholds.sql
-- ============================================================
CREATE TABLE IF NOT EXISTS tvattlinje_settings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  setting VARCHAR(100) NOT NULL UNIQUE,
  value VARCHAR(255),
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO tvattlinje_settings (setting, value) VALUES
  ('dagmal','80'),('takt_mal','15'),('skift_start','06:00'),('skift_slut','22:00'),
  ('alert_thresholds','{"kvalitet_warn":90,"plc_max_min":15,"dagmal_warn_pct":80}');

ALTER TABLE tvattlinje_settings
  ADD COLUMN IF NOT EXISTS timtakt INT NOT NULL DEFAULT 20,
  ADD COLUMN IF NOT EXISTS skiftlangd DECIMAL(4,1) NOT NULL DEFAULT 8.0;

CREATE TABLE IF NOT EXISTS tvattlinje_weekday_goals (
  id INT PRIMARY KEY AUTO_INCREMENT,
  weekday TINYINT NOT NULL UNIQUE,
  mal INT NOT NULL DEFAULT 80,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO tvattlinje_weekday_goals (weekday, mal) VALUES
  (0,80),(1,80),(2,80),(3,80),(4,80),(5,60),(6,0);

-- ============================================================
-- 2026-03-05_password_bcrypt.sql
-- ============================================================
ALTER TABLE `users` MODIFY COLUMN `password` VARCHAR(255) NOT NULL;

-- ============================================================
-- 2026-03-10_annotations.sql
-- ============================================================
CREATE TABLE IF NOT EXISTS `rebotling_annotations` (
    `id`          INT          NOT NULL AUTO_INCREMENT,
    `datum`       DATE         NOT NULL,
    `typ`         ENUM('driftstopp','helgdag','handelse','ovrigt') NOT NULL DEFAULT 'ovrigt',
    `titel`       VARCHAR(120) NOT NULL,
    `beskrivning` TEXT         NULL DEFAULT NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_datum` (`datum`),
    INDEX `idx_typ`   (`typ`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 2026-03-11_alerts.sql
-- ============================================================
CREATE TABLE IF NOT EXISTS `alerts` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type`             ENUM('oee_low','stop_long','scrap_high') NOT NULL,
    `message`          VARCHAR(500) NOT NULL,
    `value`            DECIMAL(10,2) DEFAULT NULL,
    `threshold`        DECIMAL(10,2) DEFAULT NULL,
    `severity`         ENUM('warning','critical') NOT NULL DEFAULT 'warning',
    `acknowledged`     TINYINT(1) NOT NULL DEFAULT 0,
    `acknowledged_by`  INT UNSIGNED DEFAULT NULL,
    `acknowledged_at`  DATETIME DEFAULT NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_acknowledged` (`acknowledged`),
    INDEX `idx_type` (`type`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `alert_settings` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type`            ENUM('oee_low','stop_long','scrap_high') NOT NULL,
    `threshold_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `enabled`         TINYINT(1) NOT NULL DEFAULT 1,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by`      INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `alert_settings` (`type`, `threshold_value`, `enabled`) VALUES
    ('oee_low',    60.00, 1),
    ('stop_long',  30.00, 1),
    ('scrap_high', 10.00, 1);

-- ============================================================
-- 2026-03-11_production-goals.sql
-- ============================================================
CREATE TABLE IF NOT EXISTS `rebotling_production_goals` (
    `id`           INT          NOT NULL AUTO_INCREMENT,
    `period_type`  ENUM('daily','weekly') NOT NULL DEFAULT 'daily',
    `target_count` INT          NOT NULL DEFAULT 0,
    `created_by`   INT          NULL DEFAULT NULL,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_period_type` (`period_type`),
    KEY `idx_created_at`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `rebotling_production_goals` (`id`, `period_type`, `target_count`)
VALUES (1, 'daily', 200), (2, 'weekly', 1000);

-- ============================================================
-- 2026-03-11_dashboard_layouts.sql
-- ============================================================
CREATE TABLE IF NOT EXISTS dashboard_layouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    layout_json TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 2026-03-11_skiftoverlamning.sql
-- ============================================================
CREATE TABLE IF NOT EXISTS skiftoverlamning_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    skiftraknare INT NOT NULL,
    linje VARCHAR(50) NOT NULL DEFAULT 'rebotling',
    note_text TEXT NOT NULL,
    user_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_skift_linje (skiftraknare, linje),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2026-03-11_stopporsak_registrering.sql
-- ============================================================
CREATE TABLE IF NOT EXISTS `stopporsak_kategorier` (
    `id` int NOT NULL AUTO_INCREMENT,
    `namn` varchar(100) NOT NULL,
    `ikon` varchar(10) NOT NULL,
    `sort_order` int NOT NULL DEFAULT 0,
    `active` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stopporsak_registreringar` (
    `id` int NOT NULL AUTO_INCREMENT,
    `kategori_id` int NOT NULL,
    `linje` varchar(50) NOT NULL DEFAULT 'rebotling',
    `kommentar` text,
    `user_id` int DEFAULT NULL,
    `start_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `end_time` datetime DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_kategori` (`kategori_id`),
    KEY `idx_linje` (`linje`),
    KEY `idx_user` (`user_id`),
    KEY `idx_start` (`start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed standardkategorier (bara om tabellen är tom)
INSERT IGNORE INTO `stopporsak_kategorier` (`id`, `namn`, `ikon`, `sort_order`) VALUES
(1, 'Underhåll', '🔧', 1),
(2, 'Materialbrist', '📦', 2),
(3, 'Kvalitetskontroll', '🔍', 3),
(4, 'Rast', '☕', 4),
(5, 'Rengöring', '🧹', 5),
(6, 'Maskinhaveri', '⚠️', 6),
(7, 'Verktygsbyte', '🔄', 7),
(8, 'Övrigt', '📝', 8);

-- ============================================================
-- 2026-03-11_underhallslogg.sql
-- ============================================================
CREATE TABLE IF NOT EXISTS `underhallslogg` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `kategori` VARCHAR(50) NOT NULL,
    `typ` ENUM('planerat','oplanerat') NOT NULL,
    `varaktighet_min` INT NOT NULL,
    `kommentar` TEXT,
    `maskin` VARCHAR(100) NOT NULL DEFAULT 'Rebotling',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_kategori` (`kategori`),
    KEY `idx_typ` (`typ`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `underhall_kategorier` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `namn` VARCHAR(50) NOT NULL,
    `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `underhall_kategorier` (`id`, `namn`, `aktiv`) VALUES
    (1, 'Mekaniskt', 1),
    (2, 'Elektriskt', 1),
    (3, 'Hydraulik', 1),
    (4, 'Pneumatik', 1),
    (5, 'Rengöring', 1),
    (6, 'Kalibrering', 1),
    (7, 'Annat', 1);

-- ============================================================
-- 2026-03-11_feedback_analys.sql
-- ============================================================
ALTER TABLE operator_feedback
    ADD INDEX IF NOT EXISTS idx_datum_operator (datum, operator_id),
    ADD INDEX IF NOT EXISTS idx_skapad_at (skapad_at);

-- ============================================================
-- 2026-03-11_malhistorik.sql
-- ============================================================
ALTER TABLE rebotling_goal_history
    ADD INDEX IF NOT EXISTS idx_changed_at (changed_at),
    ADD INDEX IF NOT EXISTS idx_changed_by (changed_by);

-- ============================================================
-- 2026-03-11_skiftrapport_export.sql (index-optimering)
-- ============================================================
-- OBS: Dessa index läggs till med IF NOT EXISTS / felhantering.
-- Om kolumnen inte finns i er version av rebotling_ibc, ignorera felet.

-- ============================================================
-- 2026-03-11_cykeltid_heatmap.sql (index-optimering)
-- ============================================================
-- Index för heatmap-frågor (op1/op2/op3 + datum)
-- Kräver att rebotling_ibc har kolumnerna op1, op2, op3, datum

-- ============================================================
-- 2026-03-11_ranking_historik.sql (index-optimering)
-- ============================================================
-- Index för ranking per operatör + ok-status
-- Kräver att rebotling_ibc har kolumnerna op1, op2, op3, datum, ok

-- ============================================================
-- OBS: Index-migrationer för cykeltid_heatmap, ranking_historik,
-- oee_benchmark, produktionskalender, daglig_sammanfattning,
-- skiftjamforelse och skiftrapport_export lägger till
-- prestanda-index på PLC-tabeller (rebotling_ibc, rebotling_onoff).
-- Kör dessa enskilda filer manuellt om ni vill ha index-optimeringen:
--   2026-03-11_cykeltid_heatmap.sql
--   2026-03-11_ranking_historik.sql
--   2026-03-11_oee_benchmark.sql
--   2026-03-11_produktionskalender.sql
--   2026-03-11_daglig_sammanfattning.sql
--   2026-03-11_skiftjamforelse.sql
--   2026-03-11_skiftrapport_export.sql
-- ============================================================

-- ============================================================
-- 2026-03-11_underhallsprognos.sql — prediktivt underhåll
-- ============================================================

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

CREATE TABLE IF NOT EXISTS `underhall_scheman` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `komponent_id` INT NOT NULL,
    `intervall_dagar` INT NOT NULL DEFAULT 30,
    `senaste_underhall` DATETIME NULL,
    `nasta_planerat` DATETIME NULL,
    `ansvarig` VARCHAR(100) NULL,
    `noteringar` TEXT NULL,
    `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
    `skapad` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `uppdaterad` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_komponent_id` (`komponent_id`),
    KEY `idx_senaste_underhall` (`senaste_underhall`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- ============================================================
-- Notering: 2026-03-11_kvalitetstrend.sql innehåller enbart
-- prestanda-index på rebotling_ibc + operators.
-- Kör den enskilda filen manuellt om ni vill ha index-optimeringen:
--   2026-03-11_kvalitetstrend.sql
-- ============================================================


-- ============================================================
-- 2026-03-11_produktionstakt_target
-- ============================================================
-- Måltalstabell för produktionstakt (IBC/h)
-- Används av ProduktionsTaktController för att jämföra aktuell takt mot mål.
CREATE TABLE IF NOT EXISTS produktionstakt_target (
    id INT AUTO_INCREMENT PRIMARY KEY,
    target_ibc_per_hour DECIMAL(6,1) NOT NULL,
    set_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sätt ett default-måltal
INSERT IGNORE INTO produktionstakt_target (target_ibc_per_hour, set_by)
VALUES (12.0, NULL);


-- ============================================================
-- 2026-03-12_avvikelselarm
-- ============================================================
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
INSERT IGNORE INTO `avvikelselarm` (`typ`, `allvarlighetsgrad`, `meddelande`, `varde_aktuellt`, `varde_grans`, `tidsstampel`, `kvitterad`, `kvitterad_av`, `kvitterad_datum`, `kvitterings_kommentar`) VALUES
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


-- ============================================================
-- 2026-03-12_batch_sparning
-- ============================================================
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

INSERT IGNORE INTO `batch_order` (`id`, `batch_nummer`, `planerat_antal`, `kommentar`, `status`, `skapad_av`, `skapad_datum`, `avslutad_datum`) VALUES
(1, 'BATCH-2026-0301', 10, 'Standardtvätt Mauser 1000L', 'klar', 1, '2026-03-01 07:00:00', '2026-03-01 15:30:00'),
(2, 'BATCH-2026-0305', 15, 'Kemikalietvätt specialorder', 'pagaende', 1, '2026-03-05 06:30:00', NULL),
(3, 'BATCH-2026-0310', 8, 'Expresstvätt prioriterad', 'pausad', 1, '2026-03-10 08:00:00', NULL);

-- Seed IBC:er för batch 1 (klar — 10 av 10, 1 kasserad)
INSERT IGNORE INTO `batch_ibc` (`batch_id`, `ibc_nummer`, `operator_id`, `startad`, `klar`, `kasserad`, `cykeltid_sekunder`) VALUES
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
INSERT IGNORE INTO `batch_ibc` (`batch_id`, `ibc_nummer`, `operator_id`, `startad`, `klar`, `kasserad`, `cykeltid_sekunder`) VALUES
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
INSERT IGNORE INTO `batch_ibc` (`batch_id`, `ibc_nummer`, `operator_id`, `startad`, `klar`, `kasserad`, `cykeltid_sekunder`) VALUES
(3, 'IBC-30001', 2, '2026-03-10 08:05:00', '2026-03-10 08:50:00', 0, 2700),
(3, 'IBC-30002', 2, '2026-03-10 08:55:00', '2026-03-10 09:40:00', 0, 2700),
(3, 'IBC-30003', 1, '2026-03-10 09:45:00', '2026-03-10 10:30:00', 0, 2700);


-- ============================================================
-- 2026-03-12_favoriter
-- ============================================================
-- Migration: user_favoriter — snabblänkar/favoriter per användare
-- Datum: 2026-03-12

CREATE TABLE IF NOT EXISTS user_favoriter (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    route VARCHAR(255) NOT NULL,
    label VARCHAR(100) NOT NULL,
    icon VARCHAR(50) NOT NULL DEFAULT 'fas fa-star',
    color VARCHAR(20) NOT NULL DEFAULT '#4299e1',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_favoriter_user (user_id),
    UNIQUE KEY uq_user_route (user_id, route)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 2026-03-12_kassationsorsak
-- ============================================================
-- Kassationsorsak-statistik: Utvidgar befintliga tabeller med skift-typ
-- Befintliga tabeller: kassationsorsak_typer, kassationsregistrering
-- Skapad: 2026-03-12

-- Lägg till skift_typ kolumn i kassationsregistrering om den saknas
ALTER TABLE kassationsregistrering
  ADD COLUMN IF NOT EXISTS skift_typ ENUM('dag','kväll','natt') DEFAULT NULL AFTER skiftraknare;

-- Uppdatera befintliga rader: härleda skift_typ från skifträknare (1=dag, 2=kväll, 3=natt)
UPDATE kassationsregistrering
SET skift_typ = CASE
    WHEN skiftraknare = 1 THEN 'dag'
    WHEN skiftraknare = 2 THEN 'kväll'
    WHEN skiftraknare = 3 THEN 'natt'
    ELSE NULL
END
WHERE skift_typ IS NULL AND skiftraknare IS NOT NULL;

-- Lägg till index för snabbare statistikfrågor
ALTER TABLE kassationsregistrering
  ADD INDEX IF NOT EXISTS idx_orsak_datum (orsak_id, datum),
  ADD INDEX IF NOT EXISTS idx_registrerad_av (registrerad_av),
  ADD INDEX IF NOT EXISTS idx_skift_typ (skift_typ);


-- ============================================================
-- 2026-03-12_kvalitetscertifikat
-- ============================================================
-- Migration: Kvalitetscertifikat per batch
-- Datum: 2026-03-12

CREATE TABLE IF NOT EXISTS kvalitetscertifikat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NULL,
    batch_nummer VARCHAR(50) NOT NULL,
    datum DATE NOT NULL,
    operator_id INT NULL,
    operator_namn VARCHAR(100) NOT NULL DEFAULT '',
    antal_ibc INT NOT NULL DEFAULT 0,
    kassation_procent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    cykeltid_snitt DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    kvalitetspoang DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    status ENUM('godkand','underkand','ej_bedomd') NOT NULL DEFAULT 'ej_bedomd',
    kommentar TEXT NULL,
    bedomd_av VARCHAR(100) NULL,
    bedomd_datum DATETIME NULL,
    skapad_datum TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_kc_batch (batch_nummer),
    INDEX idx_kc_datum (datum),
    INDEX idx_kc_status (status),
    INDEX idx_kc_operator (operator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kvalitetskriterier (
    id INT AUTO_INCREMENT PRIMARY KEY,
    namn VARCHAR(100) NOT NULL,
    beskrivning TEXT NULL,
    min_varde DECIMAL(10,2) NULL,
    max_varde DECIMAL(10,2) NULL,
    vikt DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    aktiv BOOLEAN NOT NULL DEFAULT TRUE,
    skapad_datum TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: kvalitetskriterier
INSERT IGNORE INTO kvalitetskriterier (namn, beskrivning, min_varde, max_varde, vikt, aktiv) VALUES
('Kassation',       'Kassationsprocent under grans',          NULL,  3.00, 30.00, TRUE),
('Cykeltid',        'Genomsnittlig cykeltid under grans',     NULL, 45.00, 25.00, TRUE),
('Antal IBC',       'Minsta antal IBC i batchen',            50.00,  NULL, 20.00, TRUE),
('Jaemnhet',        'Jamnhet i cykeltider (liten spridning)', NULL,  5.00, 15.00, TRUE),
('Operatoerserfarenhet', 'Operatoren har certifiering',       1.00,  NULL, 10.00, TRUE);

-- Seed: exempelcertifikat (25 st)
INSERT IGNORE INTO kvalitetscertifikat (batch_nummer, datum, operator_id, operator_namn, antal_ibc, kassation_procent, cykeltid_snitt, kvalitetspoang, status, kommentar, bedomd_av, bedomd_datum) VALUES
('B-2026-0301',  '2026-02-10', 1, 'Erik Lindberg',    185, 1.20, 38.5, 94.5, 'godkand',   'Utmarkt batch, alla kriterier uppfyllda.',        'Admin', '2026-02-10 16:00:00'),
('B-2026-0302',  '2026-02-11', 2, 'Anna Svensson',    192, 0.80, 36.2, 97.2, 'godkand',   'Mycket bra kvalitet och kort cykeltid.',           'Admin', '2026-02-11 16:30:00'),
('B-2026-0303',  '2026-02-12', 3, 'Karl Johansson',   178, 2.90, 42.1, 81.3, 'godkand',   'Godkand men nara kassationsgrans.',                'Admin', '2026-02-12 15:45:00'),
('B-2026-0304',  '2026-02-13', 1, 'Erik Lindberg',    165, 4.50, 47.8, 52.1, 'underkand', 'For hog kassation och cykeltid over grans.',       'Admin', '2026-02-13 16:15:00'),
('B-2026-0305',  '2026-02-14', 4, 'Lisa Andersson',   201, 1.50, 39.7, 91.8, 'godkand',   'Bra batch med hog volym.',                         'Admin', '2026-02-14 16:00:00'),
('B-2026-0306',  '2026-02-17', 2, 'Anna Svensson',    188, 1.10, 37.4, 95.6, 'godkand',   'Stabil kvalitet som vanligt.',                     'Admin', '2026-02-17 16:30:00'),
('B-2026-0307',  '2026-02-18', 5, 'Johan Nilsson',    172, 3.20, 44.5, 74.8, 'underkand', 'Kassation over grans, behover aterutbildning.',    'Admin', '2026-02-18 15:00:00'),
('B-2026-0308',  '2026-02-19', 3, 'Karl Johansson',   195, 1.80, 40.3, 88.4, 'godkand',   'Godkand, cykeltid lite hog men acceptabel.',       'Admin', '2026-02-19 16:00:00'),
('B-2026-0309',  '2026-02-20', 1, 'Erik Lindberg',    210, 0.90, 35.8, 96.7, 'godkand',   'Toppresultat, basta batchen denna manad.',         'Admin', '2026-02-20 16:00:00'),
('B-2026-0310',  '2026-02-21', 4, 'Lisa Andersson',   183, 2.10, 41.2, 86.5, 'godkand',   'Godkand med marginal.',                            'Admin', '2026-02-21 16:00:00'),
('B-2026-0311',  '2026-02-24', 2, 'Anna Svensson',    197, 0.70, 35.1, 98.1, 'godkand',   'Utmarkt resultat pa alla parametrar.',             'Admin', '2026-02-24 16:00:00'),
('B-2026-0312',  '2026-02-25', 5, 'Johan Nilsson',    168, 2.50, 43.0, 79.2, 'godkand',   'Precis godkand, kassation nara grans.',            'Admin', '2026-02-25 16:00:00'),
('B-2026-0313',  '2026-02-26', 3, 'Karl Johansson',   190, 1.60, 39.5, 90.3, 'godkand',   'Bra kvalitet.',                                    'Admin', '2026-02-26 16:30:00'),
('B-2026-0314',  '2026-02-27', 1, 'Erik Lindberg',    175, 3.80, 46.2, 61.4, 'underkand', 'Kassation och cykeltid over grans.',               'Admin', '2026-02-27 16:00:00'),
('B-2026-0315',  '2026-02-28', 4, 'Lisa Andersson',   204, 1.30, 38.1, 93.7, 'godkand',   'Hog volym och bra kvalitet.',                      'Admin', '2026-02-28 16:00:00'),
('B-2026-0316',  '2026-03-03', 2, 'Anna Svensson',    193, 0.60, 34.8, 98.5, 'godkand',   'Basta operatoren denna period.',                   'Admin', '2026-03-03 16:00:00'),
('B-2026-0317',  '2026-03-04', 5, 'Johan Nilsson',    180, 2.80, 43.7, 77.1, 'godkand',   'Godkand men behover forbattring pa cykeltid.',     'Admin', '2026-03-04 16:00:00'),
('B-2026-0318',  '2026-03-05', 3, 'Karl Johansson',   187, 1.40, 38.9, 92.6, 'godkand',   'Stabil prestation.',                               'Admin', '2026-03-05 16:00:00'),
('B-2026-0319',  '2026-03-06', 1, 'Erik Lindberg',    199, 1.00, 37.0, 95.9, 'godkand',   'Mycket bra resultat.',                             'Admin', '2026-03-06 16:00:00'),
('B-2026-0320',  '2026-03-07', 4, 'Lisa Andersson',   191, 1.70, 40.5, 89.1, 'godkand',   'Godkand, allt inom granserna.',                    'Admin', '2026-03-07 16:00:00'),
('B-2026-0321',  '2026-03-10', 2, 'Anna Svensson',    205, 0.50, 34.2, 99.0, 'godkand',   'Perfekt batch.',                                   'Admin', '2026-03-10 16:00:00'),
('B-2026-0322',  '2026-03-10', 5, 'Johan Nilsson',    170, 3.50, 45.8, 67.3, 'underkand', 'Kassation och cykeltid over grans.',               'Admin', '2026-03-10 16:30:00'),
('B-2026-0323',  '2026-03-11', 3, 'Karl Johansson',   182, 2.00, 41.0, 85.8, 'godkand',   'OK batch.',                                        'Admin', '2026-03-11 16:00:00'),
('B-2026-0324',  '2026-03-11', 1, 'Erik Lindberg',    196, 1.10, 36.5, 95.2, 'godkand',   'Bra resultat som forväntat.',                      'Admin', '2026-03-11 16:30:00'),
('B-2026-0325',  '2026-03-12', 4, 'Lisa Andersson',   188, 1.90, 40.8, 87.9, 'ej_bedomd', NULL,                                               NULL,    NULL);


-- ============================================================
-- 2026-03-12_leveransplanering
-- ============================================================
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
INSERT IGNORE INTO `kundordrar` (`kundnamn`, `antal_ibc`, `bestallningsdatum`, `onskat_leveransdatum`, `beraknat_leveransdatum`, `status`, `prioritet`, `notering`) VALUES
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


-- ============================================================
-- 2026-03-12_maskinunderhall (moved before maskin_oee — dependency)
-- ============================================================
-- Migration: 2026-03-12_maskinunderhall.sql
-- Maskinunderhåll — register och servicelogg för IBC-rebotling-linje

CREATE TABLE IF NOT EXISTS maskin_register (
  id INT AUTO_INCREMENT PRIMARY KEY,
  namn VARCHAR(100) NOT NULL,
  beskrivning TEXT,
  service_intervall_dagar INT NOT NULL DEFAULT 90,
  aktiv TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS maskin_service_logg (
  id INT AUTO_INCREMENT PRIMARY KEY,
  maskin_id INT NOT NULL,
  service_datum DATE NOT NULL,
  service_typ ENUM('planerat', 'akut', 'inspektion') NOT NULL DEFAULT 'planerat',
  beskrivning TEXT,
  utfort_av VARCHAR(100),
  nasta_planerad_datum DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (maskin_id) REFERENCES maskin_register(id),
  INDEX idx_maskin_id (maskin_id),
  INDEX idx_service_datum (service_datum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed-data: typiska maskiner i en IBC-rebotling-linje
INSERT IGNORE INTO maskin_register (namn, beskrivning, service_intervall_dagar) VALUES
('Tvättmaskin', 'Huvudtvätt för IBC-tankar', 90),
('Torkugn', 'Torkstation efter tvätt', 180),
('Inspektionsstation', 'Visuell kontroll och test', 60),
('Transportband', 'Huvudtransportör rebotling-linje', 30),
('Etiketterare', 'Automatisk märkningsmaskin', 45),
('Ventiltestare', 'Testning av IBC-ventiler', 60);

-- ============================================================
-- 2026-03-12_maskin_oee
-- ============================================================
-- Migration: maskin_oee — OEE-konfiguration per maskin
-- Datum: 2026-03-12
-- Beskrivning: Tabell for OEE-mal och ideal cykeltider per maskin

CREATE TABLE IF NOT EXISTS `maskin_oee_config` (
    `id`                INT AUTO_INCREMENT PRIMARY KEY,
    `maskin_id`         INT NOT NULL,
    `planerad_tid_min`  DECIMAL(8,2) NOT NULL DEFAULT 480 COMMENT 'Planerad drifttid per dag (min), standard 8h',
    `ideal_cykeltid_sek` DECIMAL(8,2) NOT NULL DEFAULT 120 COMMENT 'Ideal cykeltid per IBC (sekunder)',
    `oee_mal_pct`       DECIMAL(5,2) NOT NULL DEFAULT 85.00 COMMENT 'OEE-mal i procent',
    `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by`        INT DEFAULT NULL,
    UNIQUE KEY `uk_maskin_id` (`maskin_id`),
    KEY `idx_maskin_id` (`maskin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed-data: OEE-konfiguration for de 6 befintliga maskinerna
INSERT IGNORE INTO `maskin_oee_config` (`maskin_id`, `planerad_tid_min`, `ideal_cykeltid_sek`, `oee_mal_pct`) VALUES
(1, 480, 120, 85.00),
(2, 480, 90, 85.00),
(3, 480, 60, 85.00),
(4, 480, 30, 85.00),
(5, 480, 45, 85.00),
(6, 480, 60, 85.00);

-- Tabell for daglig OEE-snapshot per maskin (for trend-diagram)
CREATE TABLE IF NOT EXISTS `maskin_oee_daglig` (
    `id`                INT AUTO_INCREMENT PRIMARY KEY,
    `maskin_id`         INT NOT NULL,
    `datum`             DATE NOT NULL,
    `planerad_tid_min`  DECIMAL(8,2) NOT NULL DEFAULT 0,
    `drifttid_min`      DECIMAL(8,2) NOT NULL DEFAULT 0,
    `stopptid_min`      DECIMAL(8,2) NOT NULL DEFAULT 0,
    `total_output`      INT NOT NULL DEFAULT 0,
    `ok_output`         INT NOT NULL DEFAULT 0,
    `kassation`         INT NOT NULL DEFAULT 0,
    `ideal_cykeltid_sek` DECIMAL(8,2) NOT NULL DEFAULT 120,
    `tillganglighet_pct` DECIMAL(5,2) DEFAULT NULL,
    `prestanda_pct`     DECIMAL(5,2) DEFAULT NULL,
    `kvalitet_pct`      DECIMAL(5,2) DEFAULT NULL,
    `oee_pct`           DECIMAL(5,2) DEFAULT NULL,
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_maskin_datum` (`maskin_id`, `datum`),
    KEY `idx_datum` (`datum`),
    KEY `idx_maskin_id` (`maskin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed demo-data for senaste 30 dagarna
-- Vi insertar via en procedur-liknande approach med variabler
INSERT IGNORE INTO `maskin_oee_daglig`
    (`maskin_id`, `datum`, `planerad_tid_min`, `drifttid_min`, `stopptid_min`,
     `total_output`, `ok_output`, `kassation`, `ideal_cykeltid_sek`,
     `tillganglighet_pct`, `prestanda_pct`, `kvalitet_pct`, `oee_pct`)
SELECT
    m.id AS maskin_id,
    DATE_SUB(CURDATE(), INTERVAL seq.n DAY) AS datum,
    480 AS planerad_tid_min,
    ROUND(480 - (RAND() * 60 + 10), 2) AS drifttid_min,
    ROUND(RAND() * 60 + 10, 2) AS stopptid_min,
    FLOOR(RAND() * 80 + 120) AS total_output,
    FLOOR(RAND() * 75 + 115) AS ok_output,
    FLOOR(RAND() * 8 + 1) AS kassation,
    oc.ideal_cykeltid_sek,
    NULL, NULL, NULL, NULL
FROM maskin_register m
JOIN maskin_oee_config oc ON oc.maskin_id = m.id
CROSS JOIN (
    SELECT 0 AS n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
    UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9
    UNION SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14
    UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19
    UNION SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24
    UNION SELECT 25 UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29
) seq
WHERE m.aktiv = 1;

-- Uppdatera beraknade OEE-varden
UPDATE `maskin_oee_daglig`
SET
    tillganglighet_pct = ROUND((drifttid_min / planerad_tid_min) * 100, 2),
    prestanda_pct = ROUND(LEAST((total_output * ideal_cykeltid_sek / 60) / drifttid_min * 100, 100), 2),
    kvalitet_pct = CASE WHEN total_output > 0 THEN ROUND((ok_output / total_output) * 100, 2) ELSE 0 END,
    oee_pct = ROUND(
        (drifttid_min / planerad_tid_min) *
        LEAST((total_output * ideal_cykeltid_sek / 60) / drifttid_min, 1) *
        CASE WHEN total_output > 0 THEN (ok_output / total_output) ELSE 0 END * 100
    , 2)
WHERE tillganglighet_pct IS NULL;


-- ============================================================
-- 2026-03-12_operatorsbonus
-- ============================================================
-- Operatörsbonus — konfiguration + utbetalningslogg
-- 2026-03-12

CREATE TABLE IF NOT EXISTS `bonus_konfiguration` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `faktor`       ENUM('ibc_per_timme', 'kvalitet', 'narvaro', 'team_bonus') NOT NULL,
    `vikt`         DECIMAL(5,2) NOT NULL COMMENT 'Procentandel av total bonus (summa=100)',
    `mal_varde`    DECIMAL(10,2) NOT NULL COMMENT 'Malvarde for 100% bonus',
    `max_bonus_kr` DECIMAL(10,2) NOT NULL COMMENT 'Max bonus i kronor for denna faktor',
    `beskrivning`  TEXT DEFAULT NULL,
    `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by`   INT DEFAULT NULL,
    UNIQUE KEY `uq_faktor` (`faktor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed-data
INSERT IGNORE INTO `bonus_konfiguration` (`faktor`, `vikt`, `mal_varde`, `max_bonus_kr`, `beskrivning`) VALUES
    ('ibc_per_timme', 40.00, 12.00, 500.00, 'IBC per timme — genomsnittlig produktionstakt'),
    ('kvalitet',      30.00, 98.00, 400.00, 'Kvalitet — andel godkanda IBC i procent'),
    ('narvaro',       20.00, 100.00, 200.00, 'Narvaro — narvaro i procent'),
    ('team_bonus',    10.00, 95.00, 100.00, 'Team-bonus — andel uppnadda linjemal i procent')
ON DUPLICATE KEY UPDATE
    vikt         = VALUES(vikt),
    mal_varde    = VALUES(mal_varde),
    max_bonus_kr = VALUES(max_bonus_kr),
    beskrivning  = VALUES(beskrivning);

CREATE TABLE IF NOT EXISTS `bonus_utbetalning` (
    `id`                  INT AUTO_INCREMENT PRIMARY KEY,
    `operator_id`         INT NOT NULL,
    `operator_namn`       VARCHAR(100) NOT NULL,
    `period_start`        DATE NOT NULL,
    `period_slut`         DATE NOT NULL,
    `ibc_per_timme_snitt` DECIMAL(10,2) DEFAULT 0,
    `kvalitet_procent`    DECIMAL(5,2) DEFAULT 0,
    `narvaro_procent`     DECIMAL(5,2) DEFAULT 0,
    `team_mal_procent`    DECIMAL(5,2) DEFAULT 0,
    `bonus_ibc`           DECIMAL(10,2) DEFAULT 0,
    `bonus_kvalitet`      DECIMAL(10,2) DEFAULT 0,
    `bonus_narvaro`       DECIMAL(10,2) DEFAULT 0,
    `bonus_team`          DECIMAL(10,2) DEFAULT 0,
    `total_bonus`         DECIMAL(10,2) DEFAULT 0,
    `skapad_at`           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_operator_id`   (`operator_id`),
    KEY `idx_period`        (`period_start`, `period_slut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 2026-03-12_produktionskostnad
-- ============================================================
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
INSERT IGNORE INTO `produktionskostnad_config` (`faktor`, `varde`, `enhet`) VALUES
('energi',    150.00, 'kr/h'),
('bemanning', 350.00, 'kr/h'),
('material',   50.00, 'kr/IBC'),
('kassation', 200.00, 'kr/IBC'),
('overhead',  100.00, 'kr/h')
ON DUPLICATE KEY UPDATE `varde` = VALUES(`varde`), `enhet` = VALUES(`enhet`);


-- ============================================================
-- 2026-03-12_produktions_sla
-- ============================================================
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
INSERT IGNORE INTO `produktions_mal` (`mal_typ`, `target_ibc`, `target_kassation_pct`, `giltig_from`, `giltig_tom`, `created_by`, `created_at`) VALUES
('dagligt',   80,  5.00, '2026-01-01', NULL, 1, '2026-01-01 00:00:00'),
('veckovist', 400, 4.00, '2026-01-01', NULL, 1, '2026-01-01 00:00:00');


-- ============================================================
-- 2026-03-12_skiftoverlamning
-- ============================================================
-- Migration: 2026-03-12_skiftoverlamning.sql
-- Skiftöverlämningslogg — strukturerat överlämningsformulär med auto-KPI:er
-- Utökar den enklare skiftoverlamning_notes-tabellen med ett komplett logg-system.

CREATE TABLE IF NOT EXISTS skiftoverlamning_logg (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operator_id INT NOT NULL COMMENT 'Avgående operatör (user_id)',
    operator_namn VARCHAR(100) DEFAULT NULL COMMENT 'Cachat operatörsnamn vid skapande',
    skift_typ ENUM('dag','kvall','natt') NOT NULL DEFAULT 'dag' COMMENT 'dag=06-14, kvall=14-22, natt=22-06',
    datum DATE NOT NULL COMMENT 'Skiftets datum',
    -- Automatisk KPI-data (hämtas från rebotling_ibc)
    ibc_totalt INT NOT NULL DEFAULT 0 COMMENT 'Totalt antal IBC detta skift',
    ibc_per_h DECIMAL(6,1) NOT NULL DEFAULT 0.0 COMMENT 'IBC per timme',
    stopptid_min INT NOT NULL DEFAULT 0 COMMENT 'Total stopptid i minuter',
    kassationer INT NOT NULL DEFAULT 0 COMMENT 'Antal kasserade IBC',
    -- Fritext-fält (operatören fyller i)
    problem_text TEXT DEFAULT NULL COMMENT 'Problem/incidenter under skiftet',
    pagaende_arbete TEXT DEFAULT NULL COMMENT 'Pågående arbete att ta vid',
    instruktioner TEXT DEFAULT NULL COMMENT 'Specialinstruktioner till nästa skift',
    kommentar TEXT DEFAULT NULL COMMENT 'Generell kommentar',
    -- Flagga för aktiva problem
    har_pagaende_problem TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 om pågående problem flaggats',
    -- Metadata
    skapad DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_datum (datum),
    INDEX idx_operator (operator_id),
    INDEX idx_skift_typ (skift_typ),
    INDEX idx_pagaende (har_pagaende_problem),
    INDEX idx_skapad (skapad)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 2026-03-12_skiftplanering
-- ============================================================
-- Skiftplanering — bemanningsöversikt
-- Tabeller: skift_konfiguration + skift_schema

CREATE TABLE IF NOT EXISTS skift_konfiguration (
    id INT AUTO_INCREMENT PRIMARY KEY,
    skift_typ VARCHAR(20) NOT NULL UNIQUE,
    start_tid TIME NOT NULL,
    slut_tid TIME NOT NULL,
    min_bemanning INT NOT NULL DEFAULT 2,
    max_bemanning INT NOT NULL DEFAULT 6,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS skift_schema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operator_id INT NOT NULL,
    skift_typ VARCHAR(20) NOT NULL,
    datum DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_operator_datum (operator_id, datum),
    KEY idx_datum_skift (datum, skift_typ),
    CONSTRAINT fk_skift_typ FOREIGN KEY (skift_typ) REFERENCES skift_konfiguration(skift_typ)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed: 3 skifttyper
INSERT IGNORE INTO skift_konfiguration (skift_typ, start_tid, slut_tid, min_bemanning, max_bemanning) VALUES
('FM',   '06:00:00', '14:00:00', 3, 8),
('EM',   '14:00:00', '22:00:00', 3, 8),
('NATT', '22:00:00', '06:00:00', 2, 5);

-- Seed: Exempelschema för aktuell vecka (måndag-fredag)
-- Använder operatör-ID 1-8 som referens (om de finns i operators-tabellen)
SET @monday = DATE_SUB(CURDATE(), INTERVAL (WEEKDAY(CURDATE())) DAY);

INSERT IGNORE INTO skift_schema (operator_id, skift_typ, datum) VALUES
-- Måndag
(1, 'FM', @monday),
(2, 'FM', @monday),
(3, 'FM', @monday),
(4, 'EM', @monday),
(5, 'EM', @monday),
(6, 'EM', @monday),
(7, 'NATT', @monday),
(8, 'NATT', @monday),
-- Tisdag
(1, 'FM', DATE_ADD(@monday, INTERVAL 1 DAY)),
(2, 'FM', DATE_ADD(@monday, INTERVAL 1 DAY)),
(3, 'FM', DATE_ADD(@monday, INTERVAL 1 DAY)),
(4, 'EM', DATE_ADD(@monday, INTERVAL 1 DAY)),
(5, 'EM', DATE_ADD(@monday, INTERVAL 1 DAY)),
(7, 'NATT', DATE_ADD(@monday, INTERVAL 1 DAY)),
(8, 'NATT', DATE_ADD(@monday, INTERVAL 1 DAY)),
-- Onsdag
(1, 'FM', DATE_ADD(@monday, INTERVAL 2 DAY)),
(3, 'FM', DATE_ADD(@monday, INTERVAL 2 DAY)),
(4, 'EM', DATE_ADD(@monday, INTERVAL 2 DAY)),
(5, 'EM', DATE_ADD(@monday, INTERVAL 2 DAY)),
(6, 'EM', DATE_ADD(@monday, INTERVAL 2 DAY)),
(7, 'NATT', DATE_ADD(@monday, INTERVAL 2 DAY)),
-- Torsdag
(2, 'FM', DATE_ADD(@monday, INTERVAL 3 DAY)),
(3, 'FM', DATE_ADD(@monday, INTERVAL 3 DAY)),
(4, 'EM', DATE_ADD(@monday, INTERVAL 3 DAY)),
(5, 'EM', DATE_ADD(@monday, INTERVAL 3 DAY)),
(6, 'EM', DATE_ADD(@monday, INTERVAL 3 DAY)),
(7, 'NATT', DATE_ADD(@monday, INTERVAL 3 DAY)),
(8, 'NATT', DATE_ADD(@monday, INTERVAL 3 DAY)),
-- Fredag
(1, 'FM', DATE_ADD(@monday, INTERVAL 4 DAY)),
(2, 'FM', DATE_ADD(@monday, INTERVAL 4 DAY)),
(3, 'FM', DATE_ADD(@monday, INTERVAL 4 DAY)),
(4, 'EM', DATE_ADD(@monday, INTERVAL 4 DAY)),
(5, 'EM', DATE_ADD(@monday, INTERVAL 4 DAY)),
(8, 'NATT', DATE_ADD(@monday, INTERVAL 4 DAY));


-- ============================================================
-- 2026-03-12_stopptidsanalys
-- ============================================================
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
INSERT IGNORE INTO `maskin_stopptid`
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


-- ============================================================
-- 2026-03-13_gamification
-- ============================================================
-- Gamification: poängsystem, badges och milstolpar för operatörer
-- Denna migration skapar tabeller för att persistent lagra badges och poäng.
-- Kontrollern beräknar poäng dynamiskt från befintliga tabeller,
-- men badges sparas för historik.

CREATE TABLE IF NOT EXISTS gamification_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_id VARCHAR(50) NOT NULL COMMENT 'centurion, perfektionist, maratonlopare, stoppjagare, teamspelare',
    tilldelad_datum DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_badge_id (badge_id),
    UNIQUE KEY uq_user_badge_date (user_id, badge_id, tilldelad_datum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Sparade badges/utmarkelser for gamification-systemet';

CREATE TABLE IF NOT EXISTS gamification_milstolpar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    milstolpe_namn VARCHAR(50) NOT NULL COMMENT 'Nyborjare, Erfaren, Expert, Master, Legend, Mytisk',
    uppnadd_datum DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    UNIQUE KEY uq_user_milstolpe (user_id, milstolpe_namn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Uppnadda milstolpar for operatorer';


-- ============================================================
-- 2026-03-13_kapacitet_config
-- ============================================================
-- Kapacitet-konfiguration per rebotling-station
-- Teoretisk kapacitet, mal-utnyttjandegrad och bemanningsfaktor
-- Skapad: 2026-03-13

CREATE TABLE IF NOT EXISTS `kapacitet_config` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `station_id` VARCHAR(30) NOT NULL,
  `station_namn` VARCHAR(60) NOT NULL,
  `teoretisk_kapacitet_per_timme` DECIMAL(8,2) NOT NULL DEFAULT 30.00,
  `mal_utnyttjandegrad_pct` DECIMAL(5,2) NOT NULL DEFAULT 85.00,
  `ibc_per_operator_timme` DECIMAL(8,2) NOT NULL DEFAULT 15.00,
  `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_station_id` (`station_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed-data: rebotling-stationer
INSERT IGNORE INTO `kapacitet_config` (`station_id`, `station_namn`, `teoretisk_kapacitet_per_timme`, `mal_utnyttjandegrad_pct`, `ibc_per_operator_timme`) VALUES
('station_1', 'Station 1', 30.00, 85.00, 15.00),
('station_2', 'Station 2', 30.00, 85.00, 15.00),
('station_3', 'Station 3', 28.00, 85.00, 14.00),
('station_4', 'Station 4', 25.00, 80.00, 12.50),
('station_5', 'Station 5', 30.00, 85.00, 15.00),
('station_6', 'Station 6', 26.00, 82.00, 13.00)
ON DUPLICATE KEY UPDATE
  `station_namn` = VALUES(`station_namn`),
  `teoretisk_kapacitet_per_timme` = VALUES(`teoretisk_kapacitet_per_timme`),
  `mal_utnyttjandegrad_pct` = VALUES(`mal_utnyttjandegrad_pct`),
  `ibc_per_operator_timme` = VALUES(`ibc_per_operator_timme`);


-- ============================================================
-- 2026-03-13_kassationsalarminst
-- ============================================================
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
INSERT IGNORE INTO `rebotling_kassationsalarminst` (`varning_procent`, `alarm_procent`, `skapad_av`)
VALUES (3.00, 5.00, NULL);


-- ============================================================
-- 2026-03-13_produktionsmal
-- ============================================================
-- Produktionsmal-dashboard: tabell for VD att satta dag/vecko/manadsmal
-- Skapad: 2026-03-13
-- Uppdaterad: 2026-03-13 — lagt till 'dag' i typ-enum for dagliga produktionsmal

CREATE TABLE IF NOT EXISTS `rebotling_produktionsmal` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `typ` ENUM('dag','vecka','manad') NOT NULL DEFAULT 'vecka',
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

-- Lagg till 'dag' i befintlig enum om tabellen redan finns
ALTER TABLE `rebotling_produktionsmal`
  MODIFY COLUMN `typ` ENUM('dag','vecka','manad') NOT NULL DEFAULT 'vecka';


-- ============================================================
-- 2026-03-13_skiftoverlamning_checklista
-- ============================================================
-- Migration: 2026-03-13_skiftoverlamning_checklista.sql
-- Utökar skiftoverlamning_logg med checklista-JSON och mål nästa skift

ALTER TABLE skiftoverlamning_logg
  ADD COLUMN checklista_json JSON DEFAULT NULL COMMENT 'Checklista-status som JSON-array [{key, label, checked}]' AFTER kommentar,
  ADD COLUMN mal_nasta_skift TEXT DEFAULT NULL COMMENT 'Produktionsmål och fokusområden för nästa skift' AFTER checklista_json,
  ADD COLUMN allvarlighetsgrad ENUM('lag','medel','hog','kritisk') DEFAULT 'medel' COMMENT 'Allvarlighetsgrad för pågående problem' AFTER har_pagaende_problem;


-- ============================================================
-- 2026-03-13_skiftoverlamning
-- ============================================================
-- Migration: rebotling_skiftoverlamning
-- Digitalt skiftoverlamningsprotokoll for Rebotling-linjen
-- Skapad: 2026-03-13

CREATE TABLE IF NOT EXISTS rebotling_skiftoverlamning (
  id INT AUTO_INCREMENT PRIMARY KEY,
  skift_datum DATE NOT NULL,
  skift_typ VARCHAR(20) NOT NULL COMMENT 'dag, kvall, natt',
  operator_id INT,
  produktion_antal INT DEFAULT 0,
  oee_procent DECIMAL(5,2) DEFAULT 0.00,
  stopp_antal INT DEFAULT 0,
  stopp_minuter INT DEFAULT 0,
  kassation_procent DECIMAL(5,2) DEFAULT 0.00,
  checklista_rengoring TINYINT(1) DEFAULT 0,
  checklista_verktyg TINYINT(1) DEFAULT 0,
  checklista_kemikalier TINYINT(1) DEFAULT 0,
  checklista_avvikelser TINYINT(1) DEFAULT 0,
  checklista_sakerhet TINYINT(1) DEFAULT 0,
  checklista_material TINYINT(1) DEFAULT 0,
  kommentar_hande TEXT,
  kommentar_atgarda TEXT,
  kommentar_ovrigt TEXT,
  skapad DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_datum (skift_datum),
  INDEX idx_skift_typ (skift_typ),
  INDEX idx_operator (operator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 2026-03-13_underhallslogg
-- ============================================================
-- Migration: 2026-03-13_underhallslogg.sql
-- Skapar tabell for Rebotling-specifik underhallslogg kopplad till stationer

CREATE TABLE IF NOT EXISTS `rebotling_underhallslogg` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `station_id` INT NOT NULL DEFAULT 0,
    `typ` ENUM('planerat','oplanerat') NOT NULL,
    `beskrivning` TEXT,
    `varaktighet_min` INT NOT NULL DEFAULT 0,
    `stopporsak` VARCHAR(255) DEFAULT NULL,
    `utford_av` VARCHAR(100) DEFAULT NULL,
    `datum` DATETIME NOT NULL,
    `skapad` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_station_id` (`station_id`),
    KEY `idx_typ` (`typ`),
    KEY `idx_datum` (`datum`),
    KEY `idx_skapad` (`skapad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 2026-03-14_developer_role_and_feature_flags
-- ============================================================
-- Migration: Developer role + Feature flags system
-- Datum: 2026-03-14

-- 1. Ny role-kolumn på users (behåll admin-kolumnen för bakåtkompatibilitet)
ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user' AFTER admin;
UPDATE users SET role = 'admin' WHERE admin = 1;
UPDATE users SET role = 'user' WHERE admin = 0 OR admin IS NULL;

-- 2. Feature flags tabell
CREATE TABLE IF NOT EXISTS feature_flags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    feature_key VARCHAR(100) NOT NULL UNIQUE,
    label VARCHAR(200) NOT NULL,
    category VARCHAR(50) DEFAULT 'rebotling',
    min_role ENUM('public','user','admin','developer') NOT NULL DEFAULT 'developer',
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Seed: Alla menylänkar
-- Rollhierarki: public(0) < user(1) < admin(2) < developer(3)
-- Default: Live + Skiftrapport = public, resten = developer
INSERT IGNORE INTO feature_flags (feature_key, label, category, min_role) VALUES
-- Rebotling: public (alla ser)
('rebotling/live', 'Live', 'rebotling', 'public'),
('rebotling/live-ranking', 'Live Ranking', 'rebotling', 'public'),
('rebotling/skiftrapport', 'Skiftrapport', 'rebotling', 'public'),
('rebotling/statistik', 'Statistik', 'rebotling', 'public'),
('rebotling/historik', 'Historisk jämförelse', 'rebotling', 'public'),
('rebotling/andon', 'Andon-tavla', 'rebotling', 'public'),
('rebotling/andon-board', 'Fabriksskärm', 'rebotling', 'public'),

-- Rebotling: developer (dolt default)
('rebotling/produktions-dashboard', 'Produktions-dashboard', 'rebotling', 'admin'),
('rebotling/sammanfattning', 'Sammanfattning', 'rebotling', 'admin'),
('rebotling/kassationsanalys', 'Kassationsanalys', 'rebotling', 'admin'),
('rebotling/kassationsorsak-drilldown', 'Kassationsanalys+', 'rebotling', 'admin'),
('rebotling/cykeltid-heatmap', 'Cykeltids-heatmap', 'rebotling', 'admin'),
('rebotling/produkttyp-effektivitet', 'Produkttyp-effektivitet', 'rebotling', 'admin'),
('rebotling/narvarotracker', 'Närvarotracker', 'rebotling', 'admin'),
('rebotling/produktionspuls', 'Produktionspuls', 'rebotling', 'admin'),
('rebotling/produktionstakt', 'Produktionstakt', 'rebotling', 'admin'),
('rebotling/benchmarking', 'Benchmarking', 'rebotling', 'admin'),
('rebotling/overlamning', 'Skiftöverlämning', 'rebotling', 'admin'),
('rebotling/skiftoverlamning', 'Skiftoverlamning', 'rebotling', 'admin'),
('rebotling/operatorsportal', 'Min portal', 'rebotling', 'admin'),
('rebotling/min-dag', 'Min dag', 'rebotling', 'admin'),
('rebotling/operator-dashboard', 'Min statistik', 'rebotling', 'admin'),
('min-bonus', 'Min Bonus', 'rebotling', 'admin'),
('rebotling/stopporsak-registrering', 'Registrera stopp', 'rebotling', 'admin'),
('rebotling/underhallslogg', 'Underhållslogg', 'rebotling', 'admin'),
('rebotling/stopporsaker', 'Stopporsak-dashboard', 'rebotling', 'admin'),
('rebotling/oee-benchmark', 'OEE Benchmark', 'rebotling', 'admin'),
('rebotling/skiftrapport-export', 'Skiftrapport PDF', 'rebotling', 'admin'),
('rebotling/feedback-analys', 'Feedback-analys', 'rebotling', 'admin'),
('rebotling/ranking-historik', 'Ranking-historik', 'rebotling', 'admin'),
('rebotling/daglig-sammanfattning', 'Daglig sammanfattning', 'rebotling', 'admin'),
('rebotling/produktionskalender', 'Produktionskalender', 'rebotling', 'admin'),
('rebotling/skiftjamforelse', 'Skiftjämförelse', 'rebotling', 'admin'),
('rebotling/malhistorik', 'Målhistorik', 'rebotling', 'admin'),
('rebotling/underhallsprognos', 'Underhållsprognos', 'rebotling', 'admin'),
('rebotling/prediktivt-underhall', 'Prediktivt underhåll', 'rebotling', 'admin'),
('rebotling/effektivitet', 'Maskineffektivitet', 'rebotling', 'admin'),
('rebotling/produktionsmal', 'Produktionsmål', 'rebotling', 'admin'),
('rebotling/utnyttjandegrad', 'Utnyttjandegrad', 'rebotling', 'admin'),
('rebotling/daglig-briefing', 'Daglig briefing', 'rebotling', 'admin'),
('rebotling/morgonrapport', 'Morgonrapport', 'rebotling', 'admin'),
('rebotling/veckorapport', 'Veckorapport', 'rebotling', 'admin'),
('rebotling/alarm-historik', 'Alarm-historik', 'rebotling', 'admin'),
('rebotling/pareto', 'Pareto-analys', 'rebotling', 'admin'),
('rebotling/produktions-heatmap', 'Produktions-heatmap', 'rebotling', 'admin'),
('rebotling/oee-waterfall', 'OEE-analys', 'rebotling', 'admin'),
('rebotling/drifttids-timeline', 'Drifttids-timeline', 'rebotling', 'admin'),
('rebotling/forsta-timme-analys', 'Första timmen', 'rebotling', 'admin'),
('rebotling/produktionsprognos', 'Produktionsprognos', 'rebotling', 'admin'),
('rebotling/stopporsak-operator', 'Stopporsak per operatör', 'rebotling', 'admin'),
('rebotling/operator-onboarding', 'Operator-onboarding', 'rebotling', 'admin'),
('rebotling/operator-jamforelse', 'Operatörsjämförelse', 'rebotling', 'admin'),
('rebotling/produktionseffektivitet', 'Produktionseffektivitet/h', 'rebotling', 'admin'),
('rebotling/kvalitets-trendbrott', 'Kvalitets-trendbrott', 'rebotling', 'admin'),
('rebotling/maskinunderhall', 'Maskinunderhåll', 'rebotling', 'admin'),
('rebotling/statistik-dashboard', 'Statistik-dashboard', 'rebotling', 'admin'),
('rebotling/batch-sparning', 'Batch-spårning', 'rebotling', 'admin'),
('rebotling/kassationsorsak-statistik', 'Kassationsorsak-statistik', 'rebotling', 'admin'),
('rebotling/produktions-sla', 'Måluppfyllnad', 'rebotling', 'admin'),
('rebotling/skiftplanering', 'Skiftplanering', 'rebotling', 'admin'),
('rebotling/produktionskostnad', 'Produktionskostnad/IBC', 'rebotling', 'admin'),
('rebotling/stopptidsanalys', 'Stopptidsanalys', 'rebotling', 'admin'),
('rebotling/maskin-oee', 'Maskin-OEE', 'rebotling', 'admin'),
('rebotling/operatorsbonus', 'Operatörsbonus', 'rebotling', 'admin'),
('rebotling/leveransplanering', 'Leveransplanering', 'rebotling', 'admin'),
('rebotling/kvalitetscertifikat', 'Kvalitetscertifikat', 'rebotling', 'admin'),
('rebotling/historisk-produktion', 'Historisk produktion', 'rebotling', 'admin'),
('rebotling/avvikelselarm', 'Avvikelselarm', 'rebotling', 'admin'),
('rebotling/produktionsflode', 'Produktionsflöde', 'rebotling', 'admin'),
('rebotling/kassationsorsak', 'Kassationsorsak per station', 'rebotling', 'admin'),
('rebotling/oee-jamforelse', 'OEE-jämförelse', 'rebotling', 'admin'),
('rebotling/maskin-drifttid', 'Maskin-drifttid heatmap', 'rebotling', 'admin'),
('rebotling/skiftrapport-sammanstallning', 'Skiftsammanställning', 'rebotling', 'admin'),
('rebotling/maskinhistorik', 'Maskinhistorik per station', 'rebotling', 'admin'),
('rebotling/kassationskvot-alarm', 'Kassationskvot-alarm', 'rebotling', 'admin'),
('rebotling/kapacitetsplanering', 'Kapacitetsplanering', 'rebotling', 'admin'),
('rebotling/rebotling-trendanalys', 'Trendanalys', 'rebotling', 'admin'),
('rebotling/operators-prestanda', 'Operatörs-prestanda', 'rebotling', 'admin'),
('rebotling/stationsdetalj', 'Stationsdetalj', 'rebotling', 'admin'),
('rebotling/vd-veckorapport', 'VD Veckorapport', 'rebotling', 'admin'),
('rebotling/produktionsmal-uppfoljning', 'Produktionsmål-uppföljning', 'rebotling', 'admin'),
('rebotling/tidrapport', 'Tidrapport', 'rebotling', 'admin'),
('rebotling/oee-trendanalys', 'OEE Trendanalys', 'rebotling', 'admin'),
('rebotling/operator-ranking', 'Operatörsranking', 'rebotling', 'admin'),
('rebotling/gamification', 'Gamification', 'rebotling', 'admin'),
('rebotling/vd-dashboard', 'VD Dashboard', 'rebotling', 'admin'),
('rebotling/historisk-sammanfattning', 'Historisk sammanfattning', 'rebotling', 'admin'),
('rebotling/kvalitetstrendanalys', 'Kvalitetstrend-analys', 'rebotling', 'admin'),

-- Rebotling admin
('rebotling/admin', 'Rebotling Admin', 'rebotling-admin', 'admin'),
('rebotling/bonus', 'Bonus', 'rebotling-admin', 'admin'),
('rebotling/bonus-admin', 'Bonus Admin', 'rebotling-admin', 'admin'),
('rebotling/analys', 'Produktionsanalys', 'rebotling-admin', 'admin'),
('rebotling/kalender', 'Produktionskalender (Admin)', 'rebotling-admin', 'admin'),
('rebotling/prognos', 'Leveransprognos', 'rebotling-admin', 'admin'),

-- Övriga linjer (public)
('tvattlinje/live', 'Tvättlinje Live', 'tvattlinje', 'public'),
('tvattlinje/skiftrapport', 'Tvättlinje Skiftrapport', 'tvattlinje', 'public'),
('tvattlinje/statistik', 'Tvättlinje Statistik', 'tvattlinje', 'public'),
('tvattlinje/admin', 'Tvättlinje Admin', 'tvattlinje', 'admin'),
('saglinje/live', 'Såglinje Live', 'saglinje', 'public'),
('saglinje/skiftrapport', 'Såglinje Skiftrapport', 'saglinje', 'public'),
('saglinje/statistik', 'Såglinje Statistik', 'saglinje', 'public'),
('saglinje/admin', 'Såglinje Admin', 'saglinje', 'admin'),
('klassificeringslinje/live', 'Klassificeringslinje Live', 'klassificeringslinje', 'public'),
('klassificeringslinje/skiftrapport', 'Klassificeringslinje Skiftrapport', 'klassificeringslinje', 'public'),
('klassificeringslinje/statistik', 'Klassificeringslinje Statistik', 'klassificeringslinje', 'public'),
('klassificeringslinje/admin', 'Klassificeringslinje Admin', 'klassificeringslinje', 'admin'),

-- Favoriter, rapporter
('favoriter', 'Favoriter', 'system', 'admin'),
('statistik/overblick', 'Statistik-överblick', 'rapporter', 'admin'),
('rapporter/manad', 'Månadsrapport', 'rapporter', 'admin'),
('rapporter/vecka', 'Veckorapport (Rapporter)', 'rapporter', 'admin'),

-- Admin-sidor
('oversikt', 'Översikt (Executive)', 'admin', 'admin'),
('rebotling/alerts', 'Varningar', 'admin', 'admin'),
('stopporsaker', 'Stopporsaker', 'admin', 'admin'),
('admin/users', 'Användare', 'admin', 'admin'),
('admin/create-user', 'Skapa användare', 'admin', 'admin'),
('admin/operators', 'Operatörer', 'admin', 'admin'),
('admin/skiftplan', 'Skiftplan', 'admin', 'admin'),
('admin/certifiering', 'Certifiering', 'admin', 'admin'),
('admin/operator-dashboard', 'Operatörsdashboard', 'admin', 'admin'),
('admin/operator-compare', 'Operatörsjämförelse (Admin)', 'admin', 'admin'),
('admin/operator-attendance', 'Närvaro', 'admin', 'admin'),
('admin/operator-trend', 'Prestanda-trend', 'admin', 'admin'),
('admin/kvalitetstrend', 'Kvalitetstrend (Admin)', 'admin', 'admin'),
('admin/stopporsak-trend', 'Stopporsak-trend', 'admin', 'admin'),
('admin/underhall', 'Underhållslogg (Admin)', 'admin', 'admin'),
('admin/vpn', 'VPN', 'admin', 'admin'),
('admin/audit', 'Aktivitetslogg', 'admin', 'admin'),
('admin/news', 'Nyheter', 'admin', 'admin'),
('admin/feature-flags', 'Funktionshantering', 'admin', 'admin')
ON DUPLICATE KEY UPDATE label = VALUES(label);


-- ============================================================
-- 2026-03-14_driftstopp
-- ============================================================
-- Migration: Driftstopp-tabell för PLC-kommando
-- Datum: 2026-03-14

CREATE TABLE IF NOT EXISTS rebotling_driftstopp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    datum DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    driftstopp_status TINYINT(1) NOT NULL DEFAULT 0,
    skiftraknare INT DEFAULT NULL,
    KEY idx_datum (datum),
    KEY idx_skiftraknare (skiftraknare)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ny kolumn på skiftrapport för att lagra driftstopptid per skift
ALTER TABLE rebotling_skiftrapport ADD COLUMN driftstopptime INT DEFAULT NULL AFTER rasttime;


-- ============================================================
-- 2026-03-15_add_operator_id_to_users
-- ============================================================
-- Migration: Lagg till operator_id-kolumn i users-tabellen
-- LoginController refererar operator_id i SELECT men kolumnen saknades
-- Datum: 2026-03-15

ALTER TABLE users ADD COLUMN IF NOT EXISTS operator_id INT DEFAULT NULL AFTER role;

SET FOREIGN_KEY_CHECKS = 1;
-- ============================================================
-- KLAR — alla migreringar körda (uppdaterad 2026-03-16)
-- ============================================================

-- ============================================================
-- INSTALL_ALL.sql
-- Samlade migreringar för mauserdb — kör denna enda fil
-- Genererad: 2026-03-05
-- OBS: Hoppar över bcrypt-migrering (lösenord använder sha1/md5)
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

INSERT INTO bonus_config (id, weekly_bonus_goal)
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

-- Om tabellen existerar med gammalt schema (antal_per_dag etc), lägg till de nya kolumnerna
ALTER TABLE tvattlinje_settings
  ADD COLUMN IF NOT EXISTS setting VARCHAR(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS value VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

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
SET FOREIGN_KEY_CHECKS = 1;
-- ============================================================
-- KLAR — alla migreringar körda
-- ============================================================

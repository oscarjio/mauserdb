-- Migration: Såglinje settings och weekday-goals tabeller
-- Datum: 2026-03-04

CREATE TABLE IF NOT EXISTS saglinje_settings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  setting VARCHAR(100) NOT NULL UNIQUE,
  value VARCHAR(255),
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Inställningar för såglinjen';

INSERT IGNORE INTO saglinje_settings (setting, value) VALUES
  ('dagmal',      '50'),
  ('takt_mal',    '10'),
  ('skift_start', '06:00'),
  ('skift_slut',  '22:00');

CREATE TABLE IF NOT EXISTS saglinje_weekday_goals (
  id INT PRIMARY KEY AUTO_INCREMENT,
  weekday TINYINT NOT NULL UNIQUE COMMENT '0=Måndag, 6=Söndag',
  mal INT NOT NULL DEFAULT 50,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Veckodagsmål för såglinjen';

INSERT IGNORE INTO saglinje_weekday_goals (weekday, mal) VALUES
  (0, 50),
  (1, 50),
  (2, 50),
  (3, 50),
  (4, 50),
  (5, 30),
  (6, 0);

-- Migration: 2026-03-04_klassificeringslinje_settings.sql
-- Skapar settings- och veckodagsmål-tabeller för klassificeringslinjen
-- Körs manuellt av ägaren mot produktionsdatabasen

CREATE TABLE IF NOT EXISTS klassificeringslinje_settings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  setting VARCHAR(100) NOT NULL UNIQUE,
  value VARCHAR(255),
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO klassificeringslinje_settings (setting, value) VALUES
  ('dagmal', '120'),
  ('takt_mal', '20'),
  ('skift_start', '06:00'),
  ('skift_slut', '22:00');

CREATE TABLE IF NOT EXISTS klassificeringslinje_weekday_goals (
  id INT PRIMARY KEY AUTO_INCREMENT,
  weekday TINYINT NOT NULL UNIQUE COMMENT '0=Måndag, 6=Söndag',
  mal INT NOT NULL DEFAULT 120,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO klassificeringslinje_weekday_goals (weekday, mal) VALUES
  (0,120),(1,120),(2,120),(3,120),(4,120),(5,80),(6,0);

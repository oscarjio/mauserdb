-- Migration: 2026-03-04_maintenance_log.sql
-- Skapar tabell för underhållslogg

CREATE TABLE IF NOT EXISTS maintenance_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  line ENUM('rebotling','tvattlinje','saglinje','klassificeringslinje','allmant') NOT NULL DEFAULT 'rebotling',
  maintenance_type ENUM('planerat','akut','inspektion','kalibrering','rengoring','ovrigt') NOT NULL DEFAULT 'ovrigt',
  title VARCHAR(150) NOT NULL,
  description TEXT NULL,
  start_time DATETIME NOT NULL,
  duration_minutes INT NULL COMMENT 'NULL om pågående',
  performed_by VARCHAR(100) NULL,
  cost_sek DECIMAL(10,2) NULL,
  status ENUM('planerat','pagaende','klart','avbokat') NOT NULL DEFAULT 'klart',
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_line_date (line, start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration: 2026-03-04_operator_feedback.sql
-- Skapar tabell för operatörsfeedback efter skift

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

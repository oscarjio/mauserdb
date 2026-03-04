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

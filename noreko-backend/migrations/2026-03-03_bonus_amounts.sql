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

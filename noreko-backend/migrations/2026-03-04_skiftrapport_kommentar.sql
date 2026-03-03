CREATE TABLE IF NOT EXISTS rebotling_skift_kommentar (
  id INT PRIMARY KEY AUTO_INCREMENT,
  datum DATE NOT NULL,
  skift_nr INT NOT NULL,
  kommentar TEXT,
  skapad_av VARCHAR(100),
  skapad_tid TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_skift (datum, skift_nr)
);

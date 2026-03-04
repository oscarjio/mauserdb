-- Kassationsorsaksanalys: tabeller för att spåra orsaker till kasserade IBC/burar
-- Skapad: 2026-03-04

CREATE TABLE IF NOT EXISTS kassationsorsak_typer (
  id INT AUTO_INCREMENT PRIMARY KEY,
  namn VARCHAR(100) NOT NULL,
  beskrivning TEXT,
  aktiv TINYINT(1) DEFAULT 1,
  sortorder INT DEFAULT 0
);

INSERT INTO kassationsorsak_typer (namn, sortorder) VALUES
  ('Skada/deformation', 1),
  ('Kontaminering', 2),
  ('Läckage', 3),
  ('Ventilfel', 4),
  ('Korrosion', 5),
  ('Övrigt', 99);

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

-- min_operators-kolumn i rebotling_settings för bemanningsvarning (default 2)
ALTER TABLE rebotling_settings ADD COLUMN IF NOT EXISTS min_operators INT DEFAULT 2;

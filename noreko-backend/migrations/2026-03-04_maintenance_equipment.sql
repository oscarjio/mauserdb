-- Migration: 2026-03-04_maintenance_equipment.sql
-- Lägg till utrustnings-koppling och statistikfält i maintenance_log

ALTER TABLE maintenance_log ADD COLUMN IF NOT EXISTS equipment VARCHAR(100) DEFAULT NULL;
ALTER TABLE maintenance_log ADD COLUMN IF NOT EXISTS downtime_minutes INT DEFAULT 0;
ALTER TABLE maintenance_log ADD COLUMN IF NOT EXISTS cost_sek DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE maintenance_log ADD COLUMN IF NOT EXISTS resolved TINYINT(1) DEFAULT 0;

-- Utrustnings-kategorier (referenstabell)
CREATE TABLE IF NOT EXISTS maintenance_equipment (
  id INT AUTO_INCREMENT PRIMARY KEY,
  namn VARCHAR(100) NOT NULL,
  kategori ENUM('maskin', 'transport', 'verktyg', 'infrastruktur', 'övrigt') DEFAULT 'maskin',
  linje VARCHAR(50) DEFAULT 'rebotling',
  aktiv TINYINT(1) DEFAULT 1
);

INSERT IGNORE INTO maintenance_equipment (id, namn, kategori, linje) VALUES
(1, 'Rebotling-robot', 'maskin', 'rebotling'),
(2, 'Konveyor', 'transport', 'rebotling'),
(3, 'Hydraulik-press', 'maskin', 'rebotling'),
(4, 'Luftkompressor', 'infrastruktur', 'rebotling'),
(5, 'PLC-styrenhet', 'maskin', 'rebotling'),
(6, 'Övrig utrustning', 'övrigt', 'rebotling');

-- Migration: 2026-03-12_maskinunderhall.sql
-- Maskinunderhåll — register och servicelogg för IBC-rebotling-linje

CREATE TABLE IF NOT EXISTS maskin_register (
  id INT AUTO_INCREMENT PRIMARY KEY,
  namn VARCHAR(100) NOT NULL,
  beskrivning TEXT,
  service_intervall_dagar INT NOT NULL DEFAULT 90,
  aktiv TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS maskin_service_logg (
  id INT AUTO_INCREMENT PRIMARY KEY,
  maskin_id INT NOT NULL,
  service_datum DATE NOT NULL,
  service_typ ENUM('planerat', 'akut', 'inspektion') NOT NULL DEFAULT 'planerat',
  beskrivning TEXT,
  utfort_av VARCHAR(100),
  nasta_planerad_datum DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (maskin_id) REFERENCES maskin_register(id),
  INDEX idx_maskin_id (maskin_id),
  INDEX idx_service_datum (service_datum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed-data: typiska maskiner i en IBC-rebotling-linje
INSERT INTO maskin_register (namn, beskrivning, service_intervall_dagar) VALUES
('Tvättmaskin', 'Huvudtvätt för IBC-tankar', 90),
('Torkugn', 'Torkstation efter tvätt', 180),
('Inspektionsstation', 'Visuell kontroll och test', 60),
('Transportband', 'Huvudtransportör rebotling-linje', 30),
('Etiketterare', 'Automatisk märkningsmaskin', 45),
('Ventiltestare', 'Testning av IBC-ventiler', 60);

-- Migration: rebotling_skiftoverlamning
-- Digitalt skiftoverlamningsprotokoll for Rebotling-linjen
-- Skapad: 2026-03-13

CREATE TABLE IF NOT EXISTS rebotling_skiftoverlamning (
  id INT AUTO_INCREMENT PRIMARY KEY,
  skift_datum DATE NOT NULL,
  skift_typ VARCHAR(20) NOT NULL COMMENT 'dag, kvall, natt',
  operator_id INT,
  produktion_antal INT DEFAULT 0,
  oee_procent DECIMAL(5,2) DEFAULT 0.00,
  stopp_antal INT DEFAULT 0,
  stopp_minuter INT DEFAULT 0,
  kassation_procent DECIMAL(5,2) DEFAULT 0.00,
  checklista_rengoring TINYINT(1) DEFAULT 0,
  checklista_verktyg TINYINT(1) DEFAULT 0,
  checklista_kemikalier TINYINT(1) DEFAULT 0,
  checklista_avvikelser TINYINT(1) DEFAULT 0,
  checklista_sakerhet TINYINT(1) DEFAULT 0,
  checklista_material TINYINT(1) DEFAULT 0,
  kommentar_hande TEXT,
  kommentar_atgarda TEXT,
  kommentar_ovrigt TEXT,
  skapad DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_datum (skift_datum),
  INDEX idx_skift_typ (skift_typ),
  INDEX idx_operator (operator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

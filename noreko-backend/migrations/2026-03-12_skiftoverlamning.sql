-- Migration: 2026-03-12_skiftoverlamning.sql
-- Skiftöverlämningslogg — strukturerat överlämningsformulär med auto-KPI:er
-- Utökar den enklare skiftoverlamning_notes-tabellen med ett komplett logg-system.

CREATE TABLE IF NOT EXISTS skiftoverlamning_logg (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operator_id INT NOT NULL COMMENT 'Avgående operatör (user_id)',
    operator_namn VARCHAR(100) DEFAULT NULL COMMENT 'Cachat operatörsnamn vid skapande',
    skift_typ ENUM('dag','kvall','natt') NOT NULL DEFAULT 'dag' COMMENT 'dag=06-14, kvall=14-22, natt=22-06',
    datum DATE NOT NULL COMMENT 'Skiftets datum',
    -- Automatisk KPI-data (hämtas från rebotling_ibc)
    ibc_totalt INT NOT NULL DEFAULT 0 COMMENT 'Totalt antal IBC detta skift',
    ibc_per_h DECIMAL(6,1) NOT NULL DEFAULT 0.0 COMMENT 'IBC per timme',
    stopptid_min INT NOT NULL DEFAULT 0 COMMENT 'Total stopptid i minuter',
    kassationer INT NOT NULL DEFAULT 0 COMMENT 'Antal kasserade IBC',
    -- Fritext-fält (operatören fyller i)
    problem_text TEXT DEFAULT NULL COMMENT 'Problem/incidenter under skiftet',
    pagaende_arbete TEXT DEFAULT NULL COMMENT 'Pågående arbete att ta vid',
    instruktioner TEXT DEFAULT NULL COMMENT 'Specialinstruktioner till nästa skift',
    kommentar TEXT DEFAULT NULL COMMENT 'Generell kommentar',
    -- Flagga för aktiva problem
    har_pagaende_problem TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 om pågående problem flaggats',
    -- Metadata
    skapad DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_datum (datum),
    INDEX idx_operator (operator_id),
    INDEX idx_skift_typ (skift_typ),
    INDEX idx_pagaende (har_pagaende_problem),
    INDEX idx_skapad (skapad)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

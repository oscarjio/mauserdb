-- Migration: Stoppanalys-tabeller
-- Datum: 2026-03-04
-- Beskrivning: Skapar stoppage_reasons och stoppage_log för detaljerad stoppanalys

CREATE TABLE IF NOT EXISTS stoppage_reasons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    category ENUM('maskin','material','operatör','övrigt') NOT NULL DEFAULT 'övrigt',
    description TEXT,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stoppage_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    reason_id INT NOT NULL,
    started_at DATETIME NOT NULL,
    ended_at DATETIME,
    duration_minutes DECIMAL(8,2),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    notes TEXT,
    FOREIGN KEY (reason_id) REFERENCES stoppage_reasons(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index för prestanda på vanliga queries
CREATE INDEX IF NOT EXISTS idx_stoppage_log_created_at ON stoppage_log(created_at);
CREATE INDEX IF NOT EXISTS idx_stoppage_log_reason_id ON stoppage_log(reason_id);

-- Grunddata: vanliga stopp-orsaker
INSERT IGNORE INTO stoppage_reasons (id, name, category, description) VALUES
    (1, 'PLC-larm',            'maskin',    'PLC-larm eller felindikatorer'),
    (2, 'Mekaniskt fel',       'maskin',    'Mekaniska fel på linjen'),
    (3, 'Sensorkalibrering',   'maskin',    'Sensor ur kalibrering'),
    (4, 'Ventilfel',           'maskin',    'Ventil eller pumpproblem'),
    (5, 'Materialbrist',       'material',  'Tom buffert, inväntar material'),
    (6, 'Fel IBC-typ',         'material',  'Fel IBC-typ eller skadad IBC'),
    (7, 'Etikettproblem',      'material',  'Etikett saknas eller fel'),
    (8, 'Operatörsrast',       'operatör',  'Planerad rast'),
    (9, 'Operatörsavstängning','operatör',  'Operatör stängde av linjen manuellt'),
   (10, 'Rengöring',           'operatör',  'Rengöring av utrustning'),
   (11, 'Övrigt',              'övrigt',    'Övrig orsak');

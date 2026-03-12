-- Migration: Kvalitetscertifikat per batch
-- Datum: 2026-03-12

CREATE TABLE IF NOT EXISTS kvalitetscertifikat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NULL,
    batch_nummer VARCHAR(50) NOT NULL,
    datum DATE NOT NULL,
    operator_id INT NULL,
    operator_namn VARCHAR(100) NOT NULL DEFAULT '',
    antal_ibc INT NOT NULL DEFAULT 0,
    kassation_procent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    cykeltid_snitt DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    kvalitetspoang DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    status ENUM('godkand','underkand','ej_bedomd') NOT NULL DEFAULT 'ej_bedomd',
    kommentar TEXT NULL,
    bedomd_av VARCHAR(100) NULL,
    bedomd_datum DATETIME NULL,
    skapad_datum TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_kc_batch (batch_nummer),
    INDEX idx_kc_datum (datum),
    INDEX idx_kc_status (status),
    INDEX idx_kc_operator (operator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kvalitetskriterier (
    id INT AUTO_INCREMENT PRIMARY KEY,
    namn VARCHAR(100) NOT NULL,
    beskrivning TEXT NULL,
    min_varde DECIMAL(10,2) NULL,
    max_varde DECIMAL(10,2) NULL,
    vikt DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    aktiv BOOLEAN NOT NULL DEFAULT TRUE,
    skapad_datum TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: kvalitetskriterier
INSERT INTO kvalitetskriterier (namn, beskrivning, min_varde, max_varde, vikt, aktiv) VALUES
('Kassation',       'Kassationsprocent under grans',          NULL,  3.00, 30.00, TRUE),
('Cykeltid',        'Genomsnittlig cykeltid under grans',     NULL, 45.00, 25.00, TRUE),
('Antal IBC',       'Minsta antal IBC i batchen',            50.00,  NULL, 20.00, TRUE),
('Jaemnhet',        'Jamnhet i cykeltider (liten spridning)', NULL,  5.00, 15.00, TRUE),
('Operatoerserfarenhet', 'Operatoren har certifiering',       1.00,  NULL, 10.00, TRUE);

-- Seed: exempelcertifikat (25 st)
INSERT INTO kvalitetscertifikat (batch_nummer, datum, operator_id, operator_namn, antal_ibc, kassation_procent, cykeltid_snitt, kvalitetspoang, status, kommentar, bedomd_av, bedomd_datum) VALUES
('B-2026-0301',  '2026-02-10', 1, 'Erik Lindberg',    185, 1.20, 38.5, 94.5, 'godkand',   'Utmarkt batch, alla kriterier uppfyllda.',        'Admin', '2026-02-10 16:00:00'),
('B-2026-0302',  '2026-02-11', 2, 'Anna Svensson',    192, 0.80, 36.2, 97.2, 'godkand',   'Mycket bra kvalitet och kort cykeltid.',           'Admin', '2026-02-11 16:30:00'),
('B-2026-0303',  '2026-02-12', 3, 'Karl Johansson',   178, 2.90, 42.1, 81.3, 'godkand',   'Godkand men nara kassationsgrans.',                'Admin', '2026-02-12 15:45:00'),
('B-2026-0304',  '2026-02-13', 1, 'Erik Lindberg',    165, 4.50, 47.8, 52.1, 'underkand', 'For hog kassation och cykeltid over grans.',       'Admin', '2026-02-13 16:15:00'),
('B-2026-0305',  '2026-02-14', 4, 'Lisa Andersson',   201, 1.50, 39.7, 91.8, 'godkand',   'Bra batch med hog volym.',                         'Admin', '2026-02-14 16:00:00'),
('B-2026-0306',  '2026-02-17', 2, 'Anna Svensson',    188, 1.10, 37.4, 95.6, 'godkand',   'Stabil kvalitet som vanligt.',                     'Admin', '2026-02-17 16:30:00'),
('B-2026-0307',  '2026-02-18', 5, 'Johan Nilsson',    172, 3.20, 44.5, 74.8, 'underkand', 'Kassation over grans, behover aterutbildning.',    'Admin', '2026-02-18 15:00:00'),
('B-2026-0308',  '2026-02-19', 3, 'Karl Johansson',   195, 1.80, 40.3, 88.4, 'godkand',   'Godkand, cykeltid lite hog men acceptabel.',       'Admin', '2026-02-19 16:00:00'),
('B-2026-0309',  '2026-02-20', 1, 'Erik Lindberg',    210, 0.90, 35.8, 96.7, 'godkand',   'Toppresultat, basta batchen denna manad.',         'Admin', '2026-02-20 16:00:00'),
('B-2026-0310',  '2026-02-21', 4, 'Lisa Andersson',   183, 2.10, 41.2, 86.5, 'godkand',   'Godkand med marginal.',                            'Admin', '2026-02-21 16:00:00'),
('B-2026-0311',  '2026-02-24', 2, 'Anna Svensson',    197, 0.70, 35.1, 98.1, 'godkand',   'Utmarkt resultat pa alla parametrar.',             'Admin', '2026-02-24 16:00:00'),
('B-2026-0312',  '2026-02-25', 5, 'Johan Nilsson',    168, 2.50, 43.0, 79.2, 'godkand',   'Precis godkand, kassation nara grans.',            'Admin', '2026-02-25 16:00:00'),
('B-2026-0313',  '2026-02-26', 3, 'Karl Johansson',   190, 1.60, 39.5, 90.3, 'godkand',   'Bra kvalitet.',                                    'Admin', '2026-02-26 16:30:00'),
('B-2026-0314',  '2026-02-27', 1, 'Erik Lindberg',    175, 3.80, 46.2, 61.4, 'underkand', 'Kassation och cykeltid over grans.',               'Admin', '2026-02-27 16:00:00'),
('B-2026-0315',  '2026-02-28', 4, 'Lisa Andersson',   204, 1.30, 38.1, 93.7, 'godkand',   'Hog volym och bra kvalitet.',                      'Admin', '2026-02-28 16:00:00'),
('B-2026-0316',  '2026-03-03', 2, 'Anna Svensson',    193, 0.60, 34.8, 98.5, 'godkand',   'Basta operatoren denna period.',                   'Admin', '2026-03-03 16:00:00'),
('B-2026-0317',  '2026-03-04', 5, 'Johan Nilsson',    180, 2.80, 43.7, 77.1, 'godkand',   'Godkand men behover forbattring pa cykeltid.',     'Admin', '2026-03-04 16:00:00'),
('B-2026-0318',  '2026-03-05', 3, 'Karl Johansson',   187, 1.40, 38.9, 92.6, 'godkand',   'Stabil prestation.',                               'Admin', '2026-03-05 16:00:00'),
('B-2026-0319',  '2026-03-06', 1, 'Erik Lindberg',    199, 1.00, 37.0, 95.9, 'godkand',   'Mycket bra resultat.',                             'Admin', '2026-03-06 16:00:00'),
('B-2026-0320',  '2026-03-07', 4, 'Lisa Andersson',   191, 1.70, 40.5, 89.1, 'godkand',   'Godkand, allt inom granserna.',                    'Admin', '2026-03-07 16:00:00'),
('B-2026-0321',  '2026-03-10', 2, 'Anna Svensson',    205, 0.50, 34.2, 99.0, 'godkand',   'Perfekt batch.',                                   'Admin', '2026-03-10 16:00:00'),
('B-2026-0322',  '2026-03-10', 5, 'Johan Nilsson',    170, 3.50, 45.8, 67.3, 'underkand', 'Kassation och cykeltid over grans.',               'Admin', '2026-03-10 16:30:00'),
('B-2026-0323',  '2026-03-11', 3, 'Karl Johansson',   182, 2.00, 41.0, 85.8, 'godkand',   'OK batch.',                                        'Admin', '2026-03-11 16:00:00'),
('B-2026-0324',  '2026-03-11', 1, 'Erik Lindberg',    196, 1.10, 36.5, 95.2, 'godkand',   'Bra resultat som forväntat.',                      'Admin', '2026-03-11 16:30:00'),
('B-2026-0325',  '2026-03-12', 4, 'Lisa Andersson',   188, 1.90, 40.8, 87.9, 'ej_bedomd', NULL,                                               NULL,    NULL);

-- Skiftplanering — bemanningsöversikt
-- Tabeller: skift_konfiguration + skift_schema

CREATE TABLE IF NOT EXISTS skift_konfiguration (
    id INT AUTO_INCREMENT PRIMARY KEY,
    skift_typ VARCHAR(20) NOT NULL UNIQUE,
    start_tid TIME NOT NULL,
    slut_tid TIME NOT NULL,
    min_bemanning INT NOT NULL DEFAULT 2,
    max_bemanning INT NOT NULL DEFAULT 6,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS skift_schema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operator_id INT NOT NULL,
    skift_typ VARCHAR(20) NOT NULL,
    datum DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_operator_datum (operator_id, datum),
    KEY idx_datum_skift (datum, skift_typ),
    CONSTRAINT fk_skift_typ FOREIGN KEY (skift_typ) REFERENCES skift_konfiguration(skift_typ)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed: 3 skifttyper
INSERT IGNORE INTO skift_konfiguration (skift_typ, start_tid, slut_tid, min_bemanning, max_bemanning) VALUES
('FM',   '06:00:00', '14:00:00', 3, 8),
('EM',   '14:00:00', '22:00:00', 3, 8),
('NATT', '22:00:00', '06:00:00', 2, 5);

-- Seed: Exempelschema för aktuell vecka (måndag-fredag)
-- Använder operatör-ID 1-8 som referens (om de finns i operators-tabellen)
SET @monday = DATE_SUB(CURDATE(), INTERVAL (WEEKDAY(CURDATE())) DAY);

INSERT IGNORE INTO skift_schema (operator_id, skift_typ, datum) VALUES
-- Måndag
(1, 'FM', @monday),
(2, 'FM', @monday),
(3, 'FM', @monday),
(4, 'EM', @monday),
(5, 'EM', @monday),
(6, 'EM', @monday),
(7, 'NATT', @monday),
(8, 'NATT', @monday),
-- Tisdag
(1, 'FM', DATE_ADD(@monday, INTERVAL 1 DAY)),
(2, 'FM', DATE_ADD(@monday, INTERVAL 1 DAY)),
(3, 'FM', DATE_ADD(@monday, INTERVAL 1 DAY)),
(4, 'EM', DATE_ADD(@monday, INTERVAL 1 DAY)),
(5, 'EM', DATE_ADD(@monday, INTERVAL 1 DAY)),
(7, 'NATT', DATE_ADD(@monday, INTERVAL 1 DAY)),
(8, 'NATT', DATE_ADD(@monday, INTERVAL 1 DAY)),
-- Onsdag
(1, 'FM', DATE_ADD(@monday, INTERVAL 2 DAY)),
(3, 'FM', DATE_ADD(@monday, INTERVAL 2 DAY)),
(4, 'EM', DATE_ADD(@monday, INTERVAL 2 DAY)),
(5, 'EM', DATE_ADD(@monday, INTERVAL 2 DAY)),
(6, 'EM', DATE_ADD(@monday, INTERVAL 2 DAY)),
(7, 'NATT', DATE_ADD(@monday, INTERVAL 2 DAY)),
-- Torsdag
(2, 'FM', DATE_ADD(@monday, INTERVAL 3 DAY)),
(3, 'FM', DATE_ADD(@monday, INTERVAL 3 DAY)),
(4, 'EM', DATE_ADD(@monday, INTERVAL 3 DAY)),
(5, 'EM', DATE_ADD(@monday, INTERVAL 3 DAY)),
(6, 'EM', DATE_ADD(@monday, INTERVAL 3 DAY)),
(7, 'NATT', DATE_ADD(@monday, INTERVAL 3 DAY)),
(8, 'NATT', DATE_ADD(@monday, INTERVAL 3 DAY)),
-- Fredag
(1, 'FM', DATE_ADD(@monday, INTERVAL 4 DAY)),
(2, 'FM', DATE_ADD(@monday, INTERVAL 4 DAY)),
(3, 'FM', DATE_ADD(@monday, INTERVAL 4 DAY)),
(4, 'EM', DATE_ADD(@monday, INTERVAL 4 DAY)),
(5, 'EM', DATE_ADD(@monday, INTERVAL 4 DAY)),
(8, 'NATT', DATE_ADD(@monday, INTERVAL 4 DAY));

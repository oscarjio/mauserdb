-- Migration: service_intervals
-- Prediktivt underhåll baserat på IBC-volym (körningsbaserat serviceintervall)

CREATE TABLE IF NOT EXISTS service_intervals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    maskin_namn VARCHAR(100) NOT NULL,
    intervall_ibc INT NOT NULL DEFAULT 5000,
    senaste_service_datum DATETIME NULL,
    senaste_service_ibc INT NOT NULL DEFAULT 0,
    skapad DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    uppdaterad DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default-rad
INSERT INTO service_intervals (maskin_namn, intervall_ibc, senaste_service_datum, senaste_service_ibc)
VALUES ('Rebotling-linje 1', 5000, NOW(), 0);

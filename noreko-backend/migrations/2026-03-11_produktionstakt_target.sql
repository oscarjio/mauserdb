-- Måltalstabell för produktionstakt (IBC/h)
-- Används av ProduktionsTaktController för att jämföra aktuell takt mot mål.
CREATE TABLE IF NOT EXISTS produktionstakt_target (
    id INT AUTO_INCREMENT PRIMARY KEY,
    target_ibc_per_hour DECIMAL(6,1) NOT NULL,
    set_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sätt ett default-måltal
INSERT INTO produktionstakt_target (target_ibc_per_hour, set_by)
VALUES (12.0, NULL);

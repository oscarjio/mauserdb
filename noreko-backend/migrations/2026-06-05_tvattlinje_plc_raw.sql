CREATE TABLE IF NOT EXISTS tvattlinje_plc_raw (
  id BIGINT NOT NULL AUTO_INCREMENT,
  datum DATETIME(3) NOT NULL DEFAULT NOW(3),
  event_type ENUM('cycle','skiftrapport','running','rast','driftstopp','command') NOT NULL,
  shelly_count BIGINT NULL,
  registers JSON NULL COMMENT 'D4000-D4015 raw values from PLC',
  modbus_ok TINYINT(1) NOT NULL DEFAULT 0,
  http_payload VARCHAR(500) NULL,
  PRIMARY KEY (id),
  INDEX idx_datum (datum),
  INDEX idx_event_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS rebotling_plc_raw (
  id BIGINT NOT NULL AUTO_INCREMENT,
  datum DATETIME(3) NOT NULL DEFAULT NOW(3),
  event_type ENUM('cycle','skiftrapport','running','rast','driftstopp','command') NOT NULL,
  shelly_count BIGINT NULL,
  registers JSON NULL COMMENT 'D4000-D4015 raw values from PLC',
  modbus_ok TINYINT(1) NOT NULL DEFAULT 0,
  http_payload VARCHAR(500) NULL,
  PRIMARY KEY (id),
  INDEX idx_datum (datum),
  INDEX idx_event_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

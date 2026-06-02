CREATE TABLE IF NOT EXISTS tvattlinje_driftstopp (
  id int(11) NOT NULL AUTO_INCREMENT,
  datum datetime NOT NULL DEFAULT current_timestamp(),
  driftstopp_status tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_datum (datum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

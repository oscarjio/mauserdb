-- Flerdagars-skiftrapport: period-attributering per dag
-- Rapport skickad sent kan täcka flera kalenderdagar (tex fredag kväll + lördag morgon).
-- period_start/period_end pekar på verklig aktivitetsperiod, flerdagars=1 vid dygnsgräns.
-- tvattlinje_skiftrapport_daglig fördelar IBC/drifttid per dag från kumulativa PLC-räknare.

ALTER TABLE tvattlinje_skiftrapport
  ADD COLUMN period_start DATETIME DEFAULT NULL AFTER datum,
  ADD COLUMN period_end   DATETIME DEFAULT NULL AFTER period_start,
  ADD COLUMN flerdagars   TINYINT(1) NOT NULL DEFAULT 0 AFTER period_end,
  ADD COLUMN antal_dagar  INT NOT NULL DEFAULT 1 AFTER flerdagars;

CREATE TABLE IF NOT EXISTS tvattlinje_skiftrapport_daglig (
  id               INT NOT NULL AUTO_INCREMENT,
  skiftrapport_id  INT NOT NULL,
  dag              DATE NOT NULL,
  antal_ok         INT NOT NULL DEFAULT 0,
  antal_ej_ok      INT NOT NULL DEFAULT 0,
  omtvaatt         INT NOT NULL DEFAULT 0,
  drifttid_min     INT NOT NULL DEFAULT 0,
  rast_min         INT NOT NULL DEFAULT 0,
  kalla            ENUM('plc_event','pro_rata') NOT NULL DEFAULT 'plc_event',
  PRIMARY KEY (id),
  UNIQUE KEY uq_rapport_dag (skiftrapport_id, dag),
  KEY idx_dag (dag),
  KEY idx_skiftrapport_id (skiftrapport_id),
  CONSTRAINT fk_tvattlinje_daglig_rapport
    FOREIGN KEY (skiftrapport_id) REFERENCES tvattlinje_skiftrapport(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

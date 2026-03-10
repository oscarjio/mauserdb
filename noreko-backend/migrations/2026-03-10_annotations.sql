-- Annotationer för statistikgrafer (driftstopp, helgdagar, händelser)
CREATE TABLE IF NOT EXISTS `rebotling_annotations` (
    `id`          INT          NOT NULL AUTO_INCREMENT,
    `datum`       DATE         NOT NULL,
    `typ`         ENUM('driftstopp','helgdag','handelse','ovrigt') NOT NULL DEFAULT 'ovrigt',
    `titel`       VARCHAR(120) NOT NULL,
    `beskrivning` TEXT         NULL DEFAULT NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_datum` (`datum`),
    INDEX `idx_typ`   (`typ`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

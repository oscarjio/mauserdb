-- Gamification: poängsystem, badges och milstolpar för operatörer
-- Denna migration skapar tabeller för att persistent lagra badges och poäng.
-- Kontrollern beräknar poäng dynamiskt från befintliga tabeller,
-- men badges sparas för historik.

CREATE TABLE IF NOT EXISTS gamification_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_id VARCHAR(50) NOT NULL COMMENT 'centurion, perfektionist, maratonlopare, stoppjagare, teamspelare',
    tilldelad_datum DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_badge_id (badge_id),
    UNIQUE KEY uq_user_badge_date (user_id, badge_id, tilldelad_datum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Sparade badges/utmarkelser for gamification-systemet';

CREATE TABLE IF NOT EXISTS gamification_milstolpar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    milstolpe_namn VARCHAR(50) NOT NULL COMMENT 'Nyborjare, Erfaren, Expert, Master, Legend, Mytisk',
    uppnadd_datum DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    UNIQUE KEY uq_user_milstolpe (user_id, milstolpe_namn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Uppnadda milstolpar for operatorer';

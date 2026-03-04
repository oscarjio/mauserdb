CREATE TABLE IF NOT EXISTS produktionsmal_undantag (
  datum DATE PRIMARY KEY,
  justerat_mal INT NOT NULL,
  orsak VARCHAR(255) NULL COMMENT 'Varför justerat mål (underhåll, helgdag, reducerad bemanning etc.)',
  skapad_av INT NULL,
  skapad_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  uppdaterad_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

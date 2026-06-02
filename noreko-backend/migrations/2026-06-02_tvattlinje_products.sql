-- Migration: 2026-06-02_tvattlinje_products.sql
-- Skapar produkttabell för tvättlinjen

CREATE TABLE IF NOT EXISTS tvattlinje_products (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL,
  cycle_time_minutes decimal(8,2) DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT current_timestamp(),
  updated_at datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Migration: 2026-03-04_bonus_payouts.sql
-- Skapa tabell för bonusutbetalningshistorik

CREATE TABLE IF NOT EXISTS bonus_payouts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  op_id INT NOT NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  amount_sek DECIMAL(10,2) NOT NULL,
  ibc_count INT DEFAULT 0,
  avg_ibc_per_h DECIMAL(6,2) DEFAULT 0,
  avg_quality_pct DECIMAL(5,2) DEFAULT 0,
  notes TEXT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_op_id (op_id),
  INDEX idx_period (period_start)
);

-- Migration: 2026-03-11_malhistorik.sql
-- Skapar index på rebotling_goal_history för snabbare målhistorik-queries.
-- Tabellen skapades i 2026-03-04_goal_history.sql men saknar index på changed_at ensamt.

-- Index på changed_at för sortering (goal_type+changed_at finns redan)
ALTER TABLE rebotling_goal_history
    ADD INDEX IF NOT EXISTS idx_changed_at (changed_at);

-- Index på changed_by för filtrering per användare
ALTER TABLE rebotling_goal_history
    ADD INDEX IF NOT EXISTS idx_changed_by (changed_by);

-- Se till att rebotling_ibc har index på created_at för 7-dagarsperiod-queries
ALTER TABLE rebotling_ibc
    ADD INDEX IF NOT EXISTS idx_created_at_date (created_at);

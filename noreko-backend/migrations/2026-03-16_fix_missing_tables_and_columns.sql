-- ============================================================================
-- Migration: 2026-03-16_fix_missing_tables_and_columns.sql
-- Skapar saknade tabeller och kolumner som refereras i BonusAdminController,
-- BonusController m.fl. men aldrig skapats i en migration.
-- Alla satser är idempotenta (IF NOT EXISTS / ADD COLUMN IF NOT EXISTS).
-- ============================================================================

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. bonus_config — central konfigurationstabell för bonussystemet
--    Refereras i: BonusAdminController (getConfig, updateWeights, setTargets,
--    setWeeklyGoal, saveSimulatorParams, operatorForecast)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bonus_config (
    id INT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,

    -- Viktningar per produkttyp (JSON: {"eff":0.30,"prod":0.30,"qual":0.40})
    weights_foodgrade JSON DEFAULT NULL,
    weights_nonun JSON DEFAULT NULL,
    weights_tvattade JSON DEFAULT NULL,

    -- Produktivitetsmål (IBC/timme)
    productivity_target_foodgrade DECIMAL(6,2) DEFAULT 12.00,
    productivity_target_nonun DECIMAL(6,2) DEFAULT 20.00,
    productivity_target_tvattade DECIMAL(6,2) DEFAULT 15.00,

    -- Tier-multipliers (JSON-array med threshold/multiplier/name)
    tier_multipliers JSON DEFAULT NULL,

    -- Maxtak för bonus (SEK)
    max_bonus INT UNSIGNED DEFAULT 200,

    -- Veckobonusmål (poäng)
    weekly_bonus_goal DECIMAL(8,2) DEFAULT NULL,

    -- Team- och säkerhetsbonus
    team_bonus_enabled TINYINT(1) NOT NULL DEFAULT 0,
    safety_bonus_enabled TINYINT(1) NOT NULL DEFAULT 0,

    -- Audit
    updated_by VARCHAR(100) DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. bonus_audit_log — audit trail för admin-ändringar
--    Refereras i: BonusAdminController::logAudit()
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bonus_audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id INT UNSIGNED DEFAULT NULL,
    old_value JSON DEFAULT NULL,
    new_value JSON DEFAULT NULL,
    user VARCHAR(100) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_action (action),
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. bonus_level_amounts — bonusbelopp per nivå (brons/silver/guld/platina)
--    Refereras i: BonusAdminController (getAmounts, setAmounts)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bonus_level_amounts (
    level_name VARCHAR(20) NOT NULL PRIMARY KEY,
    amount_sek DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    updated_by VARCHAR(100) DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────────────────
-- 4. bonus_payouts — utbetalningsposter per operatör och period
--    Refereras i: BonusAdminController (listPayouts, recordPayout,
--    updatePayoutStatus, deletePayout)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bonus_payouts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    op_id INT UNSIGNED NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    period_label VARCHAR(100) DEFAULT NULL,
    bonus_level VARCHAR(20) DEFAULT NULL,
    status ENUM('pending','approved','paid') NOT NULL DEFAULT 'pending',
    amount_sek DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ibc_count INT UNSIGNED DEFAULT 0,
    avg_ibc_per_h DECIMAL(8,2) DEFAULT NULL,
    avg_quality_pct DECIMAL(5,2) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    approved_by INT UNSIGNED DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    paid_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payouts_op (op_id),
    INDEX idx_payouts_period (period_start, period_end),
    INDEX idx_payouts_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────────────────
-- 5. Kolumner i rebotling_ibc som saknas (KPI + bonusfält)
--    Refereras i: BonusController, BonusAdminController, RebotlingController
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE rebotling_ibc ADD COLUMN IF NOT EXISTS effektivitet DECIMAL(5,2) DEFAULT NULL;
ALTER TABLE rebotling_ibc ADD COLUMN IF NOT EXISTS produktivitet DECIMAL(5,2) DEFAULT NULL;
ALTER TABLE rebotling_ibc ADD COLUMN IF NOT EXISTS kvalitet DECIMAL(5,2) DEFAULT NULL;
ALTER TABLE rebotling_ibc ADD COLUMN IF NOT EXISTS bonus_poang DECIMAL(8,2) DEFAULT NULL;

-- Bonus-godkännande (refereras i BonusAdminController::approveBonuses)
ALTER TABLE rebotling_ibc ADD COLUMN IF NOT EXISTS bonus_approved TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE rebotling_ibc ADD COLUMN IF NOT EXISTS bonus_approved_by VARCHAR(100) DEFAULT NULL;
ALTER TABLE rebotling_ibc ADD COLUMN IF NOT EXISTS bonus_approved_at DATETIME DEFAULT NULL;

-- created_at (om det saknas)
ALTER TABLE rebotling_ibc ADD COLUMN IF NOT EXISTS created_at DATETIME DEFAULT CURRENT_TIMESTAMP;

-- ─────────────────────────────────────────────────────────────────────────────
-- 6. updated_at i rebotling_skiftrapport
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE rebotling_skiftrapport ADD COLUMN IF NOT EXISTS updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

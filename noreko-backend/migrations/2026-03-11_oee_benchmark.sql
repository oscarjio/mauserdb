-- ============================================================
-- Migration: 2026-03-11_oee_benchmark.sql
-- OEE Benchmark — index-optimering for rebotling_ibc och rebotling_onoff
-- Inga nya tabeller behövs; OEE beräknas direkt från befintliga tabeller.
-- ============================================================

-- Index på rebotling_ibc.datum (DATE-del) för snabba period-filter
-- Lägg bara till om det inte redan finns
SET @sql = (
    SELECT IF(
        (SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'rebotling_ibc'
           AND INDEX_NAME   = 'idx_rebotling_ibc_datum') = 0,
        'ALTER TABLE rebotling_ibc ADD INDEX idx_rebotling_ibc_datum (datum)',
        'SELECT 1 -- index idx_rebotling_ibc_datum redan finns'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index på rebotling_ibc.ok för snabb COUNT av godkända IBC
SET @sql2 = (
    SELECT IF(
        (SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'rebotling_ibc'
           AND INDEX_NAME   = 'idx_rebotling_ibc_datum_ok') = 0,
        'ALTER TABLE rebotling_ibc ADD INDEX idx_rebotling_ibc_datum_ok (datum, ok)',
        'SELECT 1 -- index idx_rebotling_ibc_datum_ok redan finns'
    )
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- Index på rebotling_onoff.start_time / stop_time för tidsintervall-filter
SET @sql3 = (
    SELECT IF(
        (SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'rebotling_onoff'
           AND INDEX_NAME   = 'idx_rebotling_onoff_start') = 0,
        'ALTER TABLE rebotling_onoff ADD INDEX idx_rebotling_onoff_start (start_time)',
        'SELECT 1 -- index idx_rebotling_onoff_start redan finns'
    )
);
PREPARE stmt3 FROM @sql3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

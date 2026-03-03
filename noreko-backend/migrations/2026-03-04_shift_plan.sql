-- Migration: 2026-03-04_shift_plan.sql
-- Skapar tabell för skiftplanering (vilka operatörer arbetar vilket skift/dag)

CREATE TABLE IF NOT EXISTS shift_plan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    datum DATE NOT NULL,
    skift_nr INT NOT NULL COMMENT '1=morgon (06-14), 2=eftermiddag (14-22), 3=natt (22-06)',
    op_number INT NOT NULL,
    note VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_shift_op (datum, skift_nr, op_number),
    INDEX idx_datum (datum),
    INDEX idx_op (op_number)
);

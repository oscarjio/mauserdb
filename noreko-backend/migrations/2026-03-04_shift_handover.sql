-- Migration: 2026-03-04_shift_handover.sql
-- Digital skiftöverlämning för Rebotling
-- Operatörer loggar anteckningar vid skiftslut som nästa skift ser direkt.

CREATE TABLE IF NOT EXISTS shift_handover (
    id INT AUTO_INCREMENT PRIMARY KEY,
    datum DATE NOT NULL,
    skift_nr INT NOT NULL COMMENT '1=morgon, 2=eftermiddag, 3=natt',
    note TEXT NOT NULL,
    priority ENUM('normal', 'important', 'urgent') DEFAULT 'normal',
    op_number INT DEFAULT NULL,
    op_name VARCHAR(100) DEFAULT NULL,
    created_by_user_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_datum (datum),
    INDEX idx_skift (datum, skift_nr)
);

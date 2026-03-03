-- Operatörscertifieringar: spårar vilka operatörer som är godkända per linje
CREATE TABLE IF NOT EXISTS operator_certifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    op_number INT NOT NULL,
    line VARCHAR(50) NOT NULL COMMENT 'rebotling|tvattlinje|saglinje|klassificeringslinje',
    certified_by INT DEFAULT NULL COMMENT 'user_id som utfärdade',
    certified_date DATE NOT NULL,
    expires_date DATE DEFAULT NULL COMMENT 'NULL = ingen utgång',
    notes VARCHAR(500) DEFAULT NULL,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_op (op_number),
    INDEX idx_line (line),
    INDEX idx_expires (expires_date)
);

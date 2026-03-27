-- Session #358: Lägger till index på kundordrar för bättre query-prestanda
-- LeveransplaneringController frågar ofta på status + leveransdatum

ALTER TABLE kundordrar ADD INDEX idx_status (status);
ALTER TABLE kundordrar ADD INDEX idx_onskat_leverans (onskat_leveransdatum);

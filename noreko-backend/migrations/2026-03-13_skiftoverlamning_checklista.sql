-- Migration: 2026-03-13_skiftoverlamning_checklista.sql
-- Utökar skiftoverlamning_logg med checklista-JSON och mål nästa skift

ALTER TABLE skiftoverlamning_logg
  ADD COLUMN checklista_json JSON DEFAULT NULL COMMENT 'Checklista-status som JSON-array [{key, label, checked}]' AFTER kommentar,
  ADD COLUMN mal_nasta_skift TEXT DEFAULT NULL COMMENT 'Produktionsmål och fokusområden för nästa skift' AFTER checklista_json,
  ADD COLUMN allvarlighetsgrad ENUM('lag','medel','hog','kritisk') DEFAULT 'medel' COMMENT 'Allvarlighetsgrad för pågående problem' AFTER har_pagaende_problem;

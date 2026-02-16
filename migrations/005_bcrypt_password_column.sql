-- Migration: Bcrypt Password Column
-- Datum: 2026-02-16
-- Beskrivning: Utöka password-kolumnen för att stödja bcrypt-hashar (60 tecken)
-- Bakgrund: Tidigare använde systemet sha1(md5()) som ger 40 tecken.
--           bcrypt (password_hash) ger 60 tecken. VARCHAR(255) är standard.

USE mauserdb;

ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NOT NULL;

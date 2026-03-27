-- Session #371: Ta bort redundant index idx_datum pa rebotling_ibc
-- idx_datum och idx_rebotling_ibc_datum indexerar bada enbart kolumnen `datum`.
-- idx_rebotling_ibc_datum behalls (namnkonvention foljer tabellprefix).
-- Verifierat med SHOW INDEX FROM rebotling_ibc 2026-03-27.
--
-- KORS MANUELLT mot prod efter granskning:
--   mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb < denna_fil.sql

ALTER TABLE `rebotling_ibc` DROP INDEX `idx_datum`;

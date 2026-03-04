-- Prediktivt underhåll körningsbaserat — serviceintervall baserat på IBC-volym
-- rebotling_settings är en key-value-tabell: INSERT IGNORE för defaultvärden

INSERT IGNORE INTO rebotling_settings (`key`, `value`) VALUES
  ('service_interval_ibc',    '5000'),
  ('last_service_ibc_total',  '0'),
  ('last_service_at',         NULL),
  ('last_service_note',       NULL);

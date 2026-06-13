-- Feature flags for operator ranking / scoring pages
-- min_role 'admin'      = operators (role=user) NEVER see these
-- min_role 'developer'  = hidden for all except developer accounts

INSERT INTO feature_flags (feature_key, label, category, min_role, enabled) VALUES
  ('tvattlinje/operator-ranking',    'Operatörsranking IBC/h (Tvättlinje)',     'tvattlinje', 'admin',     1),
  ('tvattlinje/operator-scores',     'IBC/h Scores (Tvättlinje)',               'tvattlinje', 'admin',     1),
  ('tvattlinje/operator-prestation', 'Operatörsprestation hub (Tvättlinje)',    'tvattlinje', 'admin',     1),
  ('tvattlinje/operator-topplista',  'Operatörstopplista (legacy)',             'tvattlinje', 'admin',     1),
  ('tvattlinje/operator-poang',      'Poängfördelning (legacy)',                'tvattlinje', 'developer', 1),
  ('bemanning-optimerare',           'Bemanning-optimerare',                    'verktyg',    'admin',     1),
  ('rebotling/operator-ranking',     'Operatörsranking Rebotling',              'rebotling',  'admin',     1),
  ('rebotling/operator-scores',      'IBC/h Scores (Rebotling)',                'rebotling',  'admin',     1)
ON DUPLICATE KEY UPDATE min_role = VALUES(min_role), label = VALUES(label);

UPDATE users SET role = 'developer' WHERE email = 'oscar.niklasson@jio.se';

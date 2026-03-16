-- Fix: Ändra alla developer-only feature flags till admin
-- Developer ser fortfarande allt (developer >= admin i rollhierarkin)
-- Men nu kan admin-användare också se dessa sidor

UPDATE feature_flags SET min_role = 'admin' WHERE min_role = 'developer';

-- Feature flags admin-sida ska vara developer-only
UPDATE feature_flags SET min_role = 'developer' WHERE feature_key = 'admin/feature-flags';

-- Ändrar runtime_today från int(11) till decimal(10,2) i tvattlinje_onoff
-- Bugfix: PHP sparade float-värden men kolumnen trunkerade till heltal (sub-minutprecision förlorades)
ALTER TABLE tvattlinje_onoff
  MODIFY COLUMN runtime_today decimal(10,2) NOT NULL DEFAULT 0.00;

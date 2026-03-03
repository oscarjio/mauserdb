-- 2026-03-03: Lägg till weekly_bonus_goal i bonus_config
-- Används av admin för att sätta veckobonusmål (poäng) och av operatörsprognos

ALTER TABLE bonus_config
    ADD COLUMN IF NOT EXISTS weekly_bonus_goal DECIMAL(6,2) NOT NULL DEFAULT 80.00
        COMMENT 'Målpoäng per vecka för bonusberäkning';

-- Uppdatera rad 1 om den finns
INSERT INTO bonus_config (id, weekly_bonus_goal)
VALUES (1, 80.00)
ON DUPLICATE KEY UPDATE weekly_bonus_goal = IF(weekly_bonus_goal = 0, 80.00, weekly_bonus_goal);

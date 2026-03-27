-- Session #359: Performance-index for OEE-trendanalys och alarm-historik
-- rebotling_onoff: queries filtrerar pa datum-range + running-kolumn
-- stoppage_log: queries filtrerar pa start_time + duration_minutes > 30

-- rebotling_onoff: covering index for datum-range + running lookup
CREATE INDEX IF NOT EXISTS idx_onoff_datum_running
ON rebotling_onoff (datum, running);

-- stoppage_log: composite index for start_time + duration_minutes (alarm-historik getLangaStopp)
CREATE INDEX IF NOT EXISTS idx_stoppage_start_duration
ON stoppage_log (start_time, duration_minutes);

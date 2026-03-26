-- Session #350: Composite index for rebotling_ibc to speed up date+skiftraknare queries
-- Used by HistoriskSammanfattningController, SkiftjamforelseController, etc.
CREATE INDEX IF NOT EXISTS idx_rebotling_ibc_datum_skift ON rebotling_ibc (datum, skiftraknare);

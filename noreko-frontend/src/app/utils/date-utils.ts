/**
 * Timezone-safe date utilities for MauserDB.
 *
 * Problem: `new Date('2026-03-09')` (date-only ISO string) is parsed as
 * UTC midnight by the spec.  In CET/CEST (UTC+1/+2) that becomes the
 * PREVIOUS day at 23:00/22:00.  Similarly `new Date().toISOString()` returns
 * UTC, so `.slice(0,10)` can yield tomorrow's date after 23:00 CET.
 *
 * These helpers ensure we always work in local time.
 */

/** Return today's date as 'YYYY-MM-DD' in the local timezone. */
export function localToday(): string {
  return localDateStr(new Date());
}

/** Format a Date object as 'YYYY-MM-DD' using local timezone. */
export function localDateStr(d: Date): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${dd}`;
}

/**
 * Parse a date string safely in local time.
 * If the string is a date-only format (YYYY-MM-DD), appends 'T00:00:00'
 * to force local-time parsing instead of UTC.
 * Strings that already contain 'T' or a time component are passed through.
 */
export function parseLocalDate(s: string): Date {
  if (!s) return new Date(NaN);
  // Date-only: exactly 'YYYY-MM-DD' (10 chars, no T)
  if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
    return new Date(s + 'T00:00:00');
  }
  return new Date(s);
}

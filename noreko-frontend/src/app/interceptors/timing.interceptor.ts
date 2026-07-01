import { HttpInterceptorFn, HttpResponse } from '@angular/common/http';
import { tap } from 'rxjs/operators';

/**
 * Mäter svarstid för alla /noreko-backend/-anrop, loggar till konsolen och
 * samlar i window.__apiTiming. Kör window.__apiTimingSummary() i konsolen för
 * en tabell grupperad per endpoint.
 */
export const timingInterceptor: HttpInterceptorFn = (req, next) => {
  if (!req.url.includes('/noreko-backend/')) {
    return next(req);
  }
  const start = performance.now();
  return next(req).pipe(
    tap(event => {
      if (event instanceof HttpResponse) {
        const dur = Math.round((performance.now() - start) * 10) / 10;
        const srvTiming = event.headers.get('Server-Timing') || '';
        const src = event.headers.get('X-Data-Source') || 'local';

        // Korta ner url till action/run
        let shortUrl = req.url;
        try {
          const u = new URL(req.url, window.location.origin);
          const action = u.searchParams.get('action') || '';
          const run = u.searchParams.get('run') || '';
          shortUrl = action + (run ? '/' + run : '') || req.url;
        } catch { /* behåll full url */ }

        const line = `[API-timing] ${req.method} ${shortUrl} - ${dur}ms (${src})`
          + (srvTiming ? ` | srv: ${srvTiming}` : '');
        if (dur >= 800) { console.warn(line); } else { console.debug(line); }

        const w = window as any;
        if (!w.__apiTiming) { w.__apiTiming = []; }
        w.__apiTiming.push({ ts: Date.now(), method: req.method, url: shortUrl, ms: dur, src });

        if (!w.__apiTimingSummary) {
          w.__apiTimingSummary = () => {
            const groups: Record<string, { count: number; totalMs: number; maxMs: number }> = {};
            for (const e of (w.__apiTiming || [])) {
              const key = `${e.method} ${e.url}`;
              if (!groups[key]) { groups[key] = { count: 0, totalMs: 0, maxMs: 0 }; }
              groups[key].count++;
              groups[key].totalMs += e.ms;
              groups[key].maxMs = Math.max(groups[key].maxMs, e.ms);
            }
            const rows = Object.entries(groups).map(([k, v]) => ({
              endpoint: k,
              count: v.count,
              totalMs: Math.round(v.totalMs * 10) / 10,
              avgMs: Math.round((v.totalMs / v.count) * 10) / 10,
              maxMs: Math.round(v.maxMs * 10) / 10,
            })).sort((a, b) => b.totalMs - a.totalMs);
            console.table(rows);
          };
        }
      }
    })
  );
};

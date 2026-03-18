# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-18)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #165):
- [x] **PHP input length/boundary audit** — 15 buggar fixade: VARCHAR-overflow, negativa tal, array-storlek (Worker A)
- [x] **PHP date/timezone consistency audit** — 0 buggar, redan konsekvent (Worker A)
- [x] **PHP logging completeness audit** — 6 buggar fixade: saknad error_log, sakerhetsloggning (Worker A)
- [x] **Angular HTTP retry/timeout audit** — 95 buggar fixade: retry(1) pa GET i 95 services (Worker B)
- [x] **Angular form validation audit** — 6 buggar fixade: saknade required, disabled submit (Worker B)

### Nasta buggjakt-items (session #166+):
- [ ] **PHP file upload validation audit** — MIME-type kontroll, filstorlek, path traversal
- [ ] **Angular memory leak deep audit** — chart-objekt, event listeners, window-events
- [ ] **PHP SQL query optimization audit** — N+1 queries, saknade index, onodiga JOINs
- [ ] **Angular error boundary audit** — saknade ErrorHandler, ohandlade promise-rejections
- [ ] **PHP CORS/security headers audit** — saknade Content-Security-Policy, X-Frame-Options

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

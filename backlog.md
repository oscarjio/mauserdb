# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-23)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #281):
- [ ] **PHP SQL subquery korrekthet** — Worker A — verifiera subqueries, NULL i NOT IN, kolumnreferenser
- [ ] **PHP array_key_exists vs isset** — Worker A — null-edge cases, $_GET/$_POST utan check
- [ ] **PHP error_log format konsistens (N-Z)** — Worker A — controllers N-Z, catch-blocks, kanslig data
- [ ] **Angular HTTP request cancellation** — Worker B — switchMap vs mergeMap, takeUntil vid navigation
- [ ] **Angular date/time rendering** — Worker B — timezone, DatePipe format, Date vs moment
- [ ] **Angular form validation consistency** — Worker B — saknade validators, felmeddelanden, submit-checks

### Nasta buggjakt-items (session #282+):
- [ ] **PHP mail/notification edge cases** — felhantering vid misslyckad e-post
- [ ] **PHP cron/scheduled tasks** — race conditions, timeout-hantering
- [ ] **Angular chart.js konfiguration** — felaktiga options, saknade defaults
- [ ] **Angular localStorage/sessionStorage** — quota exceeded, JSON parse errors

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

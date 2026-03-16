# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-16)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **Error response audit** — 2 PDOException-lackage fixade (Worker A #129)
- [x] **Loose comparisons** — 18 fixar i 16 controllers (Worker A #129)
- [x] **Chart.js memory leaks** — 109 instanser verifierade, alla OK (Worker B #129)
- [x] **HTTP error handling** — alla services har catchError, verifierat (Worker B #129)
- [x] **Subscription leaks** — alla har takeUntil/unsubscribe, verifierat (Worker B #129)
- [ ] **SQL edge cases** — LIMIT utan ORDER BY, NULL i aggregeringar, saknad index-audit
- [ ] **Template null-safety deep audit** — granska komplexa templates med nestade objekt
- [ ] **PHP return type consistency** — controllers som returnerar inkonsistenta JSON-strukturer
- [ ] **PHP error_log audit** — verifiera att alla catch-block loggar till error_log
- [ ] **Angular lazy loading** — verifiera att alla routes lazy-loadar korrekt

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

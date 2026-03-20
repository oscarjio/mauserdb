# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #208):
- [x] **PHP classes/ CSRF token audit** — komplett CSRF-mekanism implementerad (Worker A)
- [x] **PHP classes/ file inclusion audit** — rent, alla paths hardkodade (Worker A)
- [x] **Angular HTTP interceptor audit** — 14 redundanta timeout/catchError fixade (Worker B)
- [x] **Angular template strict null check** — rent, alla templates korrekt guardade (Worker B)

### Nasta buggjakt-items (session #209+):
- [ ] **PHP classes/ integer overflow audit** — intval/floatval pa stora tal, saknade bounds-checks
- [ ] **PHP classes/ password policy audit** — minlangd, komplexitet, account lockout
- [ ] **PHP classes/ SQL UNION/subquery audit** — felaktiga JOINs, saknade GROUP BY, N+1 i rapporter
- [ ] **Angular change detection audit** — onPush-strategier, zone.js-lackor, overflodiga renderings
- [ ] **PHP classes/ error logging audit** — saknade error_log(), inkonsekvent loggformat

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

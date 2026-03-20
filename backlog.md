# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #207):
- [x] **PHP classes/ SQL column name verification** — 4 buggar: felaktiga kolumnnamn i 4 controllers (Worker A)
- [x] **PHP classes/ session fixation audit** — rent, redan korrekt (Worker A)
- [x] **Angular pipe/transform audit** — 13 buggar: saknad sv-locale + operator-precedens (Worker B)
- [x] **Angular lazy loading + route preload audit** — rent, redan korrekt (Worker B)

### Nasta buggjakt-items (session #208+):
- [ ] **PHP classes/ CSRF token audit** — saknade CSRF-tokens pa state-changing endpoints
- [ ] **PHP classes/ file inclusion audit** — include/require med dynamiska paths, LFI-risker
- [ ] **Angular HTTP interceptor audit** — saknade interceptors for auth/error/retry
- [ ] **PHP classes/ integer overflow audit** — intval/floatval pa stora tal, saknade bounds-checks
- [ ] **Angular template strict null check** — ?. vs ! i templates, potentiella runtime-fel
- [ ] **PHP classes/ password policy audit** — minlangd, komplexitet, account lockout

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

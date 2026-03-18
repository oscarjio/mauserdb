# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-18)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **PHP session/cookie audit** — 0 buggar, allt korrekt (Worker A #162)
- [x] **Angular form validation audit** — 0 buggar, allt korrekt (Worker B #162)
- [x] **PHP file I/O audit** — 13 buggar fixade (Worker A #162)
- [x] **Angular HTTP retry/timeout audit** — 3 buggar fixade (Worker B #162)

### Nasta buggjakt-items (session #163+):
- [ ] **PHP numeric overflow audit** — intval pa stora tal, float-precision i berakningar
- [ ] **Angular memory leak audit** — detached DOM-noder, chart-instanser, event listeners
- [ ] **PHP SQL LIKE/REGEXP injection audit** — LIKE-wildcards, unsanitized REGEXP
- [ ] **Angular route guard audit** — saknade guards, felaktig redirect-logik
- [ ] **PHP error response consistency audit** — alla felfall ska ha korrekt HTTP-statuskod
- [ ] **Angular template accessibility audit** — aria-labels, keyboard navigation, focus management

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

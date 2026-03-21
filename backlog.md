# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-21)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #238):
- [ ] **PHP classes/ output buffering audit** — Worker A
- [ ] **PHP classes/ SQL prepared statement reuse audit** — Worker A
- [ ] **PHP classes/ header injection audit** — Worker A
- [ ] **Angular trackBy audit** — Worker B
- [ ] **Angular environment config audit** — Worker B

### Nasta buggjakt-items (session #239+):
- [ ] **PHP classes/ error response consistency audit** — varierande JSON-struktur vid fel
- [ ] **Angular pipe null-safety re-audit** — ny kod sedan session #215
- [ ] **PHP classes/ SQL GROUP BY correctness audit** — saknade kolumner i GROUP BY
- [ ] **Angular lazy-loaded route preload strategy audit** — verifiera preloadingStrategy
- [ ] **PHP classes/ array_map/array_filter callback audit** — felaktiga callbacks

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-17)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [ ] **PHP file I/O error handling** — file_get_contents/file_put_contents utan felkontroll
- [ ] **Angular lazy loading audit** — onodiga imports, SharedModule-bloat, bundle-storlekar
- [ ] **PHP response consistency audit** — granska att alla controllers returnerar konsekvent JSON-format (success/error/data)
- [ ] **Angular memory leak audit** — Chart.js destroy(), setInterval utan clearInterval, event listeners
- [ ] **PHP date/time edge cases** — midnight-boundary, DST-overganger, tomma datumstrangar
- [ ] **Angular HTTP retry/timeout audit** — saknade timeout(), retry-logik, offline-hantering

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

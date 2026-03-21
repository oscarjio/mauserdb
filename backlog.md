# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-21)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #231):
- [x] **PHP classes/ SQL transaction isolation audit** — rent (47 filer med skrivoperationer)
- [x] **PHP classes/ date/time edge case audit** — rent (110 filer)
- [x] **Angular lazy loading + bundle size audit** — rent (100+ routes med loadComponent)
- [x] **Angular form state consistency audit** — rent (14 formular)

### Nasta buggjakt-items (session #232+):
- [ ] **PHP classes/ input length/bounds validation audit** — saknad maxlangd-kontroll pa user input
- [ ] **PHP classes/ concurrent request race condition audit** — parallella requests som skriver till samma rad
- [ ] **Angular HTTP caching/stale data audit** — polling som visar gammal data, saknad cache-invalidering
- [ ] **PHP classes/ SQL LIMIT/OFFSET pagination audit** — saknade LIMIT pa stora SELECT-queries
- [ ] **Angular router navigation guard audit** — saknade guards, felaktig redirect-logik

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

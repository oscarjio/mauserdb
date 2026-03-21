# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-21)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #232):
- [x] **PHP classes/ input length/bounds validation audit** — 4 buggar (saknade ovre granser pa numeriska inputs)
- [x] **PHP classes/ concurrent request race condition audit** — rent
- [x] **Angular HTTP caching/stale data audit** — 1 bugg (refreshInterval anti-pattern)
- [x] **Angular router navigation guard audit** — 1 bugg (authGuard returnerade false istallet for UrlTree)

### Nasta buggjakt-items (session #233+):
- [ ] **PHP classes/ SQL LIMIT/OFFSET pagination audit** — saknade LIMIT pa stora SELECT-queries
- [ ] **PHP classes/ error response consistency audit** — inkonsekvent HTTP-statuskod och JSON-format
- [ ] **Angular service URL consistency audit** — hardkodade vs environment-baserade API-URLs
- [ ] **PHP classes/ CORS/cookie SameSite audit** — saknade SameSite-attribut pa cookies
- [ ] **Angular template accessibility audit** — saknade aria-live, tabindex, keyboard navigation

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #200):
- [x] **PHP classes/ logging + audit trail audit** — 7 buggar: saknad AuditLogger i 6 controllers (Worker A)
- [x] **PHP classes/ input sanitization audit** — 1 bugg: saknad strip_tags i NewsController category (Worker A)
- [x] **Angular template type-safety audit** — inga buggar, alla templates har korrekt null-safety (Worker B)
- [x] **PHP classes/ error response consistency audit** — 2 buggar: saknad error-nyckel i RebotlingController (Worker B)

### Nasta buggjakt-items (session #201+):
- [ ] **Angular lazy loading + bundle size audit** — onodigt stora bundles, saknad lazy loading
- [ ] **PHP classes/ caching audit** — saknad caching av tunga queries, redundanta DB-anrop
- [ ] **Angular form validation audit** — saknade validators, felaktiga error messages
- [ ] **PHP classes/ date/time edge case audit** — midnight, year boundaries, DST
- [ ] **Angular accessibility audit** — saknade aria-labels, keyboard navigation

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

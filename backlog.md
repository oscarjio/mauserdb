# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #201):
- [x] **PHP classes/ caching audit** — 3 buggar: redundant SHOW TABLES, N+1 x2, redundanta COUNT (Worker A)
- [x] **PHP classes/ date/time edge case audit** — 1 bugg: midnight edge case i NarvaroController (Worker A)
- [x] **Angular lazy loading + bundle size audit** — inga buggar, alla routes lazy-loadade (Worker B)
- [x] **Angular form validation audit** — 1 bugg: saknad losenordsvalidering i menu.ts (Worker B)

### Nasta buggjakt-items (session #202+):
- [ ] **Angular accessibility audit** — saknade aria-labels, keyboard navigation
- [ ] **PHP classes/ session/cookie security audit** — session fixation, cookie flags, CSRF
- [ ] **Angular HTTP retry/timeout audit** — saknade retries, for langa timeouts
- [ ] **PHP classes/ file path traversal audit** — saknad validering av filsokvagar
- [ ] **Angular memory leak audit** — detached DOM, event listeners, chart instances

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

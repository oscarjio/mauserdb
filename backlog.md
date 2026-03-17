# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-17)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **PHP race condition audit** — concurrent requests, locking, DB transactions (Worker A #144)
- [x] **PHP input length/boundary audit** — max-langder, overflow, edge cases (Worker A #144)
- [x] **Angular template null-safety audit** — saknade ?. och *ngIf guards (Worker B #144)
- [x] **Angular router guard audit** — saknade guards pa admin/auth-routes (Worker B #144)
- [ ] **Angular change detection audit** — OnPush-strategi (stor refactor, lag prioritet)
- [ ] **PHP error handling consistency** — granska try/catch-block, saknade rollback vid exception
- [ ] **Angular HTTP error display** — granska att alla API-anrop visar felmeddelanden for anvandaren
- [ ] **PHP session security audit** — session fixation, cookie flags, session regeneration
- [ ] **Angular memory profiling** — granska komponenter for DOM-lackor, event listener cleanup

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-18)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #164):
- [x] **PHP error response consistency audit** — 33 buggar fixade: saknade http_response_code i 5 controllers (Worker A)
- [x] **Angular template accessibility audit** — 15 buggar fixade: keyboard, ARIA, table scope (Worker B)
- [x] **PHP race condition audit** — 2 buggar fixade: RuntimeController + TvattlinjeController (Worker A)
- [x] **Angular lazy loading audit** — 0 buggar, alla routes korrekt lazy-loaded (Worker B)

### Nasta buggjakt-items (session #165+):
- [ ] **PHP input length/boundary audit** — max-langd pa textfalt, overflow i VARCHAR-kolumner
- [ ] **Angular HTTP retry/timeout audit** — saknade timeout, retry-logik, offline-hantering
- [ ] **PHP date/timezone consistency audit** — blandade date()-format, saknad timezone-config
- [ ] **Angular form validation audit** — saknad client-side validering, missmatch med backend
- [ ] **PHP logging completeness audit** — saknade error_log vid catch-block, ohandlade exceptions

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

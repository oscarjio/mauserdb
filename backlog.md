# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-23)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Nasta buggjakt-items (session #283+):
- [ ] **PHP pagination edge cases** — off-by-one, negativa sidor, extremt stora LIMIT
- [ ] **Angular HTTP retry/backoff** — exponential backoff, max retries, user feedback
- [ ] **PHP SQL UNION/INTERSECT** — kolumntyp-mismatch, sortering, NULL-hantering
- [ ] **Angular route guard race conditions** — auth-check timing, redirect loops
- [ ] **PHP file_put_contents atomicity** — concurrent writes, temp-fil + rename
- [ ] **Angular ngModel vs FormControl mixing** — inkonsekvent formularbindning, onodiga hybrid-monster

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

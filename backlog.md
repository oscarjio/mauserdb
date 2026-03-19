# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-19)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #192):
- [ ] **PHP SQL performance audit** — SELECT *, N+1 queries, saknade LIMIT (Worker A)
- [ ] **Angular form validation audit** — saknad validering, submit utan valid-check (Worker B)

### Nasta buggjakt-items (session #193+):
- [ ] **Angular memory profiling** — kora memory profiling pa tunga sidor
- [ ] **Angular lazy-loading optimization audit** — granska att alla routes lazy-loadar korrekt
- [ ] **PHP error logging consistency** — sakerstall att alla catch-block loggar korrekt
- [ ] **PHP date/time edge cases** — granska fler controllers for timezone/DST-problem
- [ ] **Angular HTTP error handling audit** — granska att alla HTTP-anrop hanterar fel korrekt

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

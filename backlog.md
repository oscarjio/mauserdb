# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-19)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #191):
- [x] **PHP input validation audit** — 8 buggar fixade (Worker A)
- [x] **Angular chart cleanup audit + memory leak hunting** — 0 buggar, kodbasen ren (Worker B)

### Nasta buggjakt-items (session #192+):
- [ ] **Angular memory profiling** — kora memory profiling pa tunga sidor
- [ ] **Angular lazy-loading optimization audit** — granska att alla routes lazy-loadar korrekt
- [ ] **PHP SQL performance audit** — granska fler controllers for SELECT *, N+1 queries, saknade index
- [ ] **PHP error logging consistency** — sakerstall att alla catch-block loggar korrekt
- [ ] **Angular form validation audit** — granska att alla formuler validerar input korrekt

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

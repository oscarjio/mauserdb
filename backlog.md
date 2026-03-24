# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-24)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Nasta buggjakt-items (session #286+):
- [ ] **PHP header/redirect consistency** — exit efter header('Location:'), saknade Content-Type
- [ ] **Angular Router navigation edge cases** — queryParams forlust vid navigate, fragment-hantering
- [ ] **PHP SQL date range queries** — BETWEEN med felaktigt format, timezone-mismatch
- [ ] **Angular HttpClient response handling** — felaktig responseType, saknad observe: 'response'
- [ ] **PHP password_hash/token timing** — timing attacks vid jamforelser, saknad hash_equals

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

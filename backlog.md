# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-25)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #314):
- [x] **PHP sendError() vs echo inkonsistens** — 13 fixade i 10 controllers
- [x] **PHP SQL N+1 queries** — rent (alla loopar begransade)
- [x] **PHP input sanitization audit** — rent (alla intval/whitelist/prepared)
- [x] **Angular NG8107/NG8102 template warnings** — 125 fixade i 23 filer
- [x] **Angular strict template type checking** — redan aktivt, bygget rent

### Nasta buggjakt-items (session #315+):
- [ ] **PHP exception handling consistency** — granska att alla catch-block loggar korrekt och returnerar ratt HTTP-status
- [ ] **Angular HTTP timeout audit** — verifiera att alla HTTP-anrop har timeout och catchError
- [ ] **PHP SQL kolumnnamn-verifiering** — jamfor query-kolumner mot faktiskt DB-schema
- [ ] **Angular component input validation** — granska att @Input-properties hanterar undefined/null korrekt
- [ ] **PHP date/time edge cases** — testa gransfall (midnatt, manadsskifte, skottaar)

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-25)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #315):
- [x] **PHP exception handling consistency A-M** — rent (407 catch, 556 PDO)
- [x] **Angular HTTP timeout audit** — rent (92 services)
- [x] **PHP SQL kolumnnamn-verifiering A-M** — rent (50 controllers)
- [x] **Angular component @Input validation** — rent (4 komponenter)
- [x] **Angular subscription/timer cleanup A-M** — rent (~60 komponenter)

### Nasta buggjakt-items (session #316+):
- [ ] **PHP exception handling consistency N-Z** — granska catch-block, loggning, HTTP-status
- [ ] **PHP SQL kolumnnamn-verifiering N-Z** — jamfor query-kolumner mot DB-schema
- [ ] **PHP date/time edge cases** — testa gransfall (midnatt, manadsskifte, skottaar)
- [ ] **Angular subscription/timer cleanup N-Z** — takeUntil-ordning, timer-rensning
- [ ] **PHP response Content-Type audit** — verifiera att alla endpoints satter korrekt Content-Type

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-24)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Nasta buggjakt-items (session #295+):
- [ ] **PHP array_key_exists vs isset** — controllers A-M (ny granskning med djupare fokus)
- [ ] **PHP preg_match return value** — controllers som inte kontrollerar false-retur fran preg_match
- [ ] **PHP array_merge i loopar** — performance-bugg, borde anvanda spread eller +=
- [ ] **Angular *ngIf + async pipe** — race conditions vid lazy-laddade data
- [ ] **Angular HttpParams encoding** — specialtecken i query params som inte encodas korrekt

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

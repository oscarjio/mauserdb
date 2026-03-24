# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-24)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Nasta buggjakt-items (session #296+):
- [ ] **PHP array_merge i loopar** — performance-bugg, borde anvanda spread eller +=
- [ ] **PHP str_replace/substr edge cases** — controllers A-Z, ovaentade typer eller tomma strangar
- [ ] **PHP date() / mktime() edge cases** — controllers A-Z, sommar/vintertid, skottdag
- [ ] **Angular ViewChild/ElementRef null** — komponenter som anvaender ViewChild utan null-check i ngAfterViewInit
- [ ] **Angular service circular dependency** — tjanster som injicerar varandra (ger runtime-error)

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-24)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #287):
- [x] **PHP array type coercion** — rent (alla in_array() har strict=true)
- [x] **Angular ngOnChanges edge cases** — rent (inga komponenter implementerar ngOnChanges)
- [x] **PHP file path traversal** — rent (inga filsokvagar fran user input)
- [x] **Angular RxJS operator ordering** — 127 buggar fixade (takeUntil sist i pipe, 38 filer)
- [x] **PHP SQL GROUP BY strictness** — 7 buggar fixade (3 controllers)

### Nasta buggjakt-items (session #288+):
- [ ] **PHP exception handling** — catch(Exception) som svaljer felmeddelanden utan loggning
- [ ] **Angular HTTP retry logic** — saknade retryWhen/retry pa kritiska GET-requests
- [ ] **PHP SQL LIKE injection** — saknad escaping av % och _ i LIKE-fragor fran user input
- [ ] **Angular trackBy i *ngFor** — stora listor utan trackBy-funktion (DOM-prestanda)
- [ ] **PHP integer overflow i pagination** — saknad validering av page/limit fran query params

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

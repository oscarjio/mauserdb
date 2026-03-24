# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-24)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #288):
- [ ] **PHP exception handling** — catch(Exception) som svaljer felmeddelanden utan loggning
- [ ] **Angular HTTP retry logic** — saknade retryWhen/retry pa kritiska GET-requests
- [ ] **PHP SQL LIKE injection** — saknad escaping av % och _ i LIKE-fragor fran user input
- [ ] **Angular trackBy i *ngFor** — stora listor utan trackBy-funktion (DOM-prestanda)
- [ ] **PHP integer overflow i pagination** — saknad validering av page/limit fran query params

### Nasta buggjakt-items (session #289+):
- [ ] **PHP mail() injektionsrisk** — kontrollera att mail-headers saniteras korrekt i alla controllers
- [ ] **Angular unsubscribed Observables** — granska alla manuella subscribe() utan takeUntil/unsubscribe
- [ ] **PHP date/time edge cases** — granska strtotimes och date() for DST/timezone-problem
- [ ] **Angular template null dereference** — granska komplexa template-uttryck for saknade ?. operatorer
- [ ] **PHP array bounds** — granska array-access utan isset/array_key_exists-guard

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

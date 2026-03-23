# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-23)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Nasta buggjakt-items (session #284+):
- [ ] **PHP array_map/array_filter callback** — felaktig callback-signatur, saknad null-check pa input
- [ ] **Angular ViewChild/ContentChild timing** — static vs dynamic, undefined i ngOnInit
- [ ] **PHP preg_match/preg_replace** — oforvantad NULL-input (PHP 8.1 deprecation), saknad delimiter
- [ ] **Angular async pipe vs manual subscribe** — dubbla subscriptions, memory leaks
- [ ] **PHP JSON encode/decode edge cases** — djupa strukturer, UTF-8 felhantering, max depth

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

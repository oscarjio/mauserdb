# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-22)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #239):
- [ ] **PHP classes/ error response consistency audit** — Worker A
- [ ] **PHP classes/ SQL GROUP BY correctness audit** — Worker A
- [ ] **PHP classes/ array_map/array_filter callback audit** — Worker A
- [ ] **Angular pipe null-safety re-audit** — Worker B
- [ ] **Angular lazy-loaded route preload strategy audit** — Worker B

### Nasta buggjakt-items (session #240+):
- [ ] **PHP classes/ SQL DISTINCT correctness audit** — onodiga eller saknade DISTINCT
- [ ] **Angular HTTP interceptor error normalization re-audit** — ny kod sedan #223
- [ ] **PHP classes/ PDO fetchAll memory audit** — stora resultat utan LIMIT
- [ ] **Angular form validation completeness audit** — saknade required/pattern
- [ ] **PHP classes/ file_get_contents error handling audit** — saknad felhantering vid HTTP-anrop

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

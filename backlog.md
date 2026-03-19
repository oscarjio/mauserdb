# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-19)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #193):
- [x] **PHP error logging + edge case audit** — 5 buggar (Worker A)
- [x] **Angular HTTP + null safety audit** — 4 buggar (Worker B)

### Nasta buggjakt-items (session #194+):
- [ ] **Angular memory profiling** — kora memory profiling pa tunga sidor
- [ ] **Angular lazy-loading optimization audit** — granska att alla routes lazy-loadar korrekt
- [ ] **PHP date/time edge cases** — granska fler controllers for timezone/DST-problem
- [ ] **PHP deprecated function audit** — granska for PHP 8.1+ deprecated patterns
- [ ] **Angular template strict mode audit** — granska templates for strictTemplates-varningar

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

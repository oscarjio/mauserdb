# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-19)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #194):
- [x] **PHP date/time + deprecated audit** — 4 buggar (Worker A)
- [x] **Angular strict template + lazy-loading audit** — 2 buggar (Worker B)

### Nasta buggjakt-items (session #195+):
- [ ] **Angular memory profiling** — kora memory profiling pa tunga sidor
- [ ] **PHP file I/O error handling** — granska controllers for saknad felhantering vid filoperationer
- [ ] **Angular HTTP retry logic audit** — granska att retry/backoff ar korrekt implementerat
- [ ] **PHP array key existence audit** — granska for saknade isset/array_key_exists-kontroller
- [ ] **Angular change detection audit** — granska for onodiga renderingar, saknad OnPush

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

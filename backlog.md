# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-23)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #262):
- [x] **PHP array key existence audit** — Worker A — rent
- [x] **PHP file upload validation audit** — Worker A — rent (ingen upload-funktionalitet)
- [x] **PHP regex pattern safety audit** — Worker A — rent
- [x] **Angular HTTP retry/error recovery audit** — Worker B — rent
- [x] **Angular form validation consistency audit** — Worker B — rent

### Nasta buggjakt-items (session #263+):
- [ ] **PHP date/string comparison audit** — strtotime edge cases, strcmp vs ===, locale-beroende
- [ ] **PHP PDO fetch mode consistency audit** — FETCH_ASSOC vs FETCH_BOTH, saknade fetchAll-kontroller
- [ ] **Angular pipe purity audit** — impure pipes i ngFor, saknade pure pipes for tunga berakningar
- [ ] **Angular change detection audit** — onPush-strategier, saknade markForCheck(), zoner
- [ ] **PHP SQL column alias consistency audit** — alias-namn som inte matchar frontend-forvantningar

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

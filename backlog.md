# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-22)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #244):
- [ ] **PHP date()/strtotime() input validation audit** — saknad validering av date-strangar fran user input (Worker A)
- [ ] **PHP json_encode/json_decode error handling audit** — saknad felhantering efter json_decode (Worker A)
- [ ] **PHP SQL LIKE wildcard escaping audit** — saknad escaping av % och _ i LIKE-clausuler (Worker A)
- [ ] **Angular HTTP unsubscribe audit** — HTTP-anrop som inte avbryts vid komponent-destroy (Worker B)
- [ ] **Angular form reset state audit** — formular som inte aterstalls korrekt vid navigation (Worker B)

### Nasta buggjakt-items (session #245+):
- [ ] **PHP error_log format consistency audit** — inkonsekvent format i error_log-meddelanden
- [ ] **PHP header() content-type audit** — saknade/felaktiga Content-Type headers
- [ ] **Angular pipe chain null-safety audit** — pipes som crashar pa null/undefined input
- [ ] **PHP array_key_exists vs isset consistency audit** — mixed usage med null-varden
- [ ] **Angular ngIf/else template reference audit** — saknade else-block vid loading states

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

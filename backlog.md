# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-22)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #243):
- [ ] **PHP str_replace/preg_replace edge case audit** — felaktiga regex, saknad preg_quote, tomma patterns (Worker A)
- [ ] **PHP array_merge i loopar performance audit** — array_merge() i foreach som kan vara +=/$result[] (Worker A)
- [ ] **PHP PDO fetch mode consistency audit** — blandade FETCH_ASSOC/FETCH_BOTH/FETCH_NUM (Worker A)
- [ ] **Angular trackBy audit** — *ngFor utan trackBy pa listor med HTTP-data (Worker B)
- [ ] **Angular template safe navigation audit** — saknade ?. i kedjeanrop pa nullable objekt (Worker B)

### Nasta buggjakt-items (session #244+):
- [ ] **PHP date()/strtotime() input validation audit** — saknad validering av date-strangar fran user input
- [ ] **PHP json_encode/json_decode error handling audit** — saknad felhantering efter json_decode
- [ ] **Angular HTTP unsubscribe audit** — HTTP-anrop som inte avbryts vid komponent-destroy
- [ ] **PHP SQL LIKE wildcard escaping audit** — saknad escaping av % och _ i LIKE-clausuler
- [ ] **Angular form reset state audit** — formuler som inte atersstalls korrekt vid navigation

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

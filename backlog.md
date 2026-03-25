# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-25)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #307):
- [ ] **PHP SQL GROUP_CONCAT overflow** — GROUP_CONCAT som kan overskrida max_length vid stora dataset (Worker A)
- [ ] **PHP error_log format consistency** — inkonsekvent loggformat forsvagar felsokbarhet (Worker A)
- [ ] **PHP SQL DATE() in WHERE** — DATE() pa kolumner forhindrar index-anvandning (Worker A)
- [ ] **Angular FormGroup reset** — formularvarden som inte aterstalls korrekt efter reset (Worker B)
- [ ] **Angular template function calls (forts)** — fler tunga funktionsanrop i templates (Worker B)
- [ ] **Angular service HTTP URL consistency** — inkonsistenta URL:er i services (Worker B)

### Nasta buggjakt-items (session #308+):
- [ ] **PHP array_map/array_filter callbacks** — felaktiga callbacks som tyst returnerar null
- [ ] **PHP PDO fetch mode consistency** — blandning av FETCH_ASSOC/FETCH_OBJ i controllers
- [ ] **Angular Chart.js update vs destroy** — grafer som uppdateras utan att forst destroya gammal instans
- [ ] **Angular HTTP retry pa POST/PUT/DELETE** — retry bor bara ske pa GET (idempotent)

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

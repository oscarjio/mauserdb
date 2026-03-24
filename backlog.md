# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-24)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #306):
- [ ] **PHP SQL subquery correlation** — korrelerade subqueries som refererar fel tabell/alias (Worker A)
- [ ] **PHP $_GET/$_POST default values** — saknade default-varden vid tomma parametrar (Worker A)
- [ ] **PHP SQL COUNT vs SUM confusion** — felaktig aggregering i rapporter (Worker A)
- [ ] **Angular router param unsubscribe** — route.params/queryParams subscriptions utan cleanup (Worker B)
- [ ] **Angular HTTP error message display** — felmeddelanden fran backend visas ej for anvandaren (Worker B)

### Nasta buggjakt-items (session #307+):
- [ ] **Angular template function calls** — tunga funktionsanrop i templates som kors vid varje CD
- [ ] **PHP SQL GROUP_CONCAT overflow** — GROUP_CONCAT som kan overskrida max_length vid stora dataset
- [ ] **PHP error_log format consistency** — inkonsekvent loggformat forsvagar felsokbarhet
- [ ] **Angular FormGroup reset** — formularvarden som inte aterst alls korrekt efter reset
- [ ] **PHP SQL DATE() in WHERE** — DATE() pa kolumner forhindrar index-anvandning (observation fran #301)

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

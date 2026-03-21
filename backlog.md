# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-21)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #228):
- [ ] **PHP classes/ SQL prepared statement audit (N-Z)** — strangkonkatenering i SQL, saknad parameterbindning (Worker A)
- [ ] **PHP classes/ type juggling audit (N-Z)** — == istallet for === i sakerhetsrelaterade jamforelser (Worker A)
- [ ] **PHP classes/ error response consistency audit** — inkonsistenta error JSON-format mellan controllers (Worker B)
- [ ] **Angular HTTP error message consistency audit** — felmeddelanden som inte visas for anvandaren (Worker B)

### Nasta buggjakt-items (session #229+):
- [ ] **PHP classes/ unused variable/dead code audit** — oanvanda variabler, naabar kod efter return
- [ ] **PHP classes/ file inclusion/require audit** — dynamiska include/require med user input
- [ ] **Angular template strict null-check audit** — ?. saknas pa potentiellt null-objekt i templates
- [ ] **PHP classes/ array_key_exists vs isset audit** — isset returnerar false for null-varden
- [ ] **Angular reactive forms validation audit** — saknade validators, felaktig felvisning

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

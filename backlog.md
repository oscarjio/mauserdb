# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #199):
- [x] **PHP classes/ SQL query performance audit** — 5 buggar: 2 N+1 queries, 3 saknade LIMIT (Worker A)
- [x] **PHP classes/ transaction audit** — inga buggar, alla transaktioner OK (Worker A)
- [x] **Angular HTTP error consistency audit** — 2 buggar: hardkodade API-URLer (Worker B)
- [x] **Angular routing guard audit** — inga buggar, alla 137 routes OK (Worker B)

### Nasta buggjakt-items (session #200+):
- [ ] **PHP classes/ logging + audit trail audit** — saknad loggning av kritiska operationer
- [ ] **Angular template type-safety audit** — any-typer, saknade null-checks i templates
- [ ] **PHP classes/ input sanitization audit** — htmlspecialchars, trim, type-casting
- [ ] **Angular lazy loading + bundle size audit** — onodigt stora bundles, saknad lazy loading
- [ ] **PHP classes/ error response consistency audit** — inkonsistenta HTTP-statuskoder, felmeddelanden

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

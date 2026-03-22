# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-22)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Nasta buggjakt-items (session #250+):
- [ ] **PHP mb_substr/mb_strlen consistency audit** — saknad multibyte-hantering
- [ ] **PHP array_unique/array_values audit** — saknad dedup i samlingar
- [ ] **Angular pipe chain null-safety audit** — kedjade pipes utan null-check
- [ ] **PHP header() Content-Type consistency audit** — saknad/felaktig content-type
- [ ] **Angular FormControl validators audit** — saknade/felaktiga validators
- [ ] **PHP static method side-effects audit** — statiska metoder med ovantade sidoeffekter
- [ ] **Angular route resolver error handling audit** — saknad felhantering i resolvers

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-19)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #183):
- [ ] **PHP header injection audit** — granska att inga HTTP-headers skapas fran anvandardata utan validering (Worker A)
- [ ] **PHP JSON response consistency** — granska att alla controllers returnerar enhetligt JSON-format (Worker A)
- [ ] **PHP error_log format audit** — granska att alla error_log anvander konsistent format med context (Worker A)
- [ ] **Angular lazy-loading verification** — granska att alla feature-moduler laddas lazy (Worker B)
- [ ] **Angular form accessibility audit** — granska att alla formularelement har labels och ARIA-attribut (Worker B)
- [ ] **Angular template null-safety audit** — granska null-safety och trackBy i templates (Worker B)

### Nasta buggjakt-items (session #184+):
- [ ] **PHP session timeout/regeneration audit** — granska att sessions regenereras korrekt och har timeout
- [ ] **PHP SQL string concatenation audit** — granska att inga queries byggs med string concat istallet for prepared statements
- [ ] **Angular setInterval/setTimeout cleanup audit** — granska att alla timers rensas i OnDestroy
- [ ] **PHP array key existence audit** — granska att isset/array_key_exists anvands fore array-access
- [ ] **Angular HTTP error message i18n audit** — granska att alla felmeddelanden ar pa svenska

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

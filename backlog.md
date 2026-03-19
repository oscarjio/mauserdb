# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-19)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #182):
- [ ] **PHP date/timezone edge cases** — granska kvarstaende DST-problem efter session #169 fixar (Worker A)
- [ ] **PHP file_get_contents/fopen audit** — granska felhantering vid fil-I/O (Worker A)
- [ ] **Angular HTTP retry audit** — granska att polling/viktiga anrop har retry-logik (Worker B)
- [ ] **Angular route guard audit** — granska att alla sidor skyddas med guards (Worker B)

### Nasta buggjakt-items (session #183+):
- [ ] **PHP header injection audit** — granska att inga HTTP-headers skapas fran anvandardata utan validering
- [ ] **PHP JSON response consistency** — granska att alla controllers returnerar enhetligt JSON-format
- [ ] **Angular lazy-loading verification** — granska att alla feature-moduler laddas lazy
- [ ] **PHP error_log format audit** — granska att alla error_log anvander konsistent format med context
- [ ] **Angular form accessibility audit** — granska att alla formularelement har labels och ARIA-attribut

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

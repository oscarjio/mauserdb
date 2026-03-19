# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-19)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #181):
- [ ] **PHP SQL column name audit** — granska att alla SQL-fragor refererar korrekta kolumnnamn (Worker A)
- [ ] **PHP input sanitization audit** — granska att alla $_GET/$_POST valideras/saniteras (Worker A)
- [ ] **Angular error boundary audit** — granska att alla HTTP-fel visar anvandardvandligt meddelande (Worker B)
- [ ] **Angular template null-safety** — granska att alla async-data hanterar null/undefined i templates (Worker B)

### Nasta buggjakt-items (session #182+):
- [ ] **PHP date/timezone edge cases** — granska DST-hantering i alla datum-berakningar
- [ ] **PHP file_get_contents/fopen audit** — granska felhantering vid fil-I/O
- [ ] **Angular HTTP retry audit** — granska att polling/viktiga anrop har retry-logik
- [ ] **PHP array access audit** — granska att alla array-accessors skyddas med isset/??
- [ ] **Angular route guard audit** — granska att alla admin-sidor skyddas med guards

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-17)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [ ] **PHP session/cookie security audit** — granska session-hantering, cookie-flaggor (httponly, secure, samesite)
- [ ] **Angular template strict null-check audit** — granska templates for saknade ?. och null-guards i bindings
- [ ] **PHP SQL column name verification** — jamfor SQL-queries mot faktiskt DB-schema, leta felstavade kolumner
- [ ] **PHP boundary/pagination validation** — granska LIMIT/OFFSET-parametrar, max-gränser, negativa värden
- [ ] **Angular form input sanitization audit** — granska att input-fält saniterar data korrekt fore HTTP-anrop
- [ ] **PHP date range validation audit** — granska att start_date <= end_date, max spannet, ogiltiga datum

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

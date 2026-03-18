# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-18)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **PHP division by zero audit** — alla divisioner har zero-check, inga buggar (session #159)
- [x] **Angular memory leak audit** — Chart.js, EventListener, rxjs alla OK (session #159)
- [x] **PHP file upload validation audit** — inga fil-uploads i projektet (session #159)
- [x] **Angular form validation audit** — alla formuler har korrekt validering (session #159)
- [x] **PHP session/auth edge case audit** — 3 controllers saknade auth-check, fixade (session #159)
- [x] **Angular error display audit** — 2 buggar fixade: loading state + delete error (session #159)
- [ ] **PHP SQL query edge case audit** — granska komplexa SQL-fragor for NULL-hantering, LIMIT/OFFSET
- [ ] **Angular template null-safety audit** — verifiera ?. och *ngIf-guards i templates
- [ ] **PHP date/time parsing audit** — granska strtotime/DateTime for ogiltiga input
- [ ] **Angular HTTP interceptor audit** — verifiera retry-logik, token refresh, error mapping
- [ ] **PHP array access audit** — granska array-accesser utan isset/array_key_exists
- [ ] **Angular router guard audit** — verifiera att auth-guards skyddar alla skyddade routes

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

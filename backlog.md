# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-18)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **PHP SQL ORDER BY injection audit** — alla whitelist-validerade (session #157)
- [x] **Angular route param validation audit** — alla validerade (session #157)
- [x] **PHP error response format audit** — 2 XSS + 20 svenska (session #157)
- [x] **Angular loading state audit** — 1 fix produktionspuls-widget (session #157)
- [x] **PHP unused method audit** — inga oanvanda (session #157)
- [x] **PHP input sanitization audit** — 75 ENT_QUOTES + 3 strip_tags/mb_substr (session #158)
- [x] **Angular HTTP retry/timeout audit** — 6 catchError i alerts.service (session #158)
- [x] **Angular change detection audit** — default CD, inga problem (session #158)
- [ ] **PHP division by zero audit** — granska alla divisioner for zero-check
- [ ] **Angular memory leak audit** — verifiera att alla subscriptions rensas korrekt
- [ ] **PHP file upload validation audit** — verifiera filtyp, storlek, path traversal-skydd
- [ ] **Angular form validation audit** — verifiera att alla formuler har korrekt validering
- [ ] **PHP session/auth edge case audit** — granska auth-floden for edge cases
- [ ] **Angular error display audit** — verifiera att felmeddelanden visas for anvandaren

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

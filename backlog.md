# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-19)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #174):
- [x] **PHP input validation completeness** — 3 stored XSS buggar fixade (Worker A)
- [x] **PHP SQL injection review** — 0 buggar, redan prepared statements overallt (Worker A)
- [x] **Angular HTTP error retry logic** — 0 buggar, redan komplett (Worker B)
- [x] **Angular route guard completeness** — 0 buggar, redan komplett (Worker B)

### Nasta buggjakt-items (session #175+):
- [ ] **PHP logging audit** — saknade loggningar vid kritiska operationer
- [ ] **Angular memory leak audit** — subscription-lackor, saknade unsubscribe, timers utan cleanup
- [ ] **PHP file upload security** — MIME-validering, filstorlek, path traversal
- [ ] **Angular form validation consistency** — saknad client-side validering pa formular
- [ ] **PHP CORS configuration review** — verifiera att CORS-headers ar korrekta och restriktiva

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

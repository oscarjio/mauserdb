# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-25)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #310):
- [ ] **PHP SQL LIKE without escaping** — LIKE '%{$var}%' utan addcslashes (Worker A)
- [ ] **PHP header/Content-Type consistency** — endpoints som saknar Content-Type: application/json (Worker A)
- [ ] **Angular route param type safety** — parseInt utan isNaN-check (Worker B)
- [ ] **Angular HTTP retry logic audit** — GET med retry vs POST utan retry (Worker B)

### Nasta buggjakt-items (session #311+):
- [ ] **PHP SQL ORDER BY dynamic** — ORDER BY med ovaliderade kolumnnamn fran user input
- [ ] **PHP session/cookie attribute audit** — SameSite, Secure, HttpOnly pa alla cookies
- [ ] **Angular form validation consistency** — required-attribut i HTML vs validators i TS
- [ ] **Angular chart.js destroy audit** — chart-instanser som inte destroyas vid omredering
- [ ] **PHP error_log format consistency** — inkonsekvent loggformat mellan controllers

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

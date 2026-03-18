# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-18)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #170):
- [x] **PHP error boundary audit** — 31 tysta catch-block + 4 felaktiga success:true fixade (Worker A)
- [x] **PHP input validation completeness** — 1 session read_and_close-bugg fixad (Worker A)
- [x] **PHP session security audit** — 2 timeout-buggar fixade (Worker A)
- [x] **Angular HTTP retry/timeout audit** — 0 buggar, alla 675+ anrop redan korrekta (Worker B)
- [x] **Angular route lazy-loading audit** — 0 buggar, alla 160+ routes lazy-loadade (Worker B)

### Nasta buggjakt-items (session #171+):
- [ ] **PHP CORS/preflight audit** — OPTIONS-hantering, Access-Control-headers konsistens
- [ ] **Angular form validation audit** — saknad validering pa input-falt, felmeddelanden
- [ ] **PHP logging consistency audit** — error_log format, saknade loggningar vid kritiska operationer
- [ ] **Angular chart destroy audit** — Chart.js-instanser som inte destroyas korrekt
- [ ] **PHP JSON response consistency** — inkonsistenta nyckelnamn, saknade statuskoder

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

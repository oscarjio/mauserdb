# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-19)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Nasta buggjakt-items (session #177+):
- [ ] **PHP file permission audit** — verifiera att filskrivning (loggar, uploads) anvander korrekta permissions
- [ ] **PHP rate limiting review** — kontrollera att login/API-endpoints har brute-force-skydd
- [ ] **Angular HTTP interceptor audit** — verifiera att alla HTTP-fel fangas korrekt globalt
- [ ] **PHP SQL injection re-audit** — granska nyligen andrade controllers for osaker input-hantering
- [ ] **Angular chart memory audit** — dubbelkolla att Chart.js-instanser destroyas korrekt vid snabb navigering

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

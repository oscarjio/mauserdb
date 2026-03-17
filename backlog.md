# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-17)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] PHP file operation safety — inga path traversal-sarbarheter hittade [Worker A #139]
- [x] PHP unused variable cleanup + dead code — 1 oanvand metod borttagen [Worker A #139]
- [x] Angular HTTP interceptor audit — retry-logik + HTTP 408 [Worker B #139]
- [x] Angular change detection optimering — 10 metodanrop ersatta med cached properties [Worker B #139]
- [x] Angular deprecated API migration — HttpClientModule borttagen fran 7 komponenter [Worker B #139]
- [ ] **PHP SQL query consistency** — granska prepared statements, saknade bindParam-typer
- [ ] **Angular form validation audit** — granska alla reaktiva forms for saknad validering
- [ ] **PHP error_log audit** — sakerhetskanslig data i loggar (losen, tokens)
- [ ] **Angular lazy loading audit** — verifiera att alla routes lazy-loadas korrekt
- [ ] **PHP CORS/headers audit** — granska Access-Control-* headers for saknade/felaktiga varden

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

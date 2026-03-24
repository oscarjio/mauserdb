# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-24)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #303):
- [ ] **PHP SQL GROUP_CONCAT truncation** — default max_length 1024 kan trunkera data (Worker A)
- [ ] **PHP error_log rotation/size** — kontrollera att loggfiler inte vaxer obegransat (Worker A)
- [ ] **PHP PDO::ATTR_STRINGIFY_FETCHES** — inkonsekvent typning fran DB-fragor (Worker A)
- [ ] **Angular router scroll position** — saknad scrollPositionRestoration vid navigation (Worker B)
- [ ] **Angular HTTP race conditions** — switchMap vs exhaustMap pa POST/PUT anrop (Worker B)

### Nasta buggjakt-items (session #304+):
- [ ] **PHP SQL implicit type conversion** — WHERE string_col = int kan ge oforutsagbara resultat
- [ ] **PHP array_splice off-by-one** — kontrollera offset/length i alla array_splice-anrop
- [ ] **Angular ViewChild static timing** — static:true vs static:false vid dynamic content
- [ ] **PHP SQL HAVING without GROUP BY** — ogiltig SQL som MySQL tillater i lax-lage
- [ ] **Angular httpClient memory on navigation** — ofullstandiga HTTP-anrop vid route-byte

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

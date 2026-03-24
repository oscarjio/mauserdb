# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-24)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #302):
- [ ] **PHP date() timezone consistency** — explicit timezone i alla date()/strtotime() vs server default (Worker A)
- [ ] **PHP SQL COUNT vs EXISTS** — ineffektiva COUNT(*) dar EXISTS racker (Worker A)
- [ ] **PHP mb_string consistency** — blandning av strlen/substr och mb_strlen/mb_substr (Worker A)
- [ ] **Angular renderer security** — innerHTML/bypassSecurityTrust utan sanitering (Worker B)
- [ ] **Angular lazy loading chunk errors** — verifiera GlobalErrorHandler + chunk-felhantering (Worker B)

### Nasta buggjakt-items (session #303+):
- [ ] **PHP SQL GROUP_CONCAT truncation** — default max_length 1024 kan trunkera data
- [ ] **PHP error_log rotation/size** — kontrollera att loggfiler inte vaxer obegransat
- [ ] **Angular router scroll position** — saknad scrollPositionRestoration vid navigation
- [ ] **PHP PDO::ATTR_STRINGIFY_FETCHES** — inkonsekvent typning fran DB-fragor
- [ ] **Angular HTTP race conditions** — switchMap vs exhaustMap pa POST/PUT anrop

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

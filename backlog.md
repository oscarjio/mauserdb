# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-23)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #264):
- [x] **PHP header() consistency audit** — Worker A — rent
- [x] **PHP json_encode flags audit** — Worker A — rent (1085 anrop, alla JSON_UNESCAPED_UNICODE)
- [x] **Angular memory leak audit** — Worker B — rent (170+ komponenter)
- [x] **Angular HTTP URL consistency audit** — Worker B — rent (alla via environment.apiUrl)

### Nasta buggjakt-items (session #265+):
- [ ] **PHP arithmetic overflow/division audit** — division by zero, integer overflow i berakningar
- [ ] **PHP include/require path audit** — saknade filer, relativa vs absoluta sokvagar
- [ ] **Angular router guard consistency audit** — saknade guards, felaktig redirect-logik
- [ ] **PHP SQL injection i dynamiska ORDER BY/GROUP BY** — kolumnnamn fran user input
- [ ] **Angular service singleton audit** — providedIn root vs komponent-scope, oavsiktlig multi-instans

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

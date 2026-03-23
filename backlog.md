# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-23)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #263):
- [x] **PHP date/string comparison audit** — Worker A — rent
- [x] **PHP PDO fetch mode consistency audit** — Worker A — rent (alla FETCH_ASSOC)
- [x] **PHP SQL column alias consistency audit** — Worker A — rent
- [x] **Angular pipe purity audit** — Worker B — rent (inga custom pipes)
- [x] **Angular change detection audit** — Worker B — rent
- [x] **Angular template null safety audit** — Worker B — rent (18 templates)

### Nasta buggjakt-items (session #264+):
- [ ] **PHP header() consistency audit** — Content-Type, CORS, caching headers
- [ ] **PHP json_encode flags audit** — JSON_THROW_ON_ERROR, JSON_UNESCAPED_UNICODE
- [ ] **Angular memory leak audit** — EventEmitter, DOM listeners, chart instances
- [ ] **Angular HTTP URL consistency audit** — hardcoded URLs, trailing slashes, query params
- [ ] **PHP arithmetic overflow/division audit** — division by zero, integer overflow i berakningar

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-25)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #321):
- [ ] **PHP session/cookie security audit** — session fixation, cookie flags, expiry (Worker A)
- [ ] **PHP file I/O audit** — fopen/fwrite utan error check, temp-filer, path traversal (Worker A)
- [ ] **PHP error handling consistency audit** — response format, try/catch, felkoder (Worker A)
- [ ] **Angular lazy loading performance audit** — chunk sizes, preloading, cirkulara imports (Worker B)
- [ ] **Angular accessibility audit** — aria-labels, keyboard navigation, screen reader (Worker B)
- [ ] **Angular change detection audit** — OnPush, trackBy, async pipe, template-logik (Worker B)

### Nasta buggjakt-items (session #322+):
- [ ] **PHP SQL query builder audit** — prepared statements, parameter binding, query concatenation
- [ ] **Angular state management audit** — BehaviorSubject race conditions, stale state
- [ ] **PHP CORS/header security audit** — Access-Control headers, content-type validation
- [ ] **Angular environment config audit** — hardcoded URLs, missing env variables

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

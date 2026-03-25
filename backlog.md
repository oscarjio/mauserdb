# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-25)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #318):
- [ ] **PHP file upload/path traversal audit** — move_uploaded_file, basename, saknad validering (Worker A)
- [ ] **PHP session/cookie security audit** — session_regenerate_id, httponly, secure-flaggor (Worker A)
- [ ] **PHP raw SQL string concatenation audit A-M** — prepared statements, SQL-injektion (Worker A)
- [ ] **Angular lazy loading audit** — loadChildren, saknade modules (Worker B)
- [ ] **Angular change detection audit N-Z** — subscription-lackor, trackBy (Worker B)
- [ ] **Angular HTTP interceptor/error handling audit** — catchError, 401-hantering (Worker B)

### Nasta buggjakt-items (session #319+):
- [ ] **PHP raw SQL string concatenation audit N-Z** — fortsattning fran A-M
- [ ] **Angular pipe/directive audit** — pure pipes, saknade imports, felaktiga transformeringar
- [ ] **PHP error logging audit** — saknade error_log, tysta catch-block
- [ ] **Angular memory leak audit** — DOM-references, event listeners, ResizeObserver

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

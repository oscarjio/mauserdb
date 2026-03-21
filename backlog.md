# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-21)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #234):
- [ ] **PHP classes/ CORS/cookie SameSite audit** — Worker A
- [ ] **PHP classes/ file upload validation audit** — Worker A
- [ ] **PHP classes/ SQL JOIN correctness audit** — Worker A
- [ ] **Angular reactive state management audit** — Worker B
- [ ] **Angular form dirty-state/unsaved changes audit** — Worker B

### Nasta buggjakt-items (session #235+):
- [ ] **PHP classes/ error logging completeness audit** — saknade loggposter for viktiga operationer (login, password change, admin actions)
- [ ] **Angular HTTP interceptor error normalization audit** — konsekvent felhantering over alla services
- [ ] **PHP classes/ session fixation/regeneration audit** — session_regenerate_id() efter login/privilege change
- [ ] **Angular component memory profiling** — detached DOM-noder, stora objekt som inte GC:as
- [ ] **PHP classes/ SQL transaction rollback audit** — saknade rollback i catch-block efter beginTransaction

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

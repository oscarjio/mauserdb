# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-21)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Nasta buggjakt-items (session #234+):
- [ ] **PHP classes/ CORS/cookie SameSite audit** — saknade SameSite-attribut pa cookies
- [ ] **PHP classes/ file upload validation audit** — MIME-type, storlek, path traversal
- [ ] **Angular reactive state management audit** — BehaviorSubject race conditions, stale subscriptions
- [ ] **PHP classes/ SQL JOIN correctness audit** — felaktiga JOIN-villkor, saknade ON-klausuler
- [ ] **Angular form dirty-state/unsaved changes audit** — saknad varning vid navigation med osparade andringar

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

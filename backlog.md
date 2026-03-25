# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-25)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #317):
- [x] **PHP numeric precision audit** — 1 bugg fixad (RebotlingController div-by-zero)
- [x] **Angular route guard audit** — rent (~80 routes)
- [x] **PHP SQL transaction audit N-Z** — rent (19 controllers)
- [x] **Angular form validation audit** — rent
- [x] **PHP array bounds/null audit N-Z** — rent
- [x] **Angular template binding audit A-M** — rent (55+ templates)

### Nasta buggjakt-items (session #318+):
- [ ] **Angular template binding audit A-M** — null-checkar, trackBy, pipe-fel
- [ ] **PHP file upload/path traversal audit** — move_uploaded_file, basename, saknad validering
- [ ] **Angular lazy loading audit** — felaktiga loadChildren, saknade modules
- [ ] **PHP session/cookie security audit** — session_regenerate_id, httponly, secure-flaggor
- [ ] **Angular change detection audit** — onPush-strategi, async pipe vs subscribe

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

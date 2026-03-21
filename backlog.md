# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-21)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Nasta buggjakt-items (session #230+):
- [ ] **PHP classes/ array_key_exists vs isset audit** — isset returnerar false for null-varden
- [ ] **PHP classes/ exception handling granularity audit** — for breda catch-block, saknad feltyp-differentiering
- [ ] **Angular HTTP retry logic audit** — saknad retry pa transienta fel, felaktig retry pa POST
- [ ] **PHP classes/ SQL transaction isolation audit** — saknad transaction dar flera queries ska vara atomara
- [ ] **Angular change detection audit** — onodiga rerenders, saknad OnPush dar mojligt

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

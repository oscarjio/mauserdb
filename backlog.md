# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-21)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #230):
- [x] **PHP classes/ array_key_exists vs isset audit** — rent (117 filer)
- [x] **PHP classes/ exception handling granularity audit** — rent (117 filer)
- [x] **Angular HTTP retry logic audit** — 1 bugg fixad (POST retry i error interceptor)
- [x] **Angular change detection audit** — rent (42 komponenter)

### Nasta buggjakt-items (session #231+):
- [ ] **PHP classes/ SQL transaction isolation audit** — saknad transaction dar flera queries ska vara atomara
- [ ] **PHP classes/ date/time edge case audit** — leap year, DST, midnight, month boundaries
- [ ] **Angular lazy loading + bundle size audit** — stora moduler som inte lazy-loadas
- [ ] **PHP classes/ input length/bounds validation audit** — saknad maxlangd-kontroll pa user input
- [ ] **Angular form state consistency audit** — dirty/pristine/touched states, submit-knapp disabled-logik

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

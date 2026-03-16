# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-16)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **Buggjakt: Backend classes/ controllers batch 2** — Worker A #122 — 13 buggar i 7 controllers (av 13 granskade + api.php OK)
- [x] **Buggjakt: Backend routing/api.php** — Worker A #122 — inga orphan-actions hittade
- [x] **Buggjakt: PHP helper-klasser** — Worker B #122 — rena (prepared statements, bcrypt, korrekt felhantering)
- [x] **Buggjakt: Funktionstesta Rebotling-endpoints** — Worker B #122 — 1 kritisk 500-fix (TrendanalysController constructor)
- [x] **Buggjakt: Funktionstesta Funktioner-endpoints** — Worker B #122 — 14 HTTP-statuskod-fixar (404->400) i 7 controllers
- [ ] **Buggjakt: Backend classes/ controllers batch 3** — Granska resterande controllers som INTE granskats i batch 1+2
- [ ] **Buggjakt: Angular pipes och directives** — Granska custom pipes/directives for buggar
- [ ] **Buggjakt: Error-logging konsistens** — Verifiera att alla catch-block loggar korrekt
- [ ] **Buggjakt: SQL-queries parametervalidering** — Granska att alla user-input saniteras
- [ ] **Buggjakt: Granska Angular guards och interceptors** — Auth guards, HTTP interceptors, route guards

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

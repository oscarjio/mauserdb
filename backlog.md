# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-16)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **Buggjakt: Backend controllers batch 5** — Worker A #124 — 34 buggar i 6 filer (av 17 controllers)
- [x] **Buggjakt: Frontend services re-audit** — Worker B #124 — 18 buggar i 9 services (hardkodade URLs, saknad timeout/catchError)
- [ ] **Buggjakt: Error-logging konsistens** — Verifiera att alla catch-block loggar korrekt
- [ ] **Buggjakt: SQL-queries parametervalidering** — Granska att alla user-input saniteras
- [ ] **Buggjakt: Template null-safety** — Granska Angular templates for saknade ?. och *ngIf-guards
- [ ] **Buggjakt: Oanvanda privata metoder** — Ta bort dead code (t.ex. calcMinuter i StopporsakController)
- [ ] **Buggjakt: Frontend page-komponenter audit** — Granska TS-logik i page-komponenter (edge cases, felhantering)

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

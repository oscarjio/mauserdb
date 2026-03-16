# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-16)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **Buggjakt: Backend controllers batch 3** — Worker A #123 — 20 buggar i 10 filer (av 20 controllers)
- [x] **Buggjakt: Frontend guards/interceptors/utils + pipes** — Worker B #123 — alla rena, inga pipes finns
- [x] **Buggjakt: Backend controllers batch 4** — Worker B #123 — 7 buggar i 5 filer (av 16 controllers)
- [ ] **Buggjakt: Resterande controllers batch 5** — ProduktTypEffektivitet t.o.m. WeeklyReport (ca 20 st)
- [ ] **Buggjakt: Error-logging konsistens** — Verifiera att alla catch-block loggar korrekt
- [ ] **Buggjakt: SQL-queries parametervalidering** — Granska att alla user-input saniteras
- [ ] **Buggjakt: Angular services re-audit** — Dubbelkolla services som fixades i #119-#122
- [ ] **Buggjakt: Template null-safety** — Granska Angular templates for saknade ?. och *ngIf-guards

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

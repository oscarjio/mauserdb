# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-15)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [ ] **Buggjakt: Batch 3 controllers del 2 (9 st)** — RebotlingStationsdetalj, Skiftplanering, StatistikDashboard, StatistikOverblick, Stopporsak, StopporsakOperator, Stopptidsanalys, Underhallslogg, Produktionspuls
- [ ] **Buggjakt: OperatorRanking streaks** — verifiera calcStreaks() med riktig data
- [ ] **Buggjakt: PHP error_reporting + logging** — granska att alla controllers loggar fel konsekvent
- [ ] **Buggjakt: Services utan error handling** — granska frontend services som saknar catchError/retry
- [ ] **Buggjakt: Chart.js memory** — verifiera att alla chart-instanser destroyas korrekt i OnDestroy
- [ ] **Buggjakt: Unused imports cleanup** — ta bort oanvanda parseLocalDate/localToday imports som Worker B kan ha lamnat
- [ ] **Buggjakt: VdDashboard + SkiftjamforelseController granskning** — dessa 2 har inte granskats i batch 3

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

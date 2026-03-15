# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-15)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [ ] **Buggjakt: OperatorRanking streaks** — verifiera calcStreaks() med riktig data
- [ ] **Buggjakt: Batch 3 controllers (18 st)** — AlarmHistorik, DagligBriefing, Favoriter, HistoriskSammanfattning, KassationsDrilldown, KvalitetsTrendbrott, OeeTrendanalys, OperatorDashboard, OperatorOnboarding, ProduktionsPrognos, RebotlingStationsdetalj, Skiftplanering, StatistikDashboard, StatistikOverblick, Stopporsak, StopporsakOperator, Stopptidsanalys, Underhallslogg
- [ ] **Buggjakt: Frontend berakningar** — verifiera OEE/bonus/procent-formler i alla components mot backend-formler (konsistens)
- [ ] **Buggjakt: API-routes audit** — verifiera att alla frontend-anrop matchar backend-routes (404-risk)
- [ ] **Buggjakt: PHP error_reporting + logging** — granska att alla controllers loggar fel konsekvent
- [ ] **Buggjakt: Datum UTC-midnatt** — sok igenom alla frontend-components for `new Date("YYYY-MM-DD")` utan `T00:00:00` (2 fixade, fler kan finnas)

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

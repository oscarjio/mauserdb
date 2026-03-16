# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-16)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **Buggjakt: Batch 4 (8 controllers/)** — klar session #111
- [x] **Buggjakt: classes/ audit del 1 (4 storsta filerna)** — klar session #111
- [ ] **Buggjakt: classes/ audit del 2** — SkiftoverlamningController, KapacitetsplaneringController, OperatorDashboardController, SkiftrapportController, TvattlinjeController (~5500 rader)
- [ ] **Buggjakt: classes/ audit del 3** — AndonController, GamificationController, HistoriskSammanfattningController, VDVeckorapportController, OeeTrendanalysController, DagligSammanfattningController (~4800 rader)
- [ ] **Buggjakt: Oanvanda variabler** — RebotlingAnalyticsController (10 st), BonusAdminController (8 st), KassationsanalysController (1 st), RebotlingController (2 st)
- [ ] **Buggjakt: api.php + auth-kontroller** — verifiera att alla endpoints kraver korrekt auth, rate limiting, CORS
- [ ] **Buggjakt: Frontend template null-safety** — sok efter template-bindningar som kan krascha vid null/undefined
- [ ] **Buggjakt: PHP logging konsistens** — granska att alla controllers loggar fel konsekvent

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

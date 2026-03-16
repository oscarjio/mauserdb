# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-16)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **Buggjakt: classes/ audit del 2** — klar session #112 (Worker A)
- [x] **Buggjakt: classes/ audit del 3** — klar session #112 (Worker B: 8 buggar)
- [x] **Buggjakt: api.php + auth-kontroller** — klar session #112 (Worker B: inga problem)
- [x] **Buggjakt: operator id/number-inkonsistens** — klar session #112 (Lead: 5 buggar i 3 filer)
- [ ] **Buggjakt: Oanvanda variabler** — RebotlingAnalyticsController (~10 st), BonusAdminController (~6 st), KassationsanalysController (~1 st), RebotlingController (~2 st)
- [ ] **Buggjakt: Frontend template null-safety** — sok efter template-bindningar som kan krascha vid null/undefined
- [ ] **Buggjakt: PHP logging konsistens** — granska att alla controllers loggar fel konsekvent
- [ ] **Buggjakt: Mellanstora classes/ (batch 5)** — ProduktionsDashboardController, ProduktionseffektivitetController, ProduktionsSlaController, ProduktionsTaktController, StopporsakTrendController
- [ ] **Buggjakt: Operator-relaterade controllers** — OperatorRankingController, OperatorsportalController, OperatorOnboardingController — verifiera id/number-anvandning konsekvent
- [ ] **Buggjakt: Frontend subscription-audit** — granska att alla components med setInterval ocksa clearar i ngOnDestroy

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

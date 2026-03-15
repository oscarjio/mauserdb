# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-15)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [ ] **Buggjakt: Batch 4 (8 controllers/)** — AlarmHistorik, HistoriskSammanfattning, Kassationsanalys, OperatorOnboarding, OperatorRanking, Produktionsmal, ProduktionsPrognos, Veckorapport
- [ ] **Buggjakt: classes/ audit del 1** — systematisk granskning av classes/-mappen, borja med storsta filerna (116 filer totalt)
- [ ] **Buggjakt: api.php + auth-kontroller** — verifiera att alla endpoints kraver korrekt auth, rate limiting, CORS
- [ ] **Buggjakt: Frontend template null-safety** — sok efter template-bindningar som kan krascha vid null/undefined
- [ ] **Buggjakt: PHP logging konsistens** — granska att alla controllers loggar fel konsekvent

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

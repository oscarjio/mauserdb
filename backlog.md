# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-23)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #272):
- [ ] **PHP try/catch + SQL korrekthet** — Worker A — empty catch, GROUP BY, division-by-zero, datum i SQL
- [ ] **Angular event listener leaks + subscription audit** — Worker B — addEventListener utan cleanup, promises utan catch, ViewChild timing

### Nasta buggjakt-items (session #273+):
- [ ] **PHP controllers N-Z** — StatistikDashboardController, SkiftplaneringController, StopptidsanalysController, RebotlingStationsdetaljController, SkiftoverlamningController, UnderhallsloggController, StopporsakController, ProduktionsmalController, OeeTrendanalysController, OperatorRankingController, VdDashboardController, HistoriskSammanfattningController, StatistikOverblickController, OperatorDashboardController, DagligBriefingController, SkiftjamforelseController
- [ ] **Angular services audit** — granska alla .service.ts for felaktiga URL:er, saknad felhantering, memory leaks
- [ ] **Angular template null safety** — granska .component.html for osaker property access utan ?. eller *ngIf
- [ ] **PHP router/api.php audit** — verifiera att alla routes matchar controller-metoder, inga doda routes
- [ ] **Angular environment config** — verifiera att environment.ts och environment.prod.ts har korrekta API-URL:er

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

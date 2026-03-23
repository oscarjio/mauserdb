# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-23)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #273):
- [ ] **PHP controllers N-Z** — Worker A — 16 controllers: StatistikDashboard, Skiftplanering, RebotlingStationsdetalj, Skiftoverlamning, Underhallslogg, Stopporsak, Produktionsmal, OeeTrendanalys, OperatorRanking, VdDashboard, HistoriskSammanfattning, StatistikOverblick, OperatorDashboard, DagligBriefing, Skiftjamforelse, Narvaro
- [ ] **Angular services audit** — Worker B — alla ~90 services: URL-korrekthet, felhantering, memory leaks, typfel

### Nasta buggjakt-items (session #274+):
- [ ] **Angular template null safety** — granska .component.html for osaker property access utan ?. eller *ngIf
- [ ] **PHP router/api.php audit** — verifiera att alla routes matchar controller-metoder, inga doda routes
- [ ] **Angular environment config** — verifiera att environment.ts och environment.prod.ts har korrekta API-URL:er
- [ ] **PHP controllers A-M djupgranskning** — fler buggkategorier: boundary values, encoding, timezone
- [ ] **Angular component interaktion** — @Input/@Output korrekthet, EventEmitter utan unsubscribe

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-24)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Nasta buggjakt-items (session #297+):
- [ ] **PHP date()/mktime() edge cases** — sommar/vintertid, skottdag, ogiltiga datum
- [ ] **PHP header()/exit() flode** — controllers som saknar exit/return efter header-redirect
- [ ] **PHP PDO fetchAll memory** — stora resultset utan LIMIT, potentiell OOM
- [ ] **Angular OnDestroy cleanup audit** — komponenter med setInterval/setTimeout utan rensning i ngOnDestroy
- [ ] **Angular HTTP error message display** — verifiera att felmeddelanden fran backend visas korrekt i UI

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-24)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #297):
- [ ] **PHP date()/mktime() edge cases** — sommar/vintertid, skottdag, ogiltiga datum
- [ ] **PHP header()/exit() flode** — controllers som saknar exit/return efter header-redirect
- [ ] **PHP PDO fetchAll memory** — stora resultset utan LIMIT, potentiell OOM
- [ ] **Angular OnDestroy cleanup audit** — setInterval/setTimeout/observer utan rensning
- [ ] **Angular HTTP error message display** — felmeddelanden fran backend visas korrekt i UI

### Nasta buggjakt-items (session #298+):
- [ ] **PHP try/catch granularitet** — for breda catch-block som doljer specifika fel
- [ ] **PHP SQL COUNT vs SUM** — aggregeringsfunktioner som ger fel resultat pa tomma set
- [ ] **Angular zone.js change detection** — onnodig rendering, tunga template-uttryck
- [ ] **Angular router canDeactivate** — osparade andringar utan varning vid navigering
- [ ] **PHP input sanitering** — htmlspecialchars/strip_tags konsistens pa utdata

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

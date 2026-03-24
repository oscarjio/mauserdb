# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-24)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #290 — alla rent):
- [x] **PHP header() redirect consistency** — rent (inga header(Location:), SPA-arkitektur)
- [x] **PHP PDO fetchColumn edge cases** — rent (191 anvandningar, alla korrekt hanterade)
- [x] **PHP error suppression (@)** — rent (13 motiverade anvandningar)
- [x] **Angular ngIf race conditions** — rent (arrayer initieras som [])
- [x] **Angular router event memory leaks** — rent (inga router.events.subscribe)

### Nasta buggjakt-items (session #291+):
- [ ] **PHP numeric string comparison** — loose comparison (==) mellan numeriska strangar i controllers
- [ ] **Angular HTTP timeout** — saknade timeout-operatorer i HTTP-anrop
- [ ] **PHP mb_string consistency** — strlen/substr vs mb_strlen/mb_substr for UTF-8 data
- [ ] **Angular ViewChild timing** — ViewChild-referenser anvanda fore ngAfterViewInit
- [ ] **PHP SQL COALESCE** — saknade COALESCE/IFNULL i queries med LEFT JOIN

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-24)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #298):
- [ ] **PHP try/catch granularitet** — for breda catch-block som doljer specifika fel
- [ ] **PHP SQL COUNT vs SUM** — aggregeringsfunktioner som ger fel resultat pa tomma set
- [ ] **PHP input sanitering** — htmlspecialchars/strip_tags konsistens pa utdata
- [ ] **Angular zone.js change detection** — onnodig rendering, tunga template-uttryck
- [ ] **Angular router canDeactivate** — osparade andringar utan varning vid navigering

### Nasta buggjakt-items (session #299+):
- [ ] **PHP array type coercion** — implicit int/string-konvertering i array-nycklar
- [ ] **PHP file_put_contents atomicitet** — race conditions vid samtidig skrivning
- [ ] **Angular HTTP request cancellation** — switchMap vs mergeMap for POST-anrop
- [ ] **Angular template accessibility** — aria-attribut, tabindex, semantisk HTML
- [ ] **PHP SQL BETWEEN med datum** — off-by-one vid midnatt, timezone-medvetenhet

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

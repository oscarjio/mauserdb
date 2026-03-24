# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-24)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #299):
- [ ] **PHP array type coercion** — implicit int/string-konvertering i array-nycklar (Worker A)
- [ ] **PHP file_put_contents atomicitet** — race conditions vid samtidig skrivning (Worker A)
- [ ] **PHP SQL BETWEEN med datum** — off-by-one vid midnatt, timezone-medvetenhet (Worker A)
- [ ] **Angular HTTP request cancellation** — switchMap vs mergeMap for POST-anrop (Worker B)
- [ ] **Angular template accessibility** — aria-attribut, tabindex, semantisk HTML (Worker B)

### Nasta buggjakt-items (session #300+):
- [ ] **PHP array_combine/array_zip** — missmatch i array-langder som ger false
- [ ] **PHP exception message leakage** — felmeddelanden som exponerar interna detaljer
- [ ] **Angular memory profiling** — komponentstorlek, DOM-nodantal i tunga vyer
- [ ] **Angular form state persistence** — formularvarden som forsvinner vid navigation
- [ ] **PHP SQL transaction isolation** — dirty reads vid concurrent batch-operationer

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

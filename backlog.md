# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-21)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Avklarade (session #223):
- [x] **PHP classes/ file upload + MIME type validation audit** — rent, ingen filuppladdning i classes/ (Worker A)
- [x] **PHP classes/ array key existence audit** — rent, alla har korrekt ?? och isset() (Worker A)
- [x] **PHP classes/ string encoding + multibyte audit** — 20 buggar fixade i 14 filer (Worker A)
- [x] **Angular HTTP interceptor error normalization audit** — 37 buggar fixade i 12 filer (Worker B)
- [x] **Angular template null-safe navigation audit** — rent (Worker B)

### Nasta buggjakt-items (session #224+):
- [ ] **PHP classes/ regex injection + preg_match audit** — ovaliderade regex-monster fran user input
- [ ] **Angular pipe error handling audit** — custom pipes som kastar vid null/undefined/ovantat format
- [ ] **PHP classes/ session concurrency + race condition audit** — parallella requests som skriver samma session/rad
- [ ] **Angular router resolve/guard data consistency audit** — data som laddas i resolve men kan vara stale
- [ ] **PHP classes/ HTTP header injection audit** — header() med ovaliderade varden

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

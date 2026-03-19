# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-19)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Nasta buggjakt-items (session #176+):
- [ ] **PHP CORS configuration review** — verifiera att CORS-headers ar korrekta och restriktiva
- [ ] **PHP session handling audit** — session fixation, regenerate_id, timeout-hantering
- [ ] **Angular error boundary audit** — saknade catchError i pipe chains, obehandlade promise rejections
- [ ] **PHP pagination/limit audit** — endpoints utan LIMIT som kan returnera for mycket data
- [ ] **Angular template accessibility** — saknade alt-texter, aria-attributes pa interaktiva element

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

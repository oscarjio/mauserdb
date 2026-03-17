# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-17)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [ ] **PHP response format consistency** — granska JSON-svar for konsekvent success/error-struktur
- [ ] **Angular error state UI audit** — komponenter som saknar felmeddelanden vid HTTP-fel
- [ ] **PHP transaction audit** — granska BEGIN/COMMIT/ROLLBACK for korrekta transaktionsgranser
- [ ] **Angular route guard audit** — verifiera att skyddade sidor har canActivate-guards
- [ ] **PHP input sanitization audit** — granska $_GET/$_POST for saknad htmlspecialchars/intval
- [ ] **Angular chart cleanup audit** — verifiera chart.destroy() i alla ngOnDestroy

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

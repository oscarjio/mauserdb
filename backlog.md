# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-17)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [ ] **PHP SQL injection re-audit** — ORDER BY utan whitelist, dynamiska kolumnnamn, LIMIT utan intval (Worker A #146)
- [ ] **PHP deprecated patterns** — nullable params utan ?, strftime(), loose comparisons (Worker A #146)
- [ ] **Angular lazy loading audit** — onodiga imports, SharedModule-bloat, bundle-storlekar (Worker B #146)
- [ ] **Angular change detection audit** — OnPush-strategi, tunga getter-anrop i templates (Worker B #146)
- [ ] **Angular unused imports/declarations** — oanvanda module-imports, deklarerade men ej anvanda komponenter
- [ ] **PHP rate limiting** — granska login/API-endpoints for brute-force-skydd
- [ ] **Angular accessibility audit** — aria-attribut, keyboard navigation, fokushantering

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

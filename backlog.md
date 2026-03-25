# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-25)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #323):
- [ ] **PHP logging/audit trail audit** — saknad loggning av viktiga handelser, loggniva-konsistens (Worker A)
- [ ] **PHP race condition audit** — concurrent requests, DB-lasning, optimistic locking (Worker A)
- [ ] **PHP date/timezone audit** — datum-/tidshantering, timezone-setting, datumformat (Worker A)
- [ ] **Angular memory profiling audit** — DOM-lackor, detached elements, stora arrayer i minnet (Worker B)
- [ ] **Angular router guard audit** — auth guards, canActivate/canDeactivate, redirect-logik (Worker B)
- [ ] **Angular form validation audit** — reactive forms, template-driven forms, error-display (Worker B)

### Nasta buggjakt-items (session #324+):
- [ ] **PHP session management audit** — session fixation, session timeout, cookie flags
- [ ] **Angular SSR/hydration audit** — server-side rendering kompatibilitet, hydration-fel
- [ ] **PHP file upload audit** — MIME-validering, filstorlek, path traversal
- [ ] **Angular pipe/transform audit** — rena vs orena pipes, prestandaproblem, edge cases

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

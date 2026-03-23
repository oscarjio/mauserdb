# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-23)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Nasta buggjakt-items (session #282+):
- [ ] **PHP mail/notification edge cases** — felhantering vid misslyckad e-post, timeout
- [ ] **PHP cron/scheduled tasks** — race conditions, timeout-hantering, dubbletter
- [ ] **Angular chart.js konfiguration** — felaktiga options, saknade defaults, responsive
- [ ] **Angular localStorage/sessionStorage** — quota exceeded, JSON parse errors, fallbacks
- [ ] **PHP response caching headers** — per-endpoint Cache-Control, ETag, 304 korrekthet
- [ ] **Angular SSR/hydration readiness** — window/document-anvandning, isPlatformBrowser

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

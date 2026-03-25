# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-25)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #324):
- [ ] **PHP session management audit** — session fixation, timeout, cookie flags (Worker A)
- [ ] **PHP file upload audit** — MIME-validering, filstorlek, path traversal (Worker A)
- [ ] **PHP error handling/exception audit** — try/catch, exponerade fel, loggning (Worker A)
- [ ] **Angular SSR/hydration audit** — DOM-manipulation, browser-guards, hydration-mismatch (Worker B)
- [ ] **Angular pipe/transform audit** — rena vs orena pipes, null-hantering, prestanda (Worker B)
- [ ] **Angular HTTP/API integration audit** — error handlers, URLs, timeouts, race conditions (Worker B)

### Nasta buggjakt-items (session #325+):
- [ ] **PHP dependency/composer audit** — outdated packages, kanda CVE:er, autoload-problem
- [ ] **Angular accessibility audit** — aria-attribut, keyboard-navigation, skarmlasar-kompatibilitet
- [ ] **PHP SQL query performance audit** — saknade index, N+1-fragor, slow queries
- [ ] **Angular build/bundle audit** — tree-shaking, dead code, bundle-storlek

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

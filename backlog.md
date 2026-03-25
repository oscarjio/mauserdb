# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-25)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #325):
- [ ] **PHP dependency/composer audit** — outdated packages, kanda CVE:er, autoload-problem (Worker A)
- [ ] **PHP SQL query performance audit** — saknade index, N+1-fragor, slow queries (Worker A)
- [ ] **PHP input sanitization audit** — prepared statements, XSS, CSRF, input-validering (Worker A)
- [ ] **Angular accessibility audit** — aria-attribut, keyboard-navigation, skarmlasar-kompatibilitet (Worker B)
- [ ] **Angular build/bundle audit** — tree-shaking, dead code, bundle-storlek (Worker B)
- [ ] **Angular template security audit** — innerHTML, sanitering, dynamiska URLs (Worker B)

### Nasta buggjakt-items (session #326+):
- [ ] **PHP caching/performance audit** — saknad caching, redundanta DB-anrop, ineffektiv logik
- [ ] **Angular change detection audit** — onPush-strategi, markForCheck, zonfri kod
- [ ] **PHP API response format audit** — inkonsekvent JSON-struktur, saknade statuskoder, felmeddelanden
- [ ] **Angular state management audit** — BehaviorSubject-lackor, delat tillstand, minnesanvandning

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

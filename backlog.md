# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-25)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #309):
- [ ] **PHP SQL implicit type conversion** — WHERE string-kolumn = integer utan quotes (Worker A)
- [ ] **PHP exception handling granularity** — breda catch(Exception) som doljer specifika fel (Worker A)
- [ ] **PHP array bounds / off-by-one** — array-access utan isset/key-check (Worker A)
- [ ] **Angular ngIf/ngSwitch exhaustiveness** — switch utan default, saknade else-fall (Worker B)
- [ ] **Angular input sanitization** — innerHTML utan DomSanitizer, XSS-risk (Worker B)
- [ ] **Angular template expression complexity** — ej cachade funktionsanrop i templates (Worker B)

### Nasta buggjakt-items (session #310+):
- [ ] **PHP SQL LIKE without escaping** — LIKE '%{$var}%' utan addcslashes
- [ ] **PHP header/Content-Type consistency** — endpoints som saknar Content-Type: application/json
- [ ] **Angular route param type safety** — parseInt utan isNaN-check
- [ ] **Angular HTTP retry logic audit** — GET med retry vs POST utan retry

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

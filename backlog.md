# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-23)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #271):
- [ ] **PHP error_reporting/display_errors** — Worker A — saknad error_reporting(0) i produktion
- [ ] **PHP file lock consistency** — Worker A — flock() vid concurrent file writes, saknade LOCK_EX
- [ ] **PHP PDO prepared statement reuse** — Worker A — duplicerade queries, SQL i loopar
- [ ] **Angular memory leaks i charts** — Worker B — Chart.js-instanser som inte destroyas
- [ ] **Angular HTTP retry logic** — Worker B — retryWhen/retry felkonfiguration, exponential backoff

### Nasta buggjakt-items (session #272+):
- [ ] **PHP output buffering** — ob_start/ob_end konsistens, saknade ob_clean vid errors
- [ ] **PHP CORS preflight** — OPTIONS-requests hanteras korrekt i alla endpoints
- [ ] **Angular lazy loading chunking** — verifiera att alla lazy routes laddar korrekt
- [ ] **PHP mail/SMTP edge cases** — timeout, encoding, attachment-storlek
- [ ] **Angular form validation edge cases** — async validators, cross-field validation

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

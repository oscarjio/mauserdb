# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-23)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #270):
- [ ] **PHP output buffering** — Worker A — ob_start/ob_end konsistens, saknade ob_clean vid errors
- [ ] **PHP session race conditions** — Worker A — session_write_close vid langa requests
- [ ] **PHP CORS preflight** — Worker A — OPTIONS-requests hanteras korrekt i alla endpoints
- [ ] **Angular route resolver errors** — Worker B — felhantering i resolvers, loading states
- [ ] **Angular SSR compatibility** — Worker B — window/document-access utan isPlatformBrowser

### Nasta buggjakt-items (session #271+):
- [ ] **PHP error_reporting/display_errors** — saknad error_reporting(0) i produktion, display_errors on
- [ ] **Angular memory leaks i charts** — Chart.js-instanser som inte destroyas vid komponentbyte
- [ ] **PHP file lock consistency** — flock() vid concurrent file writes, saknade LOCK_EX
- [ ] **Angular HTTP retry logic** — retryWhen/retry felkonfiguration, exponential backoff
- [ ] **PHP PDO prepared statement reuse** — samma query kopierad istallet for parametriserad

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

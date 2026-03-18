# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-18)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #169):
- [x] **PHP file path traversal audit** — 0 buggar, redan sakert (Worker A)
- [x] **PHP date/time edge case audit** — 27 DST-buggar fixade i 14 controllers (Worker A)
- [x] **PHP SQL transaction completeness audit** — 0 buggar, redan korrekt (Worker A)
- [x] **Angular memory leak re-audit** — 0 buggar, alla komponenter korrekta (Worker B)
- [x] **Angular accessibility audit** — 10 saknade aria-labels fixade (Worker B)

### Nasta buggjakt-items (session #170+):
- [ ] **PHP error boundary audit** — set_error_handler, register_shutdown_function, fatala fel
- [ ] **Angular HTTP retry/timeout audit** — saknad timeout pa HTTP-anrop, retry-logik
- [ ] **PHP input validation completeness** — POST/GET-parametrar utan filter_input/validering
- [ ] **Angular route lazy-loading audit** — stora bundles som borde lazy-loadas
- [ ] **PHP session security audit** — session fixation, regenerate_id, cookie flags

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

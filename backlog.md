# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-22)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #259):
- [x] **PHP file_get_contents/curl error handling audit** — Worker A — rent
- [x] **PHP session handling audit** — Worker A — rent
- [x] **PHP array_key_exists vs isset audit** — Worker A — rent
- [x] **Angular change detection strategy audit** — Worker B — rent
- [x] **Angular lazy loading route audit** — Worker B — rent
- [x] **Angular reactive forms validation audit** — Worker B — rent

### Nasta buggjakt-items (session #260+):
- [ ] **PHP date/time timezone handling audit** — date() vs DateTime, saknade timezone-settings
- [ ] **PHP JSON encode/decode error handling** — json_last_error() check efter json_decode
- [ ] **PHP integer overflow/boundary audit** — stora ID:n, raknare, pagination edge cases
- [ ] **Angular HTTP timeout consistency audit** — saknade/inkonsistenta timeout() pa HTTP-anrop
- [ ] **Angular memory leak audit (setInterval)** — setInterval utan clearInterval i OnDestroy

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-18)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **PHP error_log consistency audit** — redan konsekvent format, inga var_dump/print_r (session #155)
- [x] **Angular HTTP error message audit** — alla felmeddelanden redan pa svenska (session #155)
- [x] **PHP integer casting audit** — alla params redan korrekt castade (session #155)
- [x] **PHP array key existence audit** — 8 json_decode null-safety fixar (session #155)
- [x] **Angular change detection audit** — 47 trackByIndex->trackById i 32 komponenter (session #155)
- [ ] **PHP date/time edge case audit** — strtotime/DateTime med ogiltiga indata, saknade try/catch
- [ ] **Angular memory leak audit** — chart.destroy(), clearInterval, unsubscribe i alla komponenter
- [ ] **PHP file path traversal audit** — verifiera att filsokvagar inte kan manipuleras via user input
- [ ] **PHP transaction consistency audit** — verifiera att alla multi-query-operationer anvander transactions
- [ ] **Angular form reset audit** — formuler som inte rensar state efter submit/cancel

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

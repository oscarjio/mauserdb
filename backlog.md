# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-18)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **PHP response header audit** — headers redan korrekt globalt i api.php (session #154)
- [x] **Angular form validation audit** — 20 fixar i 10 komponenter (session #154)
- [x] **PHP SQL column name audit** — 4 fixar, rebotling_log existerade ej (session #154)
- [x] **PHP unused variable cleanup** — 4 oanvanda variabler borttagna (session #154)
- [x] **Angular template expression audit** — 33 fixar !.->?. i 3 komponenter (session #154)
- [ ] **PHP error_log consistency audit** — verifiera att alla error_log anropar samma format, inga var_dump/print_r i prod
- [ ] **Angular HTTP error message audit** — verifiera att alla HTTP-felmeddelanden visas pa svenska till anvandaren
- [ ] **PHP integer casting audit** — (int) casts pa query params, verifiera mot SQL injection
- [ ] **PHP array key existence audit** — isset/array_key_exists fore arrayelement-access
- [ ] **Angular change detection audit** — OnPush candidates, trackBy i alla ngFor

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

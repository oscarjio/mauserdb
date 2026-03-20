# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #213):
- [x] **PHP classes/ error logging audit** — 34 buggar fixade: tomma catch-block i 5 PHP-klasser (Worker A)
- [x] **PHP classes/ CORS/headers audit** — RENT, centraliserad i api.php (Worker A)
- [x] **Angular HTTP interceptor audit** — RENT, korrekt retry/401/CSRF (Worker B)
- [x] **Angular template strict null check** — RENT, konsekvent ?. och *ngIf (Worker B)

### Nasta buggjakt-items (session #214+):
- [ ] **PHP classes/ date/time edge case re-audit** — DST, midnight, month boundaries
- [ ] **Angular service URL consistency audit** — hardkodade vs environment-baserade API-URLer
- [ ] **PHP classes/ integer overflow/bounds audit** — saknade range-checks pa intval/floatval
- [ ] **Angular form validation audit** — saknade required/minlength/maxlength attribut
- [ ] **PHP classes/ SQL JOIN audit** — felaktiga LEFT/INNER JOINs, saknade ON-villkor

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

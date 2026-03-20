# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #204):
- [x] **PHP classes/ race condition audit** — 3 buggar: CertificationController + UnderhallsloggController (Worker A)
- [x] **PHP classes/ SQL LIKE injection audit** — 0 buggar, addcslashes() anvands korrekt (Worker A)
- [x] **Angular router guard audit** — 0 buggar, alla routes har korrekt guards (Worker B)
- [x] **Angular environment config audit** — 0 buggar, environment.prod.ts korrekt (Worker B)

### Nasta buggjakt-items (session #205+):
- [ ] **PHP classes/ date/timezone consistency audit** — blandning av UTC/lokal tid, sommartid-buggar
- [ ] **PHP classes/ file upload validation audit** — MIME-type, storlek, filnamn-sanering
- [ ] **Angular change detection audit** — onodiga rerenders, saknad OnPush, tunga template-uttryck
- [ ] **PHP classes/ header injection audit** — CRLF i Location/Set-Cookie-headers
- [ ] **Angular i18n/hardcoded string audit** — icke-svenska strangar, saknade oversattningar

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

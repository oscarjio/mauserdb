# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-25)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #326):
- [ ] **PHP caching/performance audit** — redundanta DB-anrop, saknad caching, N+1-fragor (Worker A)
- [ ] **PHP API response format audit** — inkonsekvent JSON, felaktiga statuskoder, lacker info (Worker A)
- [ ] **PHP error handling depth audit** — tomma catch, saknad validering, null-risker (Worker A)
- [ ] **Angular change detection audit** — saknad OnPush, trackBy, tunga template-berakningar (Worker B)
- [ ] **Angular state management audit** — BehaviorSubject-lackor, saknade unsubscribe, race conditions (Worker B)
- [ ] **Angular routing/navigation audit** — felaktiga routes, saknade guards, lazy-loading (Worker B)

### Nasta buggjakt-items (session #327+):
- [ ] **PHP date/timezone audit** — inkonsekvent datumhantering, tidszonsfel, felaktig formatering
- [ ] **Angular HTTP error handling audit** — saknade catchError, felaktig retry-logik, timeout-hantering
- [ ] **PHP authorization audit** — saknade behorighetskontroller, privilege escalation, rollkontroll
- [ ] **Angular form validation audit** — saknad validering, felaktiga felmeddelanden, edge cases

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-25)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #316):
- [x] **PHP exception handling consistency N-Z** — rent (66 controllers, 714 catch)
- [x] **PHP SQL kolumnnamn-verifiering N-Z** — rent
- [x] **PHP date/time edge cases N-Z** — rent
- [x] **Angular subscription/timer cleanup N-Z** — rent (24 komponenter)
- [x] **PHP response Content-Type audit** — rent (api.php globalt)
- [x] **Angular HTTP error handling N-Z** — rent (54 services)

### Nasta buggjakt-items (session #317+):
- [ ] **PHP numeric precision audit** — division-med-noll, floatval-precision, intval-overflow
- [ ] **Angular route guard audit** — auth guards, canActivate, redirect-logik
- [ ] **PHP SQL transaction audit N-Z** — beginTransaction/commit/rollBack-konsistens
- [ ] **Angular form validation audit** — required-falt, min/max, felmeddelanden
- [ ] **PHP array bounds/null audit N-Z** — array_key_exists, null-checkar, tomma arrayer

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

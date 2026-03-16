# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-16)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **Buggjakt: Backend classes/ controllers batch 1** — Worker A #121 — 12 buggar i 3 controllers (av 13 granskade)
- [x] **Buggjakt: Frontend services batch 6 + komponenter** — Worker B #121 — 29 buggar i 8 services + 14 komponenter granskade
- [ ] **Buggjakt: Backend classes/ controllers batch 2** — Granska 20 classes/-controllers (Andon, Alerts, Audit, Register, Login, Admin, Tidrapport, Narvaro, MinDag, Profile, Status, News, etc)
- [ ] **Buggjakt: Backend routing/api.php** — Verifiera att alla actions routas korrekt, inga orphan-actions
- [ ] **Buggjakt: PHP helper-klasser** — AuthHelper, AuditLogger, DatabaseConnection — granska for edge cases
- [ ] **Buggjakt: Funktionstesta Rebotling-sidor** — Curl-testa alla rebotling-endpoints, verifiera JSON-svar
- [ ] **Buggjakt: Funktionstesta Funktioner-sidor** — Curl-testa OEE, kassation, operator, underhall, rapporter, visualisering

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

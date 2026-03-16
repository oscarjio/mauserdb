# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-16)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **Buggjakt: Backend controllers (skift/stopp/vd/statistik)** — Worker A #120 — 4 buggar i 3 controllers
- [x] **Buggjakt: Frontend services batch 4+5 (produktion + skift/stopp, 21 st)** — Worker B #120 — 37 buggar i 14 filer
- [ ] **Buggjakt: Frontend services batch 6** — Ovrigt: alarm-historik, alerts, andon-board, audit, avvikelselarm, daglig-sammanfattning, drifttids-timeline, kvalitetscertifikat, statistik-dashboard, statistik-overblick, underhallslogg, underhallsprognos, users, vd-dashboard, veckorapport
- [ ] **Buggjakt: Frontend components** — Granska komponent-templates for saknade null-guards, felaktiga pipes, saknade trackBy i *ngFor
- [ ] **Buggjakt: Backend routing/api.php** — Verifiera att alla actions routas korrekt, inga orphan-actions
- [ ] **Buggjakt: Backend classes/ controllers batch 1** — Granska 20 classes/-controllers (Gamification, Feedback, Bonus, Kapacitet, Leverans, Kassation, Kvalitet, Malhistorik, Effektivitet, Cykeltid, etc)
- [ ] **Buggjakt: Backend classes/ controllers batch 2** — Granska 20 classes/-controllers (Andon, Alerts, Audit, Register, Login, Admin, Tidrapport, Narvaro, MinDag, Profile, Status, News, etc)
- [ ] **Buggjakt: PHP helper-klasser** — AuthHelper, AuditLogger, DatabaseConnection — granska for edge cases

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

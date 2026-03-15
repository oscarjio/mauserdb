# Worker Backlog — MauserDB

*Lead-agenten fyller på. Workers plockar uppgifter härifrån.*
*Håll 5-10 öppna items. Markera med [x] när klart.*

## PRIORITET: BUGGJAKT (2026-03-15)

Ägaren har bett oss fokusera på att hitta och fixa buggar. Inga nya features.

- [pågår] **Buggjakt: ibc_ok-kolumn i remaining controllers** — MaskinhistorikController, RebotlingStationsdetaljController, KapacitetsplaneringController, SkiftrapportController, DagligBriefingController, StatistikOverblickController, GamificationController använder fel `ok`-kolumn
- [pågår] **Buggjakt: Frontend subscription-läckor** — granska nya components (gamification, prediktivt-underhall, daglig-briefing, skiftoverlamning, operator-dashboard, vd-dashboard) för saknad takeUntil/OnDestroy
- [ ] **Buggjakt: KassationsanalysController + ProduktionskalenderController** — granska för ibc_ok-fel + övriga SQL-buggar
- [ ] **Buggjakt: Auth & session** — granska session-hantering, rate limiting, CORS-regler
- [ ] **Buggjakt: OEE-beräkningar i fixade controllers** — verifiera att de 11 redan fixade controllers räknar OEE korrekt med nya mönstret
- [ ] **Buggjakt: API-endpoints manuell test** — testa endpoints med curl, verifiera JSON-svar

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursförbrukning
- [ ] Rebotling leveransplanering
- [ ] Rebotling avvikelsehantering
- [ ] Rebotling batch-spårning

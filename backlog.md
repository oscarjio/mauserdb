# Worker Backlog — MauserDB

*Lead-agenten fyller på. Workers plockar uppgifter härifrån.*
*Håll 5-10 öppna items. Markera med [x] när klart.*

## PRIORITET: BUGGJAKT (2026-03-15)

Ägaren har bett oss fokusera på att hitta och fixa buggar. Inga nya features.

- [x] **Buggjakt: Auth & session** — login.php/admin.php hade hårdkodade credentials utan auth, ersatta med 410 Gone. CORS, bcrypt, sessions OK.
- [x] **Buggjakt: OEE-beräkningar** — 6 controllers granskade. ProduktionskalenderController hade tillgänglighet hårdkodad till 1.0 — fixad.
- [x] **Buggjakt: API-endpoints manuell test** — 12 endpoints testade med curl. 6 fungerar, 6 kräver saknade DB-tabeller. SQL injection/XSS skyddat.
- [x] **Buggjakt: Unused variables** — $toDate, $mål, $count, FormsModule, oanvända type-imports — alla fixade.
- [x] **Buggjakt: Frontend template-fel** — prediktivt-underhall [class]-bugg fixad, oanvända imports i gamification/prediktivt-underhall fixade.
- [ ] **Buggjakt: OperatorRanking streaks** — calcStreaks() använde user_id på rebotling_ibc, fixat till UNION ALL — verifiera med riktig data
- [ ] **Buggjakt: 6 endpoints med saknade tabeller** — prediktivt-underhall, skiftoverlamning, rebotling, operators, news, bonus — kräver DB-tabeller
- [ ] **Buggjakt: PHP catch($e) cleanup** — 12 catch-block med oanvända $e i 4 controllers — byt till catch(Exception) (PHP 8+)
- [ ] **Buggjakt: Edge cases i datum-hantering** — granska controllers som gör datum-jämförelser, verifiera tidszoner, DST, årsskiften
- [ ] **Buggjakt: Frontend responsivitet** — testa alla sidor i mobil-vy, fixa overflow/layout-problem

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursförbrukning
- [ ] Rebotling batch-spårning

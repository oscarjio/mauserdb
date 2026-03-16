# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-16)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [ ] **Buggjakt: HTTP-polling race conditions** — granska setInterval-baserad polling for race conditions och dubbla anrop
- [ ] **Buggjakt: PHP type coercion** — granska loose comparisons (== vs ===) i PHP-controllers
- [ ] **Buggjakt: Angular route guards** — verifiera att alla skyddade routes har korrekta guards
- [ ] **Buggjakt: PHP return-typ konsistens** — granska att alla endpoints returnerar konsekvent JSON-format
- [ ] **Buggjakt: Frontend error-display** — verifiera att catchError visar anvandarvanliga felmeddelanden
- [ ] **Buggjakt: Date/timezone edge cases** — granska datum-hantering vid midnatt/skiftbyte

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

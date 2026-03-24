# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-24)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Nasta buggjakt-items (session #285+):
- [ ] **PHP strtotime edge cases** — felaktig parsning av relativa datum, empty string, ogiltiga format
- [ ] **Angular HTTP params encoding** — sarskilda tecken i query params, dubbel-encoding, saknade params
- [ ] **PHP array_merge vs + operator** — nyckelkollisioner, numeriska nycklar re-indexeras
- [ ] **Angular number formatting** — DecimalPipe locale, NaN/Infinity i templates, parseFloat av user input
- [ ] **PHP PDO fetchAll memory** — stora resultatmangder utan LIMIT, fetchAll vs fetch i loop

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-25)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #308):
- [ ] **PHP array_map/array_filter callbacks** — felaktiga callbacks som tyst returnerar null (Worker A)
- [ ] **PHP PDO fetch mode consistency** — blandning av FETCH_ASSOC/FETCH_OBJ i controllers (Worker A)
- [ ] **PHP SQL DATE() i WHERE (forts)** — ~70+ kvarvarande DATE()-anrop i sallananropade controllers (Worker A)
- [ ] **Angular Chart.js update vs destroy** — grafer som uppdateras utan att forst destroya gammal instans (Worker B)
- [ ] **Angular OnDestroy cleanup djupgranskning** — setInterval/Subject/addEventListener cleanup (Worker B)
- [ ] **Angular HTTP error handling i komponenter** — subscribe utan error callback, saknad loading-state reset (Worker B)

### Nasta buggjakt-items (session #309+):
- [ ] **PHP SQL implicit type conversion** — WHERE string-kolumn = integer (saknar quotes)
- [ ] **PHP exception handling granularity** — breda catch(Exception) som doljer specifika fel
- [ ] **Angular ngIf/ngSwitch exhaustiveness** — switch-satser utan default/else-fall
- [ ] **Angular input sanitization** — DomSanitizer anvandning, innerHTML utan sanitering

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

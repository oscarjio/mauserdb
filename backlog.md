# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-24)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #289):
- [ ] **PHP mail() injektionsrisk** — sanitering av mail-headers, CRLF-injection
- [ ] **Angular unsubscribed Observables** — manuella subscribe() utan takeUntil/unsubscribe
- [ ] **PHP date/time edge cases** — DST/timezone-problem, strtotime edge cases
- [ ] **Angular template null dereference** — saknade ?. operatorer i templates
- [ ] **PHP array bounds** — array-access utan isset/array_key_exists-guard

### Nasta buggjakt-items (session #290+):
- [ ] **PHP header() redirect consistency** — kontrollera exit/die efter header(Location:) i alla controllers
- [ ] **Angular ngIf race conditions** — async data som renderas fore HTTP-svar (strukturella direktiv)
- [ ] **PHP PDO fetchColumn edge cases** — fetchColumn(0) nar query returnerar 0 rader (false vs 0)
- [ ] **Angular router memory** — komponenter som prenumererar pa router events utan cleanup
- [ ] **PHP error suppression (@)** — granska anvandning av @ operator som doljer fel

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

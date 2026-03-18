# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-18)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #168):
- [x] **PHP response consistency audit** — 1 bugg: AuditController felformat (Worker A)
- [x] **PHP error logging completeness audit** — 4 buggar: VDVeckorapport 3x + RebotlingAdmin (Worker A)
- [x] **PHP integer overflow/type coercion audit** — 3 buggar: float === 0.0 i 5 controllers (Worker A)
- [x] **Angular HTTP error message audit** — 4 buggar: saknad felvisning i 4 komponenter (Worker B)
- [x] **Angular form reset/dirty state audit** — 1 bugg: produktionsmal formreset (Worker B)

### Nasta buggjakt-items (session #169+):
- [ ] **PHP file path traversal audit** — saknad validering av filnamn/sokvagar i upload/export
- [ ] **Angular memory leak re-audit** — nya komponenter sedan session #166
- [ ] **PHP date/time edge case audit** — timezone-hantering, DST-overgangsproblem
- [ ] **Angular accessibility audit** — saknade aria-labels, keyboard navigation
- [ ] **PHP SQL transaction completeness audit** — multi-table writes utan transaktion

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-23)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #269):
- [ ] **PHP header injection** — Worker A — saknad validering av user input i header()-anrop
- [ ] **PHP numeric validation** — Worker A — is_numeric vs ctype_digit vs intval vid ID-parametrar
- [ ] **PHP mail/SMTP safety** — Worker A — header injection i mail()-anrop, saknad sanitering
- [ ] **Angular form dirty state** — Worker B — canDeactivate guards, osparade andringar
- [ ] **Angular template type safety** — Worker B — null-check, optional chaining, runtime errors

### Nasta buggjakt-items (session #270+):
- [ ] **PHP output buffering** — ob_start/ob_end konsistens, saknade ob_clean vid errors
- [ ] **Angular route resolver errors** — felhantering i resolvers, loading states
- [ ] **PHP session race conditions** — session_write_close vid langa requests
- [ ] **Angular SSR compatibility** — window/document-access utan isPlatformBrowser
- [ ] **PHP CORS preflight** — OPTIONS-requests hanteras korrekt i alla endpoints

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning

# MauserDB API-dokumentation

Bas-URL: `https://dev.mauserdb.com/noreko-backend/api.php?action=<ACTION>`

Alla endpoints returnerar JSON med `Content-Type: application/json; charset=utf-8`.
Framgangsrika svar: `{ "success": true, ... }`. Felsvar: `{ "success": false, "error": "..." }`.

## Autentisering
- Session-baserad (PHP-sessioner med httponly cookies)
- POST/PUT/DELETE kraver giltig session + CSRF-token (X-CSRF-Token header)
- CSRF-token returneras vid inloggning
- Session timeout: 8 timmar

## CORS
- Tillater localhost:4200, mauserdb.com och subdomaner
- Stodjer preflight OPTIONS-requests

---

## Publika endpoints (ingen inloggning kravs)

### Autentisering & konto

| Action | Metod | Run/Sub | Parametrar | Beskrivning |
|--------|-------|---------|------------|-------------|
| `login` | POST | - | username, password | Logga in, returnerar CSRF-token |
| `register` | POST | - | username, email, password | Registrera nytt konto |
| `status` | GET | - | - | Kontrollera inloggningsstatus |

### Live-data (PLC)

| Action | Metod | Run/Sub | Parametrar | Beskrivning |
|--------|-------|---------|------------|-------------|
| `rebotling` | GET | (diverse) | run=... | Rebotling produktionsdata |
| `tvattlinje` | GET | - | - | Tvattlinje live-data |
| `saglinje` | GET | - | - | Saglinje live-data |
| `klassificeringslinje` | GET | - | - | Klassificeringslinje live-data |

### Historik

| Action | Metod | Run/Sub | Parametrar | Beskrivning |
|--------|-------|---------|------------|-------------|
| `historik` | GET | monthly | manader=1-60 | Manatlig produktionshistorik |
| `historik` | GET | yearly | - | Arlig produktionshistorik |

---

## Inloggningsskyddade endpoints (auth kravs)

### Profil & anvandare

| Action | Metod | Run/Sub | Parametrar | Beskrivning |
|--------|-------|---------|------------|-------------|
| `profile` | GET | - | - | Hamta egen profil |
| `profile` | POST | - | email, currentPassword, newPassword, operator_id | Uppdatera profil/losenord |

### Rebotling — Produktion & analys

| Action | Metod | Run/Sub | Parametrar | Beskrivning |
|--------|-------|---------|------------|-------------|
| `rebotling` | GET | live-stats | - | Realtidsstatistik fran PLC |
| `rebotling` | GET | oee | period=today/week/month | OEE-berakning |
| `rebotling` | GET | day-history | start, end | Daglig historik |
| `rebotling` | GET | trend | period, from_date, to_date, days, granularity | Trenddata |
| `rebotling` | GET | shift-stats | - | Skiftstatistik |
| `rebotling` | GET | records | - | Rekordnoteringar |
| `rebotling` | POST | delete-event | id | Radera PLC-haendelse |
| `rebotlingproduct` | GET/POST/PUT/DELETE | - | - | CRUD for rebotling-produkter |
| `rebotling-stationsdetalj` | GET | stationer/kpi-idag/senaste-ibc/stopphistorik/oee-trend/realtid-oee | - | Detaljerad stationsdata |
| `rebotling-sammanfattning` | GET | overview/produktion-7d/maskin-status | - | Sammanfattning rebotling |
| `rebotlingtrendanalys` | GET | trender/daglig-historik/veckosammanfattning/anomalier/prognos | - | Trendanalys |
| `historisk-produktion` | GET | overview/produktion-per-period/jamforelse/detalj-tabell | from, to, period | Historisk produktion |
| `historisk-sammanfattning` | GET | perioder/rapport/trend/operatorer/stationer/stopporsaker | from, to | Historisk sammanfattning |

### OEE

| Action | Metod | Run/Sub | Parametrar | Beskrivning |
|--------|-------|---------|------------|-------------|
| `oee-trendanalys` | GET | sammanfattning/per-station/trend/flaskhalsar/jamforelse/prediktion | days, from1, to1, from2, to2 | OEE-trendanalys |
| `oee-waterfall` | GET | waterfall-data/summary | days | OEE waterfall-diagram |
| `oee-benchmark` | GET | current-oee/benchmark/trend/breakdown | days | OEE benchmarking |
| `oee-jamforelse` | GET | weekly-oee | - | OEE veckovis jamforelse |
| `maskin-oee` | GET | overview/per-maskin/trend/benchmark/detalj/maskiner | days, period | OEE per maskin |

### Kassation

| Action | Metod | Run/Sub | Parametrar | Beskrivning |
|--------|-------|---------|------------|-------------|
| `kassationsanalys` | GET | summary/by-cause/daily-stacked/drilldown/overview/by-period/details/trend-rate/sammanfattning/orsaker/... | days, cause, group, orsak, operator, limit | Kassationsanalys |
| `kassations-drilldown` | GET | overview/reason-detail/trend | days, reason | Kassations-drilldown |
| `kassationsorsakstatistik` | GET | overview/pareto/trend/per-operator/per-shift/drilldown | days, orsak | Kassationsorsaksstatistik |
| `kassationsorsak-per-station` | GET | overview/per-station/top-orsaker/trend/detaljer | dagar, station | Kassation per station |
| `kassationskvotalarm` | GET/POST | aktuell-kvot/alarm-historik/troskel-hamta/timvis-trend/per-skift/top-orsaker | dagar | Kassationskvot-alarm |
| `kvalitetstrendbrott` | GET | overview/alerts/daily-detail | days | Kvalitetstrendbrott |
| `kvalitetstrend` | GET | overview/operators/operator-detail | days | Kvalitetstrend |
| `kvalitetstrendanalys` | GET | overview/per-station-trend/per-operator/alarm/heatmap | days | Kvalitetstrendanalys |
| `kvalitetscertifikat` | GET/POST | overview/lista/detalj/kriterier/statistik/generera/bedom/uppdatera-kriterier | - | Kvalitetscertifikat |

### Stopporsaker

| Action | Metod | Run/Sub | Parametrar | Beskrivning |
|--------|-------|---------|------------|-------------|
| `stoppage` | GET/POST | - | period=today/week/month/year | Stopploggar CRUD |
| `stopporsak-dashboard` | GET | sammanfattning/pareto/per-station/trend/orsaker-tabell/detaljer | days | Stopporsak-dashboard |
| `stopporsak-trend` | GET | weekly/summary/detail | weeks, reason | Stopporsak-trend |
| `stopporsak-reg` | POST | - | - | Registrera stopporsak |
| `stopporsak-operator` | GET | overview/operator-detail/reasons-summary | days | Stopporsak per operator |
| `stopptidsanalys` | GET | overview/per-maskin/trend/fordelning/detaljtabell/maskiner | days, period | Stopptidsanalys |

### Operator & personal

| Action | Metod | Run/Sub | Parametrar | Beskrivning |
|--------|-------|---------|------------|-------------|
| `operators`/`operator` | GET/POST | - | - | CRUD for operatorer |
| `operator-dashboard` | GET | today/weekly/history/summary/operatorer/min-produktion/mitt-tempo/min-bonus/mina-stopp/min-veckotrend | - | Operator-dashboard |
| `operator-compare` | GET | - | op1, op2 | Jamnfor tva operatorer |
| `operator-jamforelse` | GET | operators-list/compare/compare-trend | operators, period | Operator-jamforelse |
| `operator-onboarding` | GET | overview/operator-curve/team-stats | days | Operator-onboarding |
| `operator-ranking` | GET | sammanfattning/ranking/topplista/poangfordelning/historik/mvp/idag/vecka/manad/30d | days | Operator-ranking |
| `operatorsprestanda` | GET | scatter-data/operator-detalj/ranking/teamjamforelse/utveckling | period, skift, operator_id | Operators-prestanda |
| `my-stats` | GET | my-stats/my-trend/my-achievements | period | Personlig statistik |
| `min-dag` | GET | today-summary/cycle-trend/goals-progress | - | Min dag-oversikt |
| `operatorsportal` | GET | my-stats/my-trend/my-bonus | days | Operatorsportalen |

### Skift

| Action | Metod | Run/Sub | Parametrar | Beskrivning |
|--------|-------|---------|------------|-------------|
| `skiftrapport` | GET/POST | - | - | Skiftrapporter CRUD |
| `skiftrapport-export` | GET | report-data/multi-day | - | Exportera skiftrapporter |
| `lineskiftrapport` | GET/POST | create/update/delete/updateInlagd/bulkDelete/bulkUpdateInlagd | line=tvattlinje/saglinje/klassificeringslinje | Linje-skiftrapporter |
| `skiftjamforelse` | GET | sammanfattning/jamforelse/trend/best-practices/detaljer/shift-comparison/shift-trend/shift-operators | days | Skiftjamforelse |
| `skiftoverlamning` | GET/POST | list/detail/shift-kpis/summary/operators/aktuellt-skift/... | - | Skiftoverlamning |
| `shift-handover` | GET/POST | (diverse) | - | Skiftoverlamning (alt.) |
| `shift-plan` | GET/POST | - | - | Skiftplanering |
| `skiftplanering` | GET/POST | overview/schedule/shift-detail/capacity/operators/assign/unassign | week, shift, date | Skiftplanering (utokad) |

### Bonus

| Action | Metod | Run/Sub | Parametrar | Beskrivning |
|--------|-------|---------|------------|-------------|
| `bonus` | GET/POST | simulate/operator/ranking/team/kpis/history/summary/weekly_history/hall-of-fame/... | period, start_date, end_date | Bonussystem |
| `bonusadmin` | GET/POST | get_config/update_weights/set_targets/get_periods/export_report/approve_bonuses/... | - | Bonusadministration |
| `operatorsbonus` | GET/POST | overview/per-operator/konfiguration/historik/simulering/spara-konfiguration | period, operator_id | Operatorsbonus |

### Produktion — ovrigt

| Action | Metod | Run/Sub | Parametrar | Beskrivning |
|--------|-------|---------|------------|-------------|
| `produktionsmal` | POST | aktuellt-mal/progress/satt-mal/mal-historik/sammanfattning/per-skift/veckodata/... | - | Produktionsmal |
| `produktionspuls` | GET | latest/hourly-stats/pulse/live-kpi | limit | Produktionspuls |
| `produktionsprognos` | GET | forecast/shift-history | - | Produktionsprognos |
| `produktionseffektivitet` | GET | hourly-heatmap/hourly-summary/peak-analysis | period | Produktionseffektivitet |
| `produktionsflode` | GET | overview/flode-data/station-detaljer | days | Produktionsflode |
| `produktionskalender` | GET | month-data/day-detail | year, month, date | Produktionskalender |
| `produktionskostnad` | GET/POST | overview/breakdown/trend/daily-table/shift-comparison/config/update-config | days, period | Produktionskostnad |
| `produktions-sla` | GET/POST | overview/daily-progress/weekly-progress/history/goals/set-goal | days | Produktions-SLA |
| `produktionstakt` | POST | current-rate/hourly-history/get-target/set-target | - | Produktionstakt |
| `produktionsdashboard` | GET | oversikt/vecko-produktion/vecko-oee/stationer-status/senaste-alarm/senaste-ibc | - | Produktions-dashboard |
| `produkttyp-effektivitet` | GET | summary/trend/comparison | days, a, b | Produkttyp-effektivitet |

### Maskin & underhall

| Action | Metod | Run/Sub | Parametrar | Beskrivning |
|--------|-------|---------|------------|-------------|
| `underhallslogg` | GET/POST | categories/list/stats/lista/sammanfattning/per-station/manadschart/stationer/log/delete/skapa/ta-bort | station, typ, from, to, days, limit | Underhallslogg |
| `underhallsprognos` | GET | overview/schedule/history | days | Underhallsprognos |
| `maskinunderhall` | GET/POST | overview/machines/machine-history/timeline/add-service/add-machine | maskin_id | Maskinunderhall |
| `maskin-drifttid` | GET | heatmap/kpi/dag-detalj/stationer | days | Maskin-drifttid |
| `maskinhistorik` | GET | stationer/station-kpi/station-drifttid/station-oee-trend/station-stopp/jamforelse | period, station, limit | Maskinhistorik |
| `maintenance` | GET/POST | - | - | Underhallslogg (alt.) |

### Kapacitet & planering

| Action | Metod | Run/Sub | Parametrar | Beskrivning |
|--------|-------|---------|------------|-------------|
| `kapacitetsplanering` | GET | kpi/daglig-kapacitet/station-utnyttjande/stopporsaker/tid-fordelning/vecko-oversikt/... | period | Kapacitetsplanering |
| `leveransplanering` | GET/POST | overview/ordrar/kapacitet/prognos/konfiguration/skapa-order/uppdatera-order | - | Leveransplanering |
| `utnyttjandegrad` | GET | summary/daily/losses | days | Utnyttjandegrad |

### Heatmap & visualisering

| Action | Metod | Run/Sub | Parametrar | Beskrivning |
|--------|-------|---------|------------|-------------|
| `cykeltid-heatmap` | GET | heatmap/day-pattern/operator-detail | days, operator_id | Cykeltid-heatmap |
| `heatmap` | GET | heatmap-data/summary | days | Produktions-heatmap |
| `pareto` | GET | pareto-data/summary | days | Pareto-diagram |

### Drifttid

| Action | Metod | Run/Sub | Parametrar | Beskrivning |
|--------|-------|---------|------------|-------------|
| `drifttids-timeline` | GET | timeline-data/summary | date | Drifttids-timeline |
| `effektivitet` | GET | trend/summary/by-shift | days | Effektivitetsanalys |
| `runtime` | GET/POST | stats/today | line | Rast/drifttidsregistrering |

### Rapporter

| Action | Metod | Run/Sub | Parametrar | Beskrivning |
|--------|-------|---------|------------|-------------|
| `veckorapport` | GET | report | week | Veckorapport |
| `weekly-report` | GET | report | week | Veckorapport (alt.) |
| `vd-veckorapport` | GET | kpi-jamforelse/trender-anomalier/top-bottom-operatorer/stopporsaker/vecka-sammanfattning | - | VD-veckorapport |
| `morgonrapport` | GET | rapport | date | Morgonrapport |
| `daglig-sammanfattning` | GET | daily-summary/comparison | date | Daglig sammanfattning |
| `daglig-briefing` | GET | sammanfattning/stopporsaker/stationsstatus/veckotrend/bemanning | - | Daglig briefing |
| `malhistorik` | GET | goal-history/goal-impact | days | Malhistorik |
| `forsta-timme-analys` | GET | analysis/trend | days | Forsta timme-analys |

### VD & ledning

| Action | Metod | Run/Sub | Parametrar | Beskrivning |
|--------|-------|---------|------------|-------------|
| `vd-dashboard` | GET | oversikt/stopp-nu/top-operatorer/station-oee/veckotrend/skiftstatus | - | VD-dashboard |
| `statistik-overblick` | GET | kpi/produktion/oee/kassation | days | Statistik-overblick |
| `statistikdashboard` | GET | summary/production-trend/daily-table/status-indicator | days | Statistik-dashboard |

### Kommunikation & nyheter

| Action | Metod | Run/Sub | Parametrar | Beskrivning |
|--------|-------|---------|------------|-------------|
| `news` | GET/POST | (diverse) | antal, category | Nyhetsflode |
| `andon` | GET/POST | - | - | Andon-system |
| `feedback` | GET/POST | submit/my-history/summary | - | Feedback |
| `feedback-analys` | GET | feedback-list/feedback-stats/feedback-trend/operator-sentiment | - | Feedback-analys |

### Alarm & notiser

| Action | Metod | Run/Sub | Parametrar | Beskrivning |
|--------|-------|---------|------------|-------------|
| `alerts` | GET/POST | active/history/acknowledge/settings/check | - | Alarmsystem |
| `alarm-historik` | GET | list/summary/timeline | days, status, severity, typ | Alarm-historik |
| `avvikelselarm` | POST | overview/aktiva/historik/kvittera/regler/uppdatera-regel/trend | period | Avvikelselarm |

### Ranking & gamification

| Action | Metod | Run/Sub | Parametrar | Beskrivning |
|--------|-------|---------|------------|-------------|
| `ranking-historik` | GET | weekly-rankings/ranking-changes/streak-data | - | Ranking-historik |
| `gamification` | GET | leaderboard/badges/min-profil/overview | period | Gamification |

### Diverse

| Action | Metod | Run/Sub | Parametrar | Beskrivning |
|--------|-------|---------|------------|-------------|
| `narvaro` | GET | - | year, month | Narvarodata |
| `dashboard-layout` | GET/POST | get-layout/save-layout/available-widgets | - | Dashboard-layout |
| `favoriter` | GET/POST | list/add/remove/reorder | - | Favoriter |
| `batch-sparning` | GET/POST | overview/active-batches/batch-detail/batch-history/create-batch/complete-batch | batch_id, from, to, search | Batch-sparning |
| `tidrapport` | GET | sammanfattning/per-operator/veckodata/detaljer/export-csv | period, from, to | Tidrapporter |
| `prediktivt-underhall` | GET | heatmap/mtbf/trender/rekommendationer | days | Prediktivt underhall |
| `feature-flags` | GET/POST | list/update/bulk-update | - | Feature flags |

### Administration (admin-roll kravs)

| Action | Metod | Run/Sub | Parametrar | Beskrivning |
|--------|-------|---------|------------|-------------|
| `admin` | GET | list | - | Lista alla anvandare |
| `admin` | POST | create | username, email, password, admin | Skapa anvandare |
| `admin` | POST | delete | id | Radera anvandare |
| `admin` | POST | toggleAdmin | id | Toggla admin-roll |
| `admin` | POST | toggleActive | id | Aktivera/inaktivera anvandare |
| `admin` | POST | update | id, username, email, phone, password, operator_id | Uppdatera anvandare |
| `audit` | GET | - | page, limit, filter_action, filter_user, filter_entity, search, period, from_date, to_date | Granskningslogg |
| `vpn` | GET/POST | - | - | VPN-hantering |
| `certifications`/`certification` | GET/POST | - | - | Operatorscertifieringar |

---

## Svarsformat

Alla svar anvander konsekvent JSON-format:

```json
{
  "success": true,
  "data": { ... },
  "timestamp": "2026-03-27 12:00:00"
}
```

Felsvar:
```json
{
  "success": false,
  "error": "Beskrivande felmeddelande pa svenska"
}
```

## Sakerhetsheaders

Alla svar inkluderar:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Content-Security-Policy: default-src 'self'; ...`
- `Strict-Transport-Security: max-age=31536000; includeSubDomains` (HTTPS)
- `Cache-Control: no-store, no-cache, must-revalidate, private`

## Totalt antal endpoints

- **117 unika action-varden** i classNameMap
- **~500+ run-subendpoints** totalt
- **~60+ controllers** i classes/

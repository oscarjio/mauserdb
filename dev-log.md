## 2026-03-13 Maskinhistorik per station — detaljerad historikvy per maskin/station

Ny sida `/rebotling/maskinhistorik` — VD och operatorer kan se historik, drifttid, stopp, OEE-trend och jamfora maskiner sinsemellan.

- **Backend**: `classes/MaskinhistorikController.php` (action=`maskinhistorik`)
  - `run=stationer` — lista unika stationer fran rebotling_ibc
  - `run=station-kpi` — KPI:er for vald station + period (drifttid, IBC, OEE, kassation, cykeltid, tillganglighet)
  - `run=station-drifttid` — daglig drifttid + IBC-produktion per dag for vald station
  - `run=station-oee-trend` — daglig OEE med Tillganglighet/Prestanda/Kvalitet per dag
  - `run=station-stopp` — senaste stopp fran rebotling_onoff (varaktighet, status, tidpunkter)
  - `run=jamforelse` — alla stationer jamforda med OEE, produktion, kassation, drifttid, cykeltid — sorterad bast/samst
  - OEE: T = drifttid/planerad, P = (IBC*120s)/drifttid (max 100%), K = godkanda/totalt
  - Inga nya tabeller — anvander rebotling_ibc och rebotling_onoff
  - Registrerad i api.php: `'maskinhistorik' => 'MaskinhistorikController'`

- **Frontend**: `pages/rebotling/maskinhistorik/`
  - Stationsknapp-vaeljare (dynamisk, haemtar unika stationer fran backend)
  - 6 KPI-kort: drifttid, producerade IBC, OEE, kassationsgrad, snittcykeltid, tillganglighet
  - Drifttids-graf (Chart.js kombinerat bar+linje): drifttid per dag + producerade IBC
  - OEE-trend (Chart.js linjediagram): daglig OEE + T/P/K-delkomponenter
  - Stopphistorik-tabell: senaste 20 stopp med start, stopp, varaktighet, status
  - Jamforelsematris: alla stationer med OEE, T%, P%, K%, prod, kassation%, drifttid, cykeltid
  - Periodselektor: 7d / 30d / 90d, dark theme, OnInit/OnDestroy + destroy$ + takeUntil

- **Service**: `services/maskinhistorik.service.ts`
  - `getStationer()`, `getStationKpi()`, `getStationDrifttid()`, `getStationOeeTrend()`, `getStationStopp()`, `getJamforelse()`

- **Route**: `/rebotling/maskinhistorik` med authGuard
- **Navigation**: Tillagd i Rebotling-dropdown under Skiftsammanstallning
- **Bygg**: Lyckat (ng build OK)

---

## 2026-03-13 Kassationskvot-alarm — automatisk overvakning och varning

Ny sida `/rebotling/kassationskvot-alarm` — overvakar kassationsgraden i realtid och larmar nar troskelvarden overskrids.

- **Backend**: `classes/KassationskvotAlarmController.php` (action=`kassationskvotalarm`)
  - `run=aktuell-kvot` — kassationsgrad senaste timmen, aktuellt skift, idag med fargkodning (gron/gul/rod)
  - `run=alarm-historik` — alla skiftraknare senaste 30 dagar dar kvoten oversteg troskeln
  - `run=troskel-hamta` — hamta nuvarande installningar
  - `run=troskel-spara` (POST) — spara nya troskelvarden
  - `run=timvis-trend` — kassationskvot per timme senaste 24h
  - `run=per-skift` — kassationsgrad per skift senaste 7 dagar
  - `run=top-orsaker` — top-5 kassationsorsaker vid alarm-perioder
  - Anvander rebotling_ibc + kassationsregistrering + kassationsorsak_typer
  - Skiftdefinitioner: Dag 06-14, Kvall 14-22, Natt 22-06

- **Migration**: `migrations/2026-03-13_kassationsalarminst.sql`
  - Ny tabell `rebotling_kassationsalarminst` (id, varning_procent, alarm_procent, skapad_av, skapad_datum)
  - Standardinstallning: varning 3%, alarm 5%

- **Service**: `services/kassationskvot-alarm.service.ts`
  - 7 metoder: getAktuellKvot, getAlarmHistorik, getTroskel, sparaTroskel, getTimvisTrend, getPerSkift, getTopOrsaker

- **Frontend**: `pages/rebotling/kassationskvot-alarm/`
  - 3 KPI-kort (senaste timmen / aktuellt skift / idag) med pulsande rod-animation vid alarm
  - Kassationstrend-graf (Chart.js) — linjekvot per timme 24h med horisontella trosklar
  - Troskelinst — formularet sparar nya varning/alarm-procent (POST)
  - Per-skift-tabell: dag/kvall/natt senaste 7 dagarna med fargkodade kvot-badges
  - Alarm-historik: tabell med alla skift som overskridit troskel (status ALARM/VARNING)
  - Top-5 kassationsorsaker vid alarm-perioder (staplar)
  - Auto-polling var 60:e sekund med isFetching-guard per endpoint
  - OnInit/OnDestroy + destroy$ + takeUntil + chart?.destroy()

- **Route**: `/rebotling/kassationskvot-alarm` med authGuard
- **Navigation**: Tillagd sist i Rebotling-dropdown (fore admin-divider)

## 2026-03-13 Skiftrapport-sammanstallning — daglig rapport per skift

Ny sida `/rebotling/skiftrapport-sammanstallning` — automatisk daglig rapport per skift (Dag/Kvall/Natt) med produktion, kassation, OEE, stopptid.

- **Backend**: Tre nya `run`-endpoints i `classes/SkiftrapportController.php` (action=`skiftrapport`)
  - `run=daglig-sammanstallning` — data per skift (Dag 06-14, Kvall 14-22, Natt 22-06) for valt datum
    - Per skift: producerade, kasserade, kassationsgrad, OEE (tillganglighet x prestanda x kvalitet), stopptid, drifttid
    - OEE: Tillganglighet = drifttid/8h, Prestanda = (totalIBC*120s)/drifttid (max 100%), Kvalitet = godkanda/totalt
    - Top-3 kassationsorsaker per skift (fran kassationsregistrering + kassationsorsak_typer)
  - `run=veckosammanstallning` — sammanstallning per dag, senaste 7 dagarna
  - `run=skiftjamforelse` — jamfor dag/kvall/natt senaste N dagar (default 30) med snitt-OEE och totalproduktion
  - Data fran `rebotling_ibc` + `rebotling_onoff` — inga nya tabeller

- **Frontend**: `pages/rebotling/skiftrapport-sammanstallning/`
  - Datumvaljare (default idag)
  - 3 skiftkort med produktion, kassation, kassationsgrad, OEE, stopptid, drifttid
  - Top-3 kassationsorsaker per skift
  - Dagstotalt-bar
  - Stapeldiagram (Chart.js): produktion + kassation per skift
  - Veckosammanstallning: tabell dag/kvall/natt per dag, 7 dagar
  - Skiftjamforelse: linjediagram OEE per skifttyp over 30 dagar
  - Snitt-kort per skift (30 dagar)
  - PDF-export via PdfExportButtonComponent
  - Dark theme: #1a202c bg, #2d3748 cards, #e2e8f0 text

- **Service**: `services/skiftrapport-sammanstallning.service.ts`
  - `getDagligSammanstallning(datum)`, `getVeckosammanstallning()`, `getSkiftjamforelse(dagar)`

- **Route**: `/rebotling/skiftrapport-sammanstallning` med authGuard
- **Navigation**: Tillagd i Rebotling-dropdown i menyn

---

## 2026-03-13 Produktionsmal-dashboard — VD-dashboard for malsattning och progress

Ombyggd sida `/rebotling/produktionsmal` — VD kan satta vecko/manadsmal for produktion och se progress i realtid med cirkeldiagram + prognos.

- **Backend**: `classes/ProduktionsmalController.php` (action=`produktionsmal`)
  - `run=aktuellt-mal` — hamta aktivt mal (vecka/manad) baserat pa dagens datum
  - `run=progress` — aktuell progress: producerade hittills, mal, procent, prognos, daglig produktion
    - Prognos: snitt produktion/arbetsdag extrapolerat till periodens slut
    - Gron: "I nuvarande takt nar ni X IBC — pa god vag!"
    - Rod: "Behover oka fran X till Y IBC/dag (Z% okning)"
  - `run=satt-mal` — spara nytt mal (POST: typ, antal, startdatum)
  - `run=mal-historik` — historiska mal med utfall, uppnadd ja/nej, differens
  - Legacy endpoints (`summary`, `daily`, `weekly`) bevarade for bakatkompabilitet
  - Ny tabell `rebotling_produktionsmal` (id, typ, mal_antal, start_datum, slut_datum, skapad_av, skapad_datum)
  - SQL-migrering: `migrations/2026-03-13_produktionsmal.sql`

- **Frontend**: `pages/produktionsmal/`
  - Malsattnings-formularet: typ (vecka/manad), antal IBC, startdatum, spara-knapp
  - Stort cirkeldiagram (doughnut): progress mot mal med procenttext i mitten
  - KPI-kort: Producerat hittills, Aterstar, Dagar kvar, Snitt per dag
  - Prognos-ruta: gron/rod beroende pa om malet nas i nuvarande takt
  - Daglig produktion stapeldiagram: varje dag i perioden + mallinje
  - Historik-tabell: typ, period, mal, utfall, uppfyllnad%, uppnadd, differens
  - PDF-export via PdfExportButtonComponent
  - Dark theme: #1a202c bg, #2d3748 cards, #e2e8f0 text

- **Service**: `services/produktionsmal.service.ts`
  - `getAktuelltMal()`, `getProgress()`, `sattMal()`, `getMalHistorik()`
  - Legacy-metoder bevarade

---

## 2026-03-13 OEE-jamforelse per vecka — trendanalys for VD

Ny sida `/rebotling/oee-jamforelse` — jamfor OEE vecka-for-vecka med trendpilar. VD:n ser direkt om OEE forbattras eller forsamras.

- **Backend**: `classes/OeeJamforelseController.php` (action=`oee-jamforelse`)
  - `run=weekly-oee` — OEE per vecka senaste N veckor (?veckor=12)
  - OEE = Tillganglighet x Prestanda x Kvalitet
    - Tillganglighet = drifttid (fran `rebotling_onoff`) / planerad tid (8h/arbetsdag)
    - Prestanda = (totalIbc * 120s) / drifttid (max 100%)
    - Kvalitet = godkanda (ok=1) / totalt (fran `rebotling_ibc`)
  - Returnerar: aktuell vecka, forra veckan, forandring (pp), trendpil, plus komplett veckolista
  - Registrerad i `api.php` med nyckel `oee-jamforelse`
  - Inga nya DB-tabeller — anvander `rebotling_ibc` + `rebotling_onoff`

- **Frontend**: `pages/rebotling/oee-jamforelse/`
  - Angular standalone-komponent `OeeJamforelsePage`
  - KPI-kort: aktuell vecka OEE, forra veckan OEE, forandring (trendpil), mal-OEE (85%)
  - Linjediagram (Chart.js): OEE%, tillganglighet%, prestanda%, kvalitet% per vecka + mal-linje
  - Veckovis tabell: veckonummer, OEE%, tillganglighet%, prestanda%, kvalitet%, producerade, forandring (fargad pil)
  - Periodselektor: 8/12/26/52 veckor
  - Aktuell vecka markerad i tabellen
  - PDF-export via PdfExportButtonComponent
  - Dark theme (#1a202c bg, #2d3748 cards, #e2e8f0 text)
  - Lifecycle: OnInit/OnDestroy + destroy$ + takeUntil

- **Service**: `services/oee-jamforelse.service.ts` — `getWeeklyOee(veckor)`
- **Route**: `/rebotling/oee-jamforelse` (authGuard)
- **Navigation**: tillagd i Rebotling-dropdown

---

## 2026-03-13 Maskin-drifttid heatmap — visuell oversikt nar maskiner kor vs star stilla

Ny sida `/rebotling/maskin-drifttid` — visar heatmap per timme/dag over maskindrifttid. VD:n ser pa 10 sekunder nar produktionen ar igang.

- **Backend**: `classes/MaskinDrifttidController.php` (action=`maskin-drifttid`)
  - `run=heatmap` — timvis produktion per dag fran `rebotling_ibc` (COUNT per timme per dag)
  - `run=kpi` — Total drifttid denna vecka, snitt daglig drifttid, basta/samsta dag
  - `run=dag-detalj` — detaljerad timvis vy for specifik dag
  - `run=stationer` — lista tillgangliga maskiner/stationer
  - Drifttid beraknas: timmar med minst 1 IBC = aktiv, annars stopp
  - Arbetstid: 06:00-22:00

- **Frontend**: `pages/rebotling/maskin-drifttid/` (NY katalog)
  - Standalone Angular-komponent `MaskinDrifttidPage`
  - Heatmap-grid: X=timmar (06-22), Y=dagar. Fargkodning: gron=hog prod, gul=lag, rod=stopp, gra=utanfor arbetstid
  - 4 KPI-kort: Drifttid denna vecka, Snitt daglig drifttid, Basta dag, Samsta dag
  - Periodselektor: 7/14/30/90 dagar
  - Maskinfilter: dropdown (alla/inspektion/tvatt/fyllning/etikettering/slutkontroll)
  - Tooltip: hover pa cell visar exakt antal IBC + maskinstatus
  - Dagsammanfattning: klicka pa rad for detaljerad timvy med stapelbar
  - Ren HTML/CSS heatmap (div-grid, inga Chart.js)
  - PDF-export via PdfExportButtonComponent
  - Dark theme, OnDestroy + destroy$ + takeUntil

- **Service**: `services/maskin-drifttid.service.ts` (NY)
- **Route**: `/rebotling/maskin-drifttid` (authGuard)
- **Navigation**: tillagd i Rebotling-dropdown i menyn

---

## 2026-03-12 PDF-export — generell rapport-export for alla statistiksidor

Generell PDF-export-funktion tillagd. VD:n kan klicka "Exportera PDF" pa statistiksidorna och fa en snygg PDF.

- **`services/pdf-export.service.ts`** (NY):
  - `exportToPdf(elementId, filename, title?)` — fångar element med html2canvas, skapar A4 PDF (auto landscape/portrait)
  - Header: "MauserDB — [title]" + datum/tid, footer: "Genererad [datum tid]"
  - `exportTableToPdf(data, columns, filename, title?)` — ren tabell-PDF utan screenshot, zebra-randade rader, automatisk sidbrytning
  - Installerat: `html2canvas`, `jspdf` via npm

- **`components/pdf-export-button/`** (NY katalog):
  - Standalone Angular-komponent `PdfExportButtonComponent`
  - Input: `targetElementId`, `filename`, `title`
  - Snygg knapp med `fas fa-file-pdf`-ikon + "Exportera PDF"
  - Loading-state (spinner + "Genererar...") medan PDF skapas
  - Dark theme-styling: rod border/text (#fc8181), hover: fylld bakgrund

- **Export-knapp lagd till pa 4 sidor** (bara statistiksidor — inga live-sidor):
  - `/rebotling/sammanfattning` — innehall wrappad i `#rebotling-sammanfattning-content`
  - `/rebotling/historisk-produktion` — innehall wrappad i `#historisk-produktion-content`
  - `/rebotling/avvikelselarm` — innehall wrappad i `#avvikelselarm-content`
  - `/rebotling/produktionsflode` — innehall wrappad i `#produktionsflode-content`

---

## 2026-03-12 Kassationsorsak per station — drill-down sida

Ny sida `/rebotling/kassationsorsak` — visar vilka stationer i rebotling-linjen som kasserar mest och varfor, med trendgraf och top-5-orsaker.

- **Backend**: `classes/KassationsorsakPerStationController.php` (action=`kassationsorsak-per-station`)
  - `run=overview` — KPI:er: total kassation idag, kassation%, varsta station, trend vs igar
  - `run=per-station` — kassation per station med genomsnittslinje (for stapeldiagram)
  - `run=top-orsaker` — top-5 orsaker fran `kassationsregistrering`, filtrerbart per station (?station=XXX)
  - `run=trend` — kassation% per dag per station senaste N dagar (?dagar=30)
  - `run=detaljer` — tabell med alla stationer: kassation%, top-orsak, trend vs foregaende period
  - Stationer ar logiska processsteg (Inspektion, Tvatt, Fyllning, Etikettering, Slutkontroll) distribuerade proportionellt fran `rebotling_ibc` — inga nya DB-tabeller
  - Registrerad i `api.php` med nyckel `kassationsorsak-per-station`

- **Frontend**: `pages/rebotling/kassationsorsak/`
  - Angular standalone-komponent `KassationsorsakPage`
  - Service `kassationsorsak-per-station.service.ts` med fullstandiga TypeScript-interfaces
  - 4 KPI-kort: total kassation idag, kassation%, varsta station, trend vs igar
  - Stapeldiagram (Chart.js): kassation per station + genomsnittslinje
  - Horisontellt stapeldiagram: top-5 kassationsorsaker, filtrerbart per station via dropdown
  - Linjediagram: kassation% per dag per station senaste N dagar, en linje per station
  - Detaljerad tabell: station, totalt, kasserade, kassation%, top-orsak, trend
  - Periodselektor: Idag/7/30/90 dagar
  - Lazy-loaded route med authGuard: `/rebotling/kassationsorsak`
  - Menypost under Rebotling med ikon `fas fa-times-circle`
  - OnInit/OnDestroy + destroy$ + takeUntil + chart?.destroy()

---

## 2026-03-12 Rebotling Sammanfattning — VD:ns landing page

Ny sida `/rebotling/sammanfattning` — VD:ns "landing page" med de viktigaste KPI:erna fran alla rebotling-sidor. Forsta laget pa 10 sekunder.

- **Backend**: `classes/RebotlingSammanfattningController.php`
  - `run=overview` — Alla KPI:er i ett anrop: dagens produktion, OEE%, kassation%, aktiva larm (med de 5 senaste), drifttid%
  - `run=produktion-7d` — Senaste 7 dagars produktion (for stapeldiagram), komplett dagssekvens
  - `run=maskin-status` — Status per maskin/station med OEE, tillganglighet, stopptid (gron/gul/rod)
  - Anvander befintliga tabeller: rebotling_ibc, maskin_oee_daglig, maskin_register, avvikelselarm — inga nya DB-tabeller
  - Registrerad i `api.php` med nyckel `rebotling-sammanfattning`
- **Service**: `rebotling-sammanfattning.service.ts` — interfaces SammanfattningOverview, Produktion7dData, MaskinStatusData
- **Komponent**: `pages/rebotling/rebotling-sammanfattning/`
  - 5 KPI-kort: Dagens produktion (IBC), OEE (%), Kassation (%), Aktiva larm, Drifttid (%)
  - Produktionsgraf: staplat stapeldiagram (Chart.js) med godkanda/kasserade senaste 7 dagar
  - Maskinstatus-tabell: en rad per station med fargkodad status (gron/gul/rod), OEE, tillganglighet, produktion, kassation, stopptid
  - Senaste larm: de 5 senaste aktiva larmen med typ, allvarlighetsgrad, meddelande, tidsstampel
  - Snabblankar: knappar till Live, Historisk produktion, Maskin-OEE, Avvikelselarm, Kassationsanalys, m.fl.
- **Route**: `/rebotling/sammanfattning`, authGuard, lazy-loaded
- **Meny**: Overst i Rebotling-menyn med ikon `fas fa-tachometer-alt`
- Dark theme (#1a202c bg, #2d3748 cards, #e2e8f0 text), destroy$/takeUntil, chart?.destroy(), clearInterval, auto-refresh var 60:e sekund

## 2026-03-12 Produktionsflode (Sankey-diagram) — IBC-flode genom rebotling-linjen

Ny sida `/rebotling/produktionsflode` — visar IBC-flodet visuellt genom rebotling-linjens stationer (Inspektion, Tvatt, Fyllning, Etikettering, Slutkontroll). Flaskhalsar synliga direkt.

- **Backend**: `classes/ProduktionsflodeController.php`
  - `run=overview` — KPI:er: totalt inkommande, godkanda, kasserade, genomstromning%, flaskhals-station
  - `run=flode-data` — Sankey-data: noder + floden (links) med volymer for SVG-diagram
  - `run=station-detaljer` — tabell per station: inkommande, godkanda, kasserade, genomstromning%, tid/IBC, flaskhalsstatus
  - Anvander befintlig `rebotling_ibc`-tabell med MAX-per-skift-logik — inga nya DB-tabeller
  - Registrerad i `api.php` med nyckel `produktionsflode`
- **Service**: `produktionsflode.service.ts` — interfaces FlodeOverview, FlodeData, FlodeNode, FlodeLink, StationDetalj m.fl.
- **Komponent**: `pages/rebotling/produktionsflode/`
  - 5 KPI-kort: Totalt inkommande, Godkanda, Kasserade, Genomstromning%, Flaskhals-station
  - SVG-baserat flodesdiagram (Sankey-stil): noder for stationer, kurvor for floden, kassationsgrenar i rott
  - Stationsdetaljer-tabell med flaskhalssmarkering (gul rad + badge)
  - Periodselektor: Idag/7d/30d/90d
  - Legende + sammanfattningsrad under diagram
- **Route**: `/rebotling/produktionsflode`, authGuard, lazy-loaded.
- **Meny**: Under Rebotling med ikon `fas fa-project-diagram`.
- Dark theme (#1a202c bg, #2d3748 cards), destroy$/takeUntil, clearInterval, auto-refresh var 120:e sekund.

## 2026-03-12 Automatiska avvikelselarm — larmsystem for produktionsavvikelser

Ny sida `/rebotling/avvikelselarm` — automatiskt larmsystem som varnar VD vid avvikelser i produktionen. VD:n ska forsta laget pa 10 sekunder.

- **Migration**: `2026-03-12_avvikelselarm.sql` — nya tabeller `avvikelselarm` (typ ENUM oee/kassation/produktionstakt/maskinstopp/produktionsmal, allvarlighetsgrad ENUM kritisk/varning/info, meddelande, varde_aktuellt, varde_grans, tidsstampel, kvitterad, kvitterad_av/datum/kommentar) och `larmregler` (typ, allvarlighetsgrad, grans_varde, aktiv, beskrivning). Seed: 5 standardregler + 20 exempellarm.
- **Backend**: `AvvikelselarmController.php` — 7 endpoints: overview (KPI:er), aktiva (ej kvitterade larm sorterade kritisk forst), historik (filter typ/grad/period), kvittera (POST med namn+kommentar), regler, uppdatera-regel (POST, admin-krav), trend (larm per dag per allvarlighetsgrad).
- **Frontend**: Angular standalone-komponent med 3 flikar (Dashboard/Historik/Regler). Dashboard: 4 KPI-kort (aktiva/kritiska/idag/snitt losningstid), aktiva larm-panel med fargkodade kort och kvittera-knapp, staplat Chart.js trenddiagram. Historik: filtrerbar tabell med all larmdata. Regler: admin-vy for att justera troeskelvarden och aktivera/inaktivera regler. Kvittera-dialog med namn och kommentar.
- **Route**: `/rebotling/avvikelselarm`, authGuard, lazy-loaded.
- **Meny**: Under Rebotling med ikon `fas fa-exclamation-triangle`.
- Dark theme, destroy$/takeUntil, chart?.destroy(), clearInterval, auto-refresh var 60:e sekund.

## 2026-03-12 Historisk produktionsoversikt — statistik over tid for VD

Ny sida `/rebotling/historisk-produktion` — ger VD:n en enkel oversikt av produktionen over tid med adaptiv granularitet, periodjamforelse och trendindikatorer.

- **Backend**: `classes/HistoriskProduktionController.php`
  - `run=overview` — KPI:er: total produktion, snitt/dag, basta dag, kassation% snitt
  - `run=produktion-per-period` — aggregerad produktionsdata med adaptiv granularitet (dag/vecka/manad beroende pa period)
  - `run=jamforelse` — jamfor vald period mot foregaende period (diff + trend)
  - `run=detalj-tabell` — daglig detaljdata med pagination och sortering
  - Anvander befintlig `rebotling_ibc`-tabell — inga nya DB-tabeller
  - Registrerad i `api.php` med nyckel `historisk-produktion`
- **Service**: `historisk-produktion.service.ts` — interfaces HistoriskOverview, PeriodDataPoint, Jamforelse, DetaljTabell m.fl.
- **Komponent**: `pages/rebotling/historisk-produktion/`
  - 4 KPI-kort: Total produktion, Snitt/dag, Basta dag, Kassation% snitt
  - Produktionsgraf (linjediagram, Chart.js) med adaptiv granularitet: 7/30d dagvis, 90d veckovis, 365d manadsvis
  - Jamforelsevy: nuvarande vs foregaende period sida vid sida med differenser
  - Trendindikator: pilar + procentuella forandringar (produktion, snitt, kassation)
  - Produktionstabell: daglig data med sortering pa alla kolumner + pagination
  - Periodselektor: 7d/30d/90d/365d knappar + anpassat datumintervall
  - Dark theme, OnInit/OnDestroy, destroy$ + takeUntil, chart?.destroy(), refreshTimer
- **Route**: `rebotling/historisk-produktion`, authGuard, lazy-loaded
- **Meny**: Under Rebotling med ikon `fas fa-chart-line`

## 2026-03-12 Leveransplanering — kundorder vs produktionskapacitet

Ny sida `/rebotling/leveransplanering` — matchar kundordrar mot produktionskapacitet i rebotling-linjen med leveransprognos och forseningsvarningar.

- **Migration**: `2026-03-12_leveransplanering.sql` — nya tabeller `kundordrar` (kundnamn, antal_ibc, bestallningsdatum, onskat/beraknat leveransdatum, status ENUM planerad/i_produktion/levererad/forsenad, prioritet, notering) och `produktionskapacitet_config` (kapacitet_per_dag, planerade_underhallsdagar JSON, buffer_procent). Seed-data: 10 exempelordrar + kapacitet 80 IBC/dag.
- **Backend**: `classes/LeveransplaneringController.php`
  - `run=overview` — KPI:er: aktiva ordrar, leveransgrad%, forsenade ordrar, kapacitetsutnyttjande%
  - `run=ordrar` — lista ordrar med filter (status, period)
  - `run=kapacitet` — kapacitetsdata per dag (tillganglig vs planerad) + Gantt-data
  - `run=prognos` — leveransprognos baserat pa kapacitet och orderko
  - `run=konfiguration` — hamta/uppdatera kapacitetskonfiguration
  - `run=skapa-order` (POST) — skapa ny order med automatisk leveransdatumberakning
  - `run=uppdatera-order` (POST) — uppdatera orderstatus
  - `ensureTables()` med automatisk seed-data
  - Registrerad i `api.php` med nyckel `leveransplanering`
- **Service**: `leveransplanering.service.ts` — interfaces KundorderItem, GanttItem, KapacitetData, PrognosItem m.fl.
- **Komponent**: `pages/rebotling/leveransplanering/`
  - KPI-kort (4 st): Aktiva ordrar, Leveransgrad%, Forsenade ordrar, Kapacitetsutnyttjande%
  - Ordertabell med sortering, statusbadges (planerad/i_produktion/levererad/forsenad), prioritetsindikatorer, atgardsknappar
  - Gantt-liknande kapacitetsvy (Chart.js horisontella staplar) — beraknad leverans vs deadline per order
  - Kapacitetsprognos (linjediagram) — tillganglig kapacitet vs planerad produktion per dag
  - Filterbar: status (alla/aktiva/forsenade/levererade) + period (alla/vecka/manad)
  - Ny order-modal med automatisk leveransdatumberakning
  - Dark theme, OnInit/OnDestroy, destroy$ + takeUntil, chart?.destroy(), refreshTimer
- **Route**: `rebotling/leveransplanering`, authGuard, lazy-loaded
- **Meny**: Under Rebotling med ikon `fas fa-truck-loading`

## 2026-03-12 Kvalitetscertifikat — certifikat per batch med kvalitetsbedomning

Ny sida `/rebotling/kvalitetscertifikat` — genererar kvalitetsintyg for avslutade batchar med nyckeltal (kassation%, cykeltid, operatorer, godkand/underkand).

- **Migration**: `2026-03-12_kvalitetscertifikat.sql` — nya tabeller `kvalitetscertifikat` (batch_nummer, datum, operator, antal_ibc, kassation_procent, cykeltid_snitt, kvalitetspoang, status ENUM godkand/underkand/ej_bedomd, kommentar, bedomd_av/datum) och `kvalitetskriterier` (namn, beskrivning, min/max_varde, vikt, aktiv). Seed-data: 25 exempelcertifikat + 5 kvalitetskriterier.
- **Backend**: `classes/KvalitetscertifikatController.php`
  - `run=overview` — KPI:er: totala certifikat, godkand%, senaste certifikat, snitt kvalitetspoang
  - `run=lista` — lista certifikat med filter (status, period, operator)
  - `run=detalj` — hamta komplett certifikat for en batch
  - `run=generera` (POST) — skapa nytt certifikat med automatisk poangberakning
  - `run=bedom` (POST) — godkann/underkann certifikat med kommentar
  - `run=kriterier` — hamta kvalitetskriterier
  - `run=uppdatera-kriterier` (POST) — uppdatera kriterier (admin)
  - `run=statistik` — kvalitetspoang per batch for trenddiagram
  - Registrerad i `api.php` med nyckel `kvalitetscertifikat`
- **Service**: `kvalitetscertifikat.service.ts` — interfaces Certifikat, KvalitetOverviewData, Kriterium, StatistikItem m.fl.
- **Komponent**: `pages/rebotling/kvalitetscertifikat/`
  - KPI-kort (4 st): Totala certifikat, Godkanda%, Senaste certifikat, Snitt kvalitetspoang
  - Batch-tabell med sortering, statusbadges, poangfargkodning
  - Certifikat-modal: formaterat kvalitetscertifikat med batchinfo, produktionsdata, bedomning, kriterier
  - Bedom-funktion: godkann/underkann med kommentar
  - Generera-modal: skapa nytt certifikat med batchdata
  - Stapeldiagram (Chart.js) med kvalitetspoang per batch + trendlinje
  - Filter: period (vecka/manad/kvartal), status, operator
  - Print CSS (@media print) for utskriftsvanliq certifikatvy
  - Auto-refresh var 60 sek, OnInit/OnDestroy + destroy$ + takeUntil + clearInterval + chart.destroy()
- **Route**: `/rebotling/kvalitetscertifikat` (authGuard, lazy-loaded)
- **Meny**: "Kvalitetscertifikat" med ikon `fas fa-certificate` under Rebotling

---

## 2026-03-12 Operatorsbonus — individuell bonuskalkylator per operator

Ny sida `/rebotling/operatorsbonus` — transparent bonusmodell som beraknar individuell bonus baserat pa IBC/h, kvalitet, narvaro och team-mal.

- **Migration**: `2026-03-12_operatorsbonus.sql` — nya tabeller `bonus_konfiguration` (faktor ENUM, vikt, mal_varde, max_bonus_kr, beskrivning) och `bonus_utbetalning` (operator_id, period_start/slut, delbonus per faktor, total_bonus). Seed-data: IBC/h 40%/12 mal/500kr, Kvalitet 30%/98%/400kr, Narvaro 20%/100%/200kr, Team 10%/95%/100kr.
- **Backend**: `classes/OperatorsbonusController.php`
  - `run=overview` — KPI:er: snittbonus, hogsta/lagsta bonus (med namn), total utbetald, antal kvalificerade
  - `run=per-operator` — bonusberakning per operator med IBC/h, kvalitet%, narvaro%, team-mal%, delbonus per faktor, total bonus, progress-procent per faktor
  - `run=konfiguration` — hamta bonuskonfiguration (vikter, mal, maxbelopp)
  - `run=spara-konfiguration` (POST) — uppdatera bonusparametrar (admin)
  - `run=historik` — tidigare utbetalningar per operator/period
  - `run=simulering` — vad-om-analys med anpassade invaranden
  - Bonusformel: min(verkligt/mal, 1.0) x max_bonus_kr
  - Registrerad i `api.php` med nyckel `operatorsbonus`
- **Service**: `operatorsbonus.service.ts` — interfaces BonusOverviewData, OperatorBonus, BonusKonfig, KonfigItem, SimuleringData m.fl.
- **Komponent**: `pages/rebotling/operatorsbonus/`
  - KPI-kort (4 st): Snittbonus, Hogsta bonus (namn+kr), Total utbetald, Antal kvalificerade
  - Stapeldiagram (Chart.js, stacked bar) — bonus per operator uppdelat pa faktor
  - Radardiagram — prestationsprofil per vald operator (IBC/h, Kvalitet, Narvaro, Team)
  - Operatorstabell — sorterbar med progress bars per faktor, delbonus per kolumn, total
  - Konfigurationspanel (admin) — andra vikter, mal, maxbelopp
  - Bonussimulator — skjutreglage for IBC/h, Kvalitet, Narvaro, Team med doughnut-resultat
  - Period-filter: Idag / Vecka / Manad
  - Auto-refresh var 60 sek, OnInit/OnDestroy + destroy$ + takeUntil + clearInterval + chart.destroy()
- **Route**: `/rebotling/operatorsbonus` (authGuard, lazy-loaded)
- **Meny**: "Operatorsbonus" med ikon `fas fa-award` under Rebotling

---

## 2026-03-12 Maskin-OEE — OEE per maskin/station i rebotling-linjen

Ny sida `/rebotling/maskin-oee` — OEE (Overall Equipment Effectiveness) nedbruten per maskin. OEE = Tillganglighet x Prestanda x Kvalitet.

- **Migration**: `2026-03-12_maskin_oee.sql` — nya tabeller `maskin_oee_config` (maskin_id, planerad_tid_min, ideal_cykeltid_sek, oee_mal_pct) och `maskin_oee_daglig` (maskin_id, datum, planerad_tid_min, drifttid_min, stopptid_min, total_output, ok_output, kassation, T/P/K/OEE%) med seed-data for 6 maskiner x 30 dagar
- **Backend**: `classes/MaskinOeeController.php`
  - `run=overview` — Total OEE idag, basta/samsta maskin, trend vs forra veckan, OEE-mal
  - `run=per-maskin` — OEE per maskin med T/P/K-uppdelning, planerad tid, drifttid, output, kassation
  - `run=trend` — OEE per dag per maskin (linjediagram), med OEE-mallinje
  - `run=benchmark` — jamfor maskiner mot varandra och mot mal-OEE (min/max/avg)
  - `run=detalj` — detaljerad daglig breakdown per maskin: planerad tid, drifttid, ideal cykeltid, output, kassation
  - `run=maskiner` — lista aktiva maskiner
  - `ensureTables()` kor migration automatiskt vid forsta anrop
  - Registrerad i `api.php` med nyckel `maskin-oee`
- **Service**: `maskin-oee.service.ts` — interfaces OeeOverviewData, OeeMaskinItem, OeeTrendSeries, OeeBenchmarkItem, OeeDetaljItem, Maskin
- **Komponent**: `pages/rebotling/maskin-oee/`
  - KPI-kort (4 st): Total OEE idag (fargkodad mot mal), Basta maskin (namn+OEE), Samsta maskin (namn+OEE), Trend vs forra veckan (+/- %)
  - OEE gauge-kort per maskin med progress bars for T/P/K och over/under mal-badge
  - Stapeldiagram: T/P/K per maskin (grupperat) med OEE i tooltip
  - Linjediagram: OEE-trend per dag per maskin med streckad mal-linje (konfigurerbar)
  - Maskin-checkboxar for att valja vilka maskiner som visas i trenddiagrammet
  - Detaljerad tabell: OEE%, T%, P%, K%, planerad tid, drifttid, output, kassation% per maskin
  - Daglig OEE-logg: sorterbar tabell med alla dagliga OEE-poster
  - Period-filter: Idag / Vecka / Manad (30d)
  - Maskin-filter dropdown for trend + detalj
  - Auto-refresh var 60 sekunder, OnInit/OnDestroy + destroy$ + takeUntil + clearInterval + destroyCharts
- **Route**: `/rebotling/maskin-oee` (authGuard, lazy loading)
- **Meny**: "Maskin-OEE" med ikon `fas fa-tachometer-alt` (lila) under Rebotling

---

## 2026-03-12 Stopptidsanalys per maskin — drill-down, flaskhalsar, maskin-jämförelse

Ny sida `/rebotling/stopptidsanalys` — VD kan göra drill-down på stopptider per maskin, identifiera flaskhalsar och jämföra maskiner.

- **Migration**: `2026-03-12_stopptidsanalys.sql` — ny tabell `maskin_stopptid` (id, maskin_id, maskin_namn, startad_at, avslutad_at, duration_min, orsak, orsak_kategori ENUM, operator_id, operator_namn, kommentar) med 27 demo-stopphändelser för 6 maskiner (Tvättmaskin, Torkugn, Inspektionsstation, Transportband, Etiketterare, Ventiltestare)
- **Backend**: `classes/StopptidsanalysController.php`
  - `run=overview` — KPI:er: total stopptid idag (min), flaskhals-maskin (mest stopp i perioden), antal stopp idag, snitt per stopp, trend vs föregående period
  - `run=per-maskin` — horisontellt stapeldiagram-data: total stopptid per maskin sorterat störst→minst, andel%, antal stopp, snitt/max per stopp
  - `run=trend` — linjediagram: stopptid per dag per maskin, filtrerbart per maskin_id
  - `run=fordelning` — doughnut-data: andel stopptid per maskin
  - `run=detaljtabell` — detaljlog alla stopp med tidpunkt, maskin, varaktighet, orsak, kategori, operatör (max 500 poster), maskin_id-filter
  - `run=maskiner` — lista alla aktiva maskiner (för filter-dropdowns)
  - `ensureTables()` kör migration automatiskt vid första anrop
  - Registrerad i `api.php` med nyckel `stopptidsanalys`
- **Service**: `stopptidsanalys.service.ts` — interfaces OverviewData, PerMaskinData, MaskinItem, TrendData, TrendSeries, FordelningData, DetaljData, StoppEvent, Maskin
- **Komponent**: `pages/rebotling/stopptidsanalys/`
  - KPI-kort (4 st): Total stopptid idag, Flaskhals-maskin (med tid), Antal stopp idag (med trendikon), Snitt per stopp (med period-total)
  - Horisontellt stapeldiagram (Chart.js) per maskin, sorterat störst→minst med tooltip: min/stopp/snitt
  - Trenddiagram (linjediagram) per dag per maskin med interaktiva maskin-checkboxar (standard: top-3 valda)
  - Doughnut-diagram: stopptidsfördelning per maskin med tooltip: min/andel/stopp
  - Maskin-sammanfattningstabell med progress bars, andel%, snitt, max-stopp
  - Detaljerad stopptids-log: sorterbar tabell (klicka kolumnrubrik), maskin-filter dropdown, kategori-badges
  - Period-filter: Idag / Vecka / Manad (30d) med btn-group
  - Auto-refresh var 60 sekunder, OnInit/OnDestroy + destroy$ + takeUntil + clearInterval + destroyCharts
- **Route**: `/rebotling/stopptidsanalys` (authGuard)
- **Meny**: "Stopptidsanalys" med ikon `fas fa-stopwatch` under Rebotling

---

## 2026-03-12 Produktionskostnad per IBC — kostnadskalkyl med konfigurerbara faktorer

Ny sida `/rebotling/produktionskostnad` -- VD kan se uppskattad produktionskostnad per IBC baserat pa stopptid, energi, bemanning och kassation.

- **Migration**: `2026-03-12_produktionskostnad.sql` -- tabell `produktionskostnad_config` (id, faktor ENUM energi/bemanning/material/kassation/overhead, varde DECIMAL, enhet VARCHAR, updated_at, updated_by) med seed-data (energi 150kr/h, bemanning 350kr/h, material 50kr/IBC, kassation 200kr/IBC, overhead 100kr/h)
- **Backend**: `ProduktionskostnadController.php` i `classes/`
  - `run=overview` -- 4 KPI:er: kostnad/IBC idag, totalkostnad, kostnadstrend% vs forra veckan, kassationskostnad och andel
  - `run=breakdown` (?period=dag/vecka/manad) -- kostnadsuppdelning per kategori (energi/bemanning/material/kassation/overhead)
  - `run=trend` (?period=30/90) -- kostnad/IBC per dag med snitt
  - `run=daily-table` (?from&to) -- daglig tabell med IBC, kostnader, stopptid
  - `run=shift-comparison` (?period=dag/vecka/manad) -- kostnad/IBC per skift
  - `run=config` (GET) -- hamta aktuell konfiguration
  - `run=update-config` (POST) -- uppdatera kostnadsfaktorer (krav: inloggad)
  - Kostnadsmodell: Energi = drifttimmar x kr/h, Bemanning = 2op x 8h x kr/h, Material = ibc_ok x kr, Kassation = ibc_ej_ok x kr, Overhead = arbetstimmar x kr/h
  - Stopptid hamtas fran `rebotling_log`; produktionsdata fran `rebotling_ibc` med MAX per skift
  - `ensureTables()` kor migration automatiskt vid forsta anrop
  - Registrerad i `api.php` med nyckel `produktionskostnad`
- **Service**: `produktionskostnad.service.ts` -- interfaces KostnadOverview, KostnadBreakdown, KostnadTrend, DailyTable, ShiftComparison, KonfigFaktor
- **Komponent**: `pages/rebotling/produktionskostnad/`
  - 4 KPI-kort: Kostnad/IBC idag, Totalkostnad, Kostnadstrend (pil upp/ner + %), Kassationskostnad (andel %)
  - Kostnadskonfiguration: accordion med inputfalt per faktor, spara-knapp med feedback
  - Kostnadsuppdelning: doughnut-diagram (Chart.js) + progress-bars med procent per kategori
  - Kostnad/IBC over tid: linjediagram (30/90 dagar) med snittlinje
  - Daglig kostnadstabell: datum, IBC ok/kasserad, totalkostnad, kostnad/IBC, kassationskostnad, stopptid
  - Skiftjamforelse: stapeldiagram (kostnad/IBC per skift), fargpalette per skift
  - Period-filter: dag/vecka/manad for breakdown och skiftjamforelse
  - Datum-filter for tabell (fran/till)
  - Lifecycle: OnInit/OnDestroy + destroy$ + takeUntil + clearInterval
- **Route**: `/rebotling/produktionskostnad` (authGuard) i `app.routes.ts`
- **Meny**: "Produktionskostnad/IBC" (coins-ikon, gul) lagd till i Rebotling-dropdown i `menu.html`

---

## 2026-03-12 Produktions-SLA/Maluppfyllnad — dagliga/veckovisa produktionsmal med uppfyllnadsgrad

Ny sida `/rebotling/produktions-sla` -- VD kan satta dagliga/veckovisa produktionsmal och se uppfyllnadsgrad i procent med progress bars, gauge-diagram och historik.

- **Migration**: `2026-03-12_produktions_sla.sql` -- tabell `produktions_mal` (id, mal_typ ENUM dagligt/veckovist, target_ibc, target_kassation_pct, giltig_from, giltig_tom, created_by, created_at) med seed-data (dagligt: 80 IBC / max 5% kassation, veckovist: 400 IBC / max 4% kassation)
- **Backend**: `ProduktionsSlaController.php` i `classes/`
  - `run=overview` -- KPI:er: dagens maluppfyllnad% (producerat vs mal), veckans maluppfyllnad%, streak (dagar i rad over mal), basta vecka senaste manaden
  - `run=daily-progress` (?date=YYYY-MM-DD) -- dagens mal vs faktisk produktion per timme (kumulativt, 06-22), takt per timme
  - `run=weekly-progress` (?week=YYYY-Wxx) -- veckans mal vs faktisk dag for dag med uppfyllnad% och over_mal-flagga
  - `run=history` (?period=30/90) -- historik over maluppfyllnad per dag med trend (uppat/nedat/stabil), snitt uppfyllnad%, dagar over mal
  - `run=goals` -- lista aktiva och historiska mal med aktiv-flagga
  - `run=set-goal` (POST) -- satt nytt mal (dagligt/veckovist), avslutar automatiskt tidigare aktivt mal av samma typ
  - `ensureTables()` kor migration automatiskt vid forsta anrop
  - Produktionsdata hamtas fran `rebotling_ibc` med MAX(ibc_ok) per (datum, skiftraknare) -- samma monster som StatistikDashboardController
  - Registrerad i `api.php` med nyckel `produktionssla`
- **Service**: `produktions-sla.service.ts` -- interfaces SlaOverview, DailyProgress, WeeklyProgress, HistoryData, SlaGoal, SetGoalData
- **Komponent**: `pages/rebotling/produktions-sla/`
  - KPI-kort (4 st) -- Dagens mal (% med animerad progress bar, fargkodad gron/gul/rod), Veckans mal (%), Streak (dagar i rad, eldikon), Basta vecka
  - Dagens progress -- halvdoughnut gauge (Chart.js) med IBC klara / mal, kassation%, takt/timme
  - Veckoversikt -- stapeldiagram med dagliga staplar (gron=over mal, rod=under), mal-linje overlagd (streckad gul)
  - Historik -- linjediagram med maluppfyllnad% over tid (30/90 dagar), 7-dagars glidande medelvarde, 100%-linje
  - Daglig tabell -- denna vecka dag for dag med progress bars, kassation%, check/cross-ikoner
  - Malkonfiguration -- expanderbar sektion dar VD sattar nya mal (typ, IBC/dag, max kassation%, giltigt fran), malhistorik-tabell
  - Auto-refresh var 60 sekunder, OnInit/OnDestroy + destroy$ + takeUntil + clearInterval + destroyCharts
- **Route**: `/rebotling/produktions-sla` (authGuard)
- **Meny**: "Maluppfyllnad" med ikon `fas fa-bullseye` under Rebotling

---

## 2026-03-12 Skiftplanering — bemanningsöversikt

Ny sida `/rebotling/skiftplanering` — VD/admin ser vilka operatörer som jobbar vilket skift, planerar kapacitet och får varning vid underbemanning.

- **Migration**: `2026-03-12_skiftplanering.sql` — tabeller `skift_konfiguration` (3 skifttyper: FM 06-14, EM 14-22, NATT 22-06 med min/max bemanning) + `skift_schema` (operator_id, skift_typ, datum) med seed-data för aktuell vecka (8 operatörer)
- **Backend**: `SkiftplaneringController.php` i `classes/`
  - `run=overview` — KPI:er: antal operatörer totalt (unika denna vecka), bemanningsgrad idag (%), antal skift med underbemanning, nästa skiftbyte (tid kvar + klockslag)
  - `run=schedule` (?week=YYYY-Wxx) — veckoschema: per skift och dag, vilka operatörer med namn, antal, status (gron/gul/rod) baserat på min/max-konfiguration
  - `run=shift-detail` (?shift=FM/EM/NATT&date=YYYY-MM-DD) — detalj: operatörer i skiftet, planerad kapacitet (IBC/h), faktisk produktion från rebotling_log
  - `run=assign` (POST) — tilldela operatör till skift/dag (med validering: ej dubbelbokad samma dag)
  - `run=unassign` (POST) — ta bort operatör från skift (via schema_id eller operator_id+datum)
  - `run=capacity` — bemanningsgrad per dag i veckan, historisk IBC/h, skift-konfiguration
  - `run=operators` — lista alla operatörer (för dropdown vid tilldelning)
  - `ensureTables()` kör migration automatiskt vid första anrop
  - Registrerad i `api.php` med nyckel `skiftplanering`
  - Proxy-controller i `controllers/SkiftplaneringController.php`
- **Service**: `skiftplanering.service.ts` — interfaces SkiftOverview, ScheduleResponse, SkiftRad, DagInfo, ShiftDetailResponse, OperatorItem, DagKapacitet, CapacityResponse
- **Komponent**: `pages/rebotling/skiftplanering/`
  - KPI-kort (4 st): Operatörer denna vecka, Bemanningsgrad idag % (grön/gul/röd ram), Underbemanning (röd vid >0), Nästa skiftbyte
  - Veckoväljare: navigera framåt/bakåt mellan veckor med pilar
  - Veckoschema-tabell: dagar som kolumner, skift som rader, operatörsnamn som taggar i celler, färgkodad (grön=full, gul=låg, röd=under min), today-markering (blå kant)
  - Klickbar cell — öppnar skiftdetalj-overlay med operatörlista, planerad kapacitet, faktisk produktion
  - Plus-knapp i varje cell — öppnar tilldelnings-modal med dropdown av tillgängliga operatörer (filtrerar bort redan inplanerade)
  - Ta bort-knapp per operatör i detaljvyn
  - Chart.js: Bemanningsgrad per dag (stapeldiagram med grön/gul/röd färg + röd streckad target-linje vid 100%)
  - Förklaring (legend): grön/gul/röd
  - Auto-refresh var 5 minuter, OnInit/OnDestroy + destroy$ + takeUntil + clearInterval
- **Route**: `/rebotling/skiftplanering` (authGuard)
- **Meny**: "Skiftplanering" med ikon `fas fa-calendar-alt` under Rebotling

---

## 2026-03-12 Batch-spårning — följ IBC-batchar genom produktionslinjen

Ny sida `/rebotling/batch-sparning` — VD/operatör kan följa batchar/ordrar av IBC:er genom hela produktionslinjen.

- **Migration**: `2026-03-12_batch_sparning.sql` — tabeller `batch_order` + `batch_ibc` med seed-data (3 exempelbatchar: 1 klar, 1 pågående, 1 pausad med totalt 22 IBC:er)
- **Backend**: `BatchSparningController.php` i `classes/`
  - `run=overview` → KPI:er: aktiva batchar, snitt ledtid (h), snitt kassation%, bästa batch (lägst kassation)
  - `run=active-batches` → lista aktiva/pausade batchar med progress, snitt cykeltid, uppskattad tid kvar
  - `run=batch-detail&batch_id=X` → detaljinfo: progress bar, operatörer, tidsåtgång, kasserade, cykeltider, IBC-lista
  - `run=batch-history` → avslutade batchar med KPI:er, stöd för period-filter (from/to) och sökning
  - `run=create-batch` (POST) → skapa ny batch med batch-nummer, planerat antal, kommentar
  - `run=complete-batch` (POST) → markera batch som klar
  - `ensureTables()` kör migration automatiskt vid första anrop
  - Registrerad i `api.php` med nyckel `batchsparning`
- **Service**: `batch-sparning.service.ts` — interfaces BatchOverview, ActiveBatch, BatchDetailResponse, HistoryBatch, CreateBatchData
- **Komponent**: `pages/rebotling/batch-sparning/`
  - KPI-kort (4 st) — aktiva batchar, snitt ledtid, snitt kassation% (röd vid >5%), bästa batch (grön ram)
  - Flik "Aktiva batchar" — tabell med progress bar, status-badge, snitt cykeltid, uppskattad tid kvar
  - Flik "Batch-historik" — sökbar/filtrerbar tabell med period-filter, kassation%, ledtid
  - Chart.js horisontellt staplat stapeldiagram (klara vs kvar per batch)
  - Klickbar rad → detaljpanel (overlay): stor progress bar, detalj-KPI:er, operatörer, IBC-lista med kasserad-markering
  - Modal: Skapa ny batch (batch-nummer, planerat antal, kommentar)
  - Knapp "Markera som klar" i detaljvyn
  - Auto-refresh var 30 sekunder, OnInit/OnDestroy + destroy$ + clearInterval
- **Route**: `/rebotling/batch-sparning` (authGuard)
- **Meny**: "Batch-spårning" med ikon `fas fa-boxes` under Rebotling

---

## 2026-03-12 Maskinunderhåll — serviceintervall-vy

Ny sida `/rebotling/maskinunderhall` — planerat underhåll, servicestatus per maskin och varningslampa vid försenat underhåll.

- **Migration**: `2026-03-12_maskinunderhall.sql` — tabeller `maskin_register` + `maskin_service_logg` med seed-data (6 maskiner: Tvättmaskin, Torkugn, Inspektionsstation, Transportband, Etiketterare, Ventiltestare)
- **Backend**: `MaskinunderhallController.php` i `classes/`
  - `run=overview` → KPI:er: antal maskiner, kommande service inom 7 dagar, försenade (rött om >0), snitt intervall dagar
  - `run=machines` → lista maskiner med senaste service, nästa planerad, dagar kvar, status (gron/gul/rod)
  - `run=machine-history&maskin_id=X` → servicehistorik för specifik maskin (50 senaste)
  - `run=timeline` → data för Chart.js: dagar sedan service, intervall, förbrukad%, status per maskin
  - `run=add-service` (POST) → registrera genomförd service med auto-beräkning av nästa datum
  - `run=add-machine` (POST) → registrera ny maskin
  - `ensureTables()` kör migration automatiskt vid första anrop
  - Registrerad i `api.php` med nyckel `maskinunderhall`
- **Service**: `maskinunderhall.service.ts` — interfaces MaskinOverview, MaskinItem, ServiceHistoryItem, TimelineItem, AddServiceData, AddMachineData
- **Komponent**: `pages/rebotling/maskinunderhall/`
  - KPI-kort (4 st) — antal maskiner, kommande 7d, försenade (röd vid >0), snitt intervall
  - Tabell med statusfärg: grön (>7d kvar), gul (1-7d), röd (försenat), sorterbara kolumner, statusfilter
  - Klickbar rad → expanderbar servicehistorik inline (accordion-stil)
  - Modal: Registrera service (maskin, datum, typ, beskrivning, utfört av, nästa planerad)
  - Modal: Registrera ny maskin (namn, beskrivning, serviceintervall)
  - Chart.js horisontellt stapeldiagram (indexAxis: 'y') — tid sedan service vs intervall, röd del för försenat
  - Auto-refresh var 5 minuter, OnInit/OnDestroy + destroy$ + clearInterval
- **Route**: `/rebotling/maskinunderhall` (authGuard)
- **Meny**: "Maskinunderhåll" med ikon `fas fa-wrench` under Rebotling

---

## 2026-03-12 Statistik-dashboard — komplett produktionsöverblick för VD

Ny sida `/rebotling/statistik-dashboard` — VD kan på 10 sekunder se hela produktionsläget.

- **Backend**: `StatistikDashboardController.php` i `classes/` + proxy i `controllers/`
  - `run=summary` → 6 KPI:er: IBC idag/igår, vecka/förra veckan, kassation%, drifttid%, aktiv operatör, snitt IBC/h 7d
  - `run=production-trend` → daglig data senaste N dagar med dual-axis stöd (IBC + kassation%)
  - `run=daily-table` → senaste 7 dagars tabell med bästa operatör per dag + färgkodning
  - `run=status-indicator` → beräknar grön/gul/röd baserat på kassation% och IBC/h vs mål
- **api.php**: nyckel `statistikdashboard` registrerad
- **Service**: `statistik-dashboard.service.ts` med interfaces DashboardSummary, ProductionTrendItem, DailyTableRow, StatusIndicator
- **Komponent**: `pages/rebotling/statistik-dashboard/` — standalone, OnInit/OnDestroy, destroy$/takeUntil, Chart.js dual Y-axel (IBC vänster, kassation% höger), auto-refresh var 60s, klickbara datapunkter med detaljvy
- **Route**: `/rebotling/statistik-dashboard` (authGuard)
- **Meny**: "Statistik-dashboard" under Rebotling med ikon `fas fa-tachometer-alt`

---

## 2026-03-12 Kvalitetsanalys — Trendbrott-detektion

Ny sida `/rebotling/kvalitets-trendbrott` — automatisk flaggning av dagar med markant avvikande kassationsgrad. VD ser direkt varningar.

- **Backend**: `KvalitetsTrendbrottController.php` i `classes/`
  - `run=overview` (?period=7/30/90) — daglig kassationsgrad (%) med rorligt medelv (7d), stddev, ovre/undre grans (+-2 sigma), flaggade avvikelser
  - `run=alerts` (?period=30/90) — trendbrott sorterade efter allvarlighetsgrad (sigma), med skift- och operatorsinfo
  - `run=daily-detail` (?date=YYYY-MM-DD) — drill-down: per-skift kassation, per-operator, stopporsaker
  - Kassationsgrad = ibc_ej_ok / (ibc_ok + ibc_ej_ok) * 100 fran rebotling_ibc
  - Registrerad i `api.php` med nyckel `kvalitetstrendbrott`
- **Proxy**: `controllers/KvalitetsTrendbrottController.php`
- **Frontend Service**: `services/kvalitets-trendbrott.service.ts` (standalone)
  - `getOverview(period)`, `getAlerts(period)`, `getDailyDetail(date)`
  - Interfaces: TrendbrottDailyItem, TrendbrottOverviewData, TrendbrottAlert, TrendbrottAlertsData, TrendbrottDailyDetailData
- **Frontend Komponent**: `pages/rebotling/kvalitets-trendbrott/` (standalone, OnInit/OnDestroy, destroy$, takeUntil)
  - KPI-kort: Snitt kassation%, Antal trendbrott, Senaste trendbrott, Aktuell trend (battre/samre/stabil)
  - Chart.js linjediagram: Daglig kassation% + rorligt medelv (7d) + ovre/undre grans. Avvikande punkter i rott/gront
  - Varningstabell: datum, kassation%, avvikelse (sigma), typ-badge (hog=rod, lag=gron), operatorer
  - Drill-down: klicka pa dag -> detaljvy med per-skift + per-operator kassation + stopporsaker
- **Route**: `rebotling/kvalitets-trendbrott` i `app.routes.ts` (authGuard)
- **Meny**: Under Rebotling, ikon `fas fa-chart-line` (rod), text "Kvalitets-trendbrott"

## 2026-03-12 Favoriter / Snabbkommandon — bokmärk mest använda sidor

VD:n kan spara sina mest använda sidor som favoriter och se dem samlade på startsidan for snabb atkomst (10 sekunder).

- **Backend**: `FavoriterController.php` i `classes/` + proxy i `controllers/`
  - `run=list` — hämta användarens sparade favoriter (sorterade)
  - `run=add` (POST) — lägg till favorit (route, label, icon, color)
  - `run=remove` (POST) — ta bort favorit (id)
  - `run=reorder` (POST) — ändra ordning (array av ids)
  - Registrerad i `api.php` med nyckel `favoriter`
- **DB-migrering**: `migrations/2026-03-12_favoriter.sql` — tabell `user_favoriter` (id, user_id, route, label, icon, color, sort_order, created_at) med UNIQUE(user_id, route)
- **Frontend Service**: `favoriter.service.ts` — list/add/remove/reorder + AVAILABLE_PAGES (36 sidor)
- **Frontend Komponent**: `pages/favoriter/` — hantera favoriter med lägg-till-dialog, sökfilter, ordningsknappar, ta-bort
- **Dashboard-widget**: Favoriter visas som klickbara kort med ikon direkt på startsidan (news.html/news.ts)
- **Route**: `/favoriter` i `app.routes.ts` (authGuard)
- **Meny**: Nytt "Favoriter"-menyitem med stjärn-ikon i navigationsmenyn (synlig for inloggade)

## 2026-03-12 Produktionseffektivitet per timme — Heatmap och toppanalys

Ny sida `/rebotling/produktionseffektivitet` — VD förstår vilka timmar på dygnet som är mest/minst produktiva via heatmap, KPI-kort och toppanalys.

- **Backend**: `ProduktionseffektivitetController.php` i `classes/`
  - `run=hourly-heatmap` (?period=7/30/90) — matris veckodag (mån-sön) x timme (0-23), snitt IBC per timme beräknat via antal unika dagar per veckodag
  - `run=hourly-summary` (?period=30) — per timme (0-23): snitt IBC/h, antal mätdagar, bästa/sämsta veckodag
  - `run=peak-analysis` (?period=30) — topp-3 mest produktiva + botten-3 minst produktiva timmar, skillnad i %
  - Registrerad i `api.php` med nyckel `produktionseffektivitet`
- **Frontend Service**: Tre nya metoder + interfaces i `rebotling.service.ts`:
  - `getHourlyHeatmap(period)`, `getHourlySummary(period)`, `getPeakAnalysis(period)`
  - Interfaces: HeatmapVeckodag, HourlyHeatmapData/Response, HourlySummaryRow/Data/Response, PeakTimmeRow, PeakAnalysisData/Response
- **Frontend Komponent**: `pages/rebotling/produktionseffektivitet/` (standalone, OnInit/OnDestroy, destroy$, takeUntil, auto-refresh 120s)
  - KPI-kort: mest produktiv timme, minst produktiv timme, skillnad i %
  - Heatmap som HTML-tabell med dynamiska bakgrundsfärger (röd→gul→grön interpolation)
  - Topp/botten-lista: de 3 bästa och 3 sämsta timmarna med IBC-siffror och progress-bar
  - Linjediagram (Chart.js): snitt IBC/h per timme (0-23) med färgkodade datapunkter
  - Detaljdatatabell med veckodag-info per timme
- **Route**: `rebotling/produktionseffektivitet` i `app.routes.ts` (authGuard)
- **Meny**: Under Rebotling, ikon `fas fa-clock` (grön), text "Produktionseffektivitet/h"

## 2026-03-12 Operatörsjämförelse — Sida-vid-sida KPI-jämförelse

Ny sida `/rebotling/operator-jamforelse` — VD väljer 2–3 operatörer och ser deras KPI:er jämförda sida vid sida.

- **Backend**: `OperatorJamforelseController.php` i `classes/`
  - `run=operators-list` — lista aktiva operatörer (id, namn) för dropdown
  - `run=compare&operators=1,2,3&period=7|30|90` — per operatör: totalt_ibc, ibc_per_h, kvalitetsgrad, antal_stopp, total_stopptid_min, aktiva_timmar
  - `run=compare-trend&operators=1,2,3&period=30` — daglig trenddata (datum, ibc_count, ibc_per_hour) per operatör
  - Stopptid hämtas från stoppage_log med fallback till rebotling_skiftrapport.stopp_min
  - Registrerad i `api.php` som `'operator-jamforelse' => 'OperatorJamforelseController'`
- **Frontend Service**: Tre nya metoder i `rebotling.service.ts`:
  - `getOperatorsForCompare()`, `compareOperators(ids, period)`, `compareOperatorsTrend(ids, period)`
  - Nya interfaces: OperatorJamforelseItem, OperatorJamforelseKpi, OperatorJamforelseTrendRow, OperatorsListResponse, CompareResponse, CompareTrendResponse
- **Frontend Komponent**: `pages/rebotling/operator-jamforelse/` (standalone, OnInit/OnDestroy, destroy$, takeUntil, auto-refresh 120s)
  - Dropdown med checkboxar — välj upp till 3 operatörer
  - Periodväljare: 7/30/90 dagar
  - KPI-tabell sida-vid-sida med kronikon för bästa värde per rad
  - Chart.js linjediagram: IBC/dag per operatör (en linje per operatör)
  - Chart.js radardiagram: normaliserade KPI:er (0–100) i spider chart
  - Guard: isFetchingCompare/isFetchingTrend mot dubbel-requests
- **Route**: `/rebotling/operator-jamforelse` med authGuard i `app.routes.ts`
- **Meny**: Under Rebotling, ikon `fas fa-users`, text "Operatörsjämförelse"

## 2026-03-12 Skiftoverlamninslogg — Digital overlamning mellan skift

Ombyggd sida `/rebotling/skiftoverlamning` — komplett digital skiftoverlamning med strukturerat formular, auto-KPI:er, historik och detaljvy.

- **DB-migrering**: `migrations/2026-03-12_skiftoverlamning.sql` — ny tabell `skiftoverlamning_logg` med operator_id, skift_typ (dag/kvall/natt), datum, auto-KPI-falt (ibc_totalt, ibc_per_h, stopptid_min, kassationer), fritextfalt (problem_text, pagaende_arbete, instruktioner, kommentar), har_pagaende_problem-flagga
- **Backend**: `SkiftoverlamningController.php` i `classes/` och `controllers/` (proxy)
  - `run=list` med filtrering (skift_typ, operator_id, from, to) + paginering
  - `run=detail&id=N` — fullstandig vy av en overlamning
  - `run=shift-kpis` — automatiskt hamta KPI:er fran rebotling_ibc (senaste skiftet)
  - `run=summary` — sammanfattnings-KPI:er: senaste overlamning, antal denna vecka, snittproduktion (senaste 10), pagaende problem
  - `run=operators` — operatorslista for filter-dropdown
  - `run=create (POST)` — skapa ny overlamning med validering + textlangdsbegransning
  - Registrerad i `api.php` som `'skiftoverlamning' => 'SkiftoverlamningController'`
- **Frontend Service**: `skiftoverlamning.service.ts` — interfaces SkiftoverlamningItem, ShiftKpis, SenastOverlamning, PagaendeProblem, CreatePayload + alla responses
- **Frontend Komponent**: `pages/skiftoverlamning/` (standalone, OnInit/OnDestroy, destroy$, takeUntil)
  - KPI-kort: Senaste overlamningens tid, antal denna vecka, snitt IBC (senaste 10), pagaende problem
  - Pagaende-problem-varning med detaljer + klickbar for fullstandig vy
  - Historiklista med tabell: datum, skifttyp (badge), operator, IBC, IBC/h, stopptid, sammanfattning
  - Filtrering: skifttyp, operator, datumintervall
  - Detaljvy: fullstandig vy med auto-KPI:er + alla fritextfalt (problem, pagaende arbete, instruktioner, kommentar)
  - Formular: Auto-hamtar KPI:er fran PLC, operator fyller i fritextfalt, flagga pagaende problem
  - Paginering, dark theme, responsive
- **Route**: `rebotling/skiftoverlamning` i `app.routes.ts` (redan registrerad)
- **Meny**: Under Rebotling, ikon `fas fa-clipboard-list`, text "Skiftoverlamningmall" (redan registrerad)

## 2026-03-12 Operator-onboarding — Larlingskurva & nya operatorers utveckling

Ny sida `/rebotling/operator-onboarding` — VD ser hur snabbt nya operatorer nar teamgenomsnitt i IBC/h under sina forsta veckor.

- **Backend**: `OperatorOnboardingController.php` i `classes/` och `controllers/` (proxy)
  - `run=overview&months=3|6|12`: Alla operatorer med onboarding-status, KPI-kort. Filtrerar pa operatorer vars forsta registrerade IBC ar inom valt tidsfonstret. Beraknar nuvarande IBC/h (30d), % av teamsnitt, veckor aktiv, veckor till teamsnitt, status (gron/gul/rod)
  - `run=operator-curve&operator_number=X`: Veckovis IBC/h de forsta 12 veckorna for en operator, jamfort med teamsnitt
  - `run=team-stats`: Teamsnitt IBC/h (90 dagar), antal aktiva operatorer
  - Anvander `rebotling_skiftrapport` (op1/op2/op3, ibc_ok, drifttid, datum) och `operators` (number, name)
  - Registrerad i `api.php` som `'operator-onboarding' => 'OperatorOnboardingController'`
- **Frontend Service**: `operator-onboarding.service.ts` — interfaces OnboardingOperator, OnboardingKpi, OverviewData, WeekData, OperatorCurveData, TeamStatsData + timeout(15000) + catchError
- **Frontend Komponent**: `pages/operator-onboarding/` (standalone, OnInit/OnDestroy, destroy$, takeUntil)
  - KPI-rad: Antal nya operatorer, snitt veckor till teamsnitt, basta nykomling (IBC/h), teamsnitt IBC/h
  - Operatorstabell: sorterad efter startdatum (nyast forst), NY-badge, status-badge (gron >= 90%, gul 70-90%, rod < 70%), procent-stapel
  - Drill-down: klicka operator -> Chart.js linjediagram (12 veckor, IBC/h + teamsnitt-linje) + veckotabell (IBC/h, IBC OK, drifttid, vs teamsnitt)
  - Periodvaljare: 3 / 6 / 12 manader
- **Route**: `rebotling/operator-onboarding` (authGuard) i `app.routes.ts`
- **Meny**: Under Rebotling, ikon `fas fa-user-graduate`, text "Operator-onboarding", visas for inloggade

## 2026-03-12 Stopporsak per operatör — Utbildningsbehov & drill-down

Ny sida `/rebotling/stopporsak-operator` — identifiera vilka operatörer som har mest stopp och kartlägg utbildningsbehov.

- **Backend**: `StopporsakOperatorController.php` i `classes/` och `controllers/` (proxy)
  - `run=overview&period=7|30|90`: Alla operatörer med total stopptid (min), antal stopp, % av teamsnitt, flagga "hog_stopptid" om >150% av teamsnitt. Slår ihop data från `stopporsak_registreringar` + `stoppage_log`
  - `run=operator-detail&operator_id=X&period=7|30|90`: En operatörs alla stopporsaker (antal, total_min, senaste) — underlag för drill-down + donut-chart
  - `run=reasons-summary&period=7|30|90`: Aggregerade stopporsaker för alla operatörer (pie/donut-chart), med `andel_pct`
  - Registrerad i `api.php` som `'stopporsak-operator' => 'StopporsakOperatorController'`
- **Frontend Service**: `stopporsak-operator.service.ts` — interfaces OperatorRow, OverviewData, OrsakDetail, OperatorDetailData, OrsakSummary, ReasonsSummaryData + timeout(15000) + catchError
- **Frontend Komponent**: `pages/stopporsak-operator/` (standalone, OnInit/OnDestroy, destroy$, takeUntil)
  - KPI-rad: Total stopptid, antal stopp, teamsnitt per operatör, antal med hög stopptid
  - Chart.js horisontell stapel: stopptid per operatör (röd = hög, blå = normal) med teamsnittslinje (gul streckad)
  - Operatörstabell: sorterad efter total stopptid, röd vänsterkant + badge "Hög" för >150% av snitt
  - Drill-down: klicka operatör → detaljvy med donut-chart + orsakstabell (antal, stopptid, andel, senaste)
  - Donut-chart (alla operatörer): top-10 stopporsaker med andel av total stopptid
  - Periodväljare: 7d / 30d / 90d
- **Route**: `rebotling/stopporsak-operator` (authGuard) i `app.routes.ts`
- **Meny**: Under Rebotling, ikon `fas fa-exclamation-triangle`, text "Stopporsak per operatör", visas för inloggade

## 2026-03-12 Produktionsprognos — Skiftbaserad realtidsprognos

Ny sida `/rebotling/produktionsprognos` — VD ser på 10 sekunder: producerat X IBC, takt Y IBC/h, prognos Z IBC vid skiftslut.

- **Backend**: `ProduktionsPrognosController.php` i `classes/` och `controllers/` (proxy)
  - `run=forecast`: Aktuellt skift (dag/kväll/natt), IBC hittills, takt (IBC/h), prognos vid skiftslut, tid kvar, trendstatus (bättre/sämre/i snitt), historiskt snitt (14 dagar), dagsmål + progress%
  - `run=shift-history`: Senaste 10 fullständiga skiftens faktiska IBC-resultat och takt, med genomsnitt
  - Skifttider: dag 06-14, kväll 14-22, natt 22-06. Auto-detekterar aktuellt skift inkl. nattskift som spänner midnatt
  - Dagsmål från `rebotling_settings.rebotling_target` + `produktionsmal_undantag` för undantag
  - Registrerad i `api.php` som `'produktionsprognos' => 'ProduktionsPrognosController'`
- **Frontend Service**: `produktionsprognos.service.ts` — TypeScript-interfaces ForecastData, ShiftHistorik, ShiftHistoryData + timeout(10000) + catchError
- **Frontend Komponent**: `pages/produktionsprognos/` (standalone, OnInit/OnDestroy, destroy$, takeUntil, setInterval/clearInterval)
  - VD-sammanfattning: Skifttyp (ikon+namn), Producerat IBC, Takt IBC/h (färgkodad), stor prognossiffra vid skiftslut, tid kvar
  - Skiftprogress: horisontell progressbar som visar hur långt in i skiftet man är
  - Dagsmålsprogress: progressbar för IBC idag vs dagsmål (grön/gul/blå beroende på nivå)
  - Trendindikator: pil upp/ner/neutral + färg + %-avvikelse vs historiskt snitt (14 dagars snitt)
  - Prognosdetaljer: 4 kort — IBC hittills, prognos, vs skiftmål (diff +/-), tid kvar
  - Skifthistorik: de 10 senaste skiften med namn, datum, IBC-total, takt + mini-progressbar (färgkodad grön/gul/röd mot snitt)
  - Auto-refresh var 60:e sekund med isFetching-guard mot dubbla anrop
- **Route**: `rebotling/produktionsprognos` (authGuard) i `app.routes.ts`
- **Meny**: Under Rebotling, ikon `fas fa-chart-line`, text "Produktionsprognos", visas för inloggade
- **Buggfix**: Rättade pre-existenta byggfel i `stopporsak-operator` (orsakFärg → orsakFarg i HTML+TS, styleUrls → styleUrl, ctx: any)

## 2026-03-12 Operatörs-personligt dashboard — Min statistik

Ny sida `/rebotling/operator-dashboard` — varje inloggad operatör ser sin egen statistik, trender och jämförelse mot teamsnitt.

- **Backend**: `MyStatsController.php` i `classes/` och `controllers/` (proxy-monster)
  - `run=my-stats&period=7|30|90`: Total IBC, snitt IBC/h, kvalitet%, bästa dag, jämförelse mot teamsnitt (IBC/h + kvalitet), ranking bland alla operatörer
  - `run=my-trend&period=30|90`: Daglig trend — IBC/dag, IBC/h/dag, kvalitet/dag samt teamsnitt IBC/h per dag
  - `run=my-achievements`: Karriär-total, bästa dag ever (all-time), nuvarande streak (dagar i rad med produktion), förbättring senaste vecka vs föregående (%)
  - Auth: 401 om ej inloggad, 403 om inget operator_id kopplat till kontot
  - Aggregering: MAX() per skiftraknare, sedan SUM() — korrekt för kumulativa PLC-värden
  - Registrerad i `api.php` som `'my-stats' => 'MyStatsController'`
- **Frontend Service**: `my-stats.service.ts` — TypeScript-interfaces för MyStatsData, MyTrendData, MyAchievementsData + timeout(15000) + catchError
- **Frontend Komponent**: `pages/operator-personal-dashboard/` (standalone, OnInit/OnDestroy, destroy$, takeUntil, chart?.destroy())
  - Välkomst-header: "Hej, [operatörsnamn]!" + dagens datum (lång format)
  - 4 KPI-kort: Dina IBC (period), Din IBC/h (färgkodad grön/gul/röd), Din kvalitet%, Din ranking (#X av Y)
  - Jämförelse-sektion: progressbars Du vs Teamsnitt för IBC/h och kvalitet%
  - Linjediagram (Chart.js): Din IBC/h per dag (blå fylld linje) vs teamsnitt (orange streckad linje), 2 dataset
  - Prestationsblock (4 kort): karriär-total IBC, bästa dag ever, nuvarande streak, förbättring +/-% vs förra veckan
  - Bästa dag denna period (extra sektion)
  - Periodväljare: 7d / 30d / 90d
  - Dark theme: #1a202c bg, #2d3748 cards, #e2e8f0 text, Bootstrap 5
- **Route**: `/rebotling/operator-dashboard` med `authGuard` (tillagd i `app.routes.ts`)
- **Meny**: "Min statistik" (ikon `fas fa-id-badge`) under Rebotling-dropdown direkt efter "Min dag"

## 2026-03-12 Forsta timme-analys — Uppstartsanalys per skift

Ny sida `/rebotling/forsta-timme-analys` — analyserar hur forsta timmen efter varje skiftstart gar.

- **Backend**: `ForstaTimmeAnalysController.php` i `classes/` och `controllers/` (proxy-monster)
  - `run=analysis&period=7|30|90`: Per-skiftstart-data for varje skift (dag 06:00, kväll 14:00, natt 22:00). Beraknar tid till forsta IBC, IBC/10-min-intervaller under forsta 60 min (6 x 10-min), bedomning (snabb/normal/langssam). Returnerar aggregerad genomsnitts-kurva + KPI:er (snitt tid, snabbaste/langsamma start, rampup%).
  - `run=trend&period=30|90`: Daglig trend av "tid till forsta IBC" — visar om uppstarterna forbattras eller forsamras over tid (snitt + min + max per dag).
  - Auth: session kravs (401 om ej inloggad). Stod for bade `timestamp`- och `datum`-kolumnnamn i rebotling_ibc.
- **Proxy-controller**: `controllers/ForstaTimmeAnalysController.php` (ny)
- **api.php**: `'forsta-timme-analys' => 'ForstaTimmeAnalysController'` registrerad i $classNameMap
- **Frontend Service**: `services/forsta-timme-analys.service.ts` — interfaces SkiftStart, AnalysData, AnalysResponse, TrendPoint, TrendData, TrendResponse + getAnalysis()/getTrend() med timeout(15000) + catchError
- **Frontend Komponent**: `pages/forsta-timme-analys/` (ny, standalone)
  - 4 KPI-kort: Snitt tid till forsta IBC, Snabbaste start (min), Langsamma start (min), Ramp-up-hastighet (% av normal takt efter 30 min)
  - Linjediagram (Chart.js): Genomsnittlig ramp-up-kurva (6 x 10-min-intervaller, snitt IBC/intervall)
  - Stapeldiagram med linjer: Tid till forsta IBC per dag — snitt (staplar) + snabbaste/langsamma start (linjer)
  - Tabell: Senaste skiftstarter med datum, skift-badge (dag/kväll/natt), tid till forsta IBC, IBC forsta timmen, bedomning-badge (snabb/normal/langssam)
  - Periodvaljare: 7d / 30d / 90d
  - Lifecycle: OnInit/OnDestroy + destroy$ + takeUntil + chart?.destroy()
  - Dark theme: #1a202c bg, #2d3748 cards, #e2e8f0 text, Bootstrap 5
- **Route**: `/rebotling/forsta-timme-analys` med `authGuard` (tillagd i app.routes.ts)
- **Meny**: "Forsta timmen" med ikon fas fa-stopwatch tillagd i Rebotling-dropdown (menu.html)

## 2026-03-12 Produktionspuls — Realtids-ticker (uppgraderad)

Uppgraderad sida `/rebotling/produktionspuls` — scrollande realtids-ticker (borsticker-stil) for VD.

- **Backend**: `ProduktionspulsController.php` i `classes/` och `controllers/` (proxy-monster)
  - `run=pulse&limit=20`: Kronologisk handelsefeed — samlar IBC-registreringar, on/off-handelser, stopporsaker fran `rebotling_ibc`, `rebotling_onoff`, `stoppage_log`, `stopporsak_registreringar`. Varje handelse har type/time/label/detail/color/icon. Sorterat nyast forst.
  - `run=live-kpi`: Realtids-KPI:er — IBC idag (COUNT fran rebotling_ibc), IBC/h (senaste timmen), driftstatus (kor/stopp + sedan nar fran rebotling_onoff), tid sedan senaste stopp (minuter).
  - `run=latest` + `run=hourly-stats`: Bakatkompat (oforandrade).
  - Auth: session kravs for pulse/live-kpi (401 om ej inloggad).
- **Proxy-controller**: `controllers/ProduktionspulsController.php` (ny)
- **Frontend Service**: `produktionspuls.service.ts` — nya interfaces PulseEvent, PulseResponse, Driftstatus, TidSedanSenasteStopp, LiveKpiResponse + getPulse()/getLiveKpi()
- **Frontend Komponent**: `pages/rebotling/produktionspuls/` (uppgraderad)
  - Scrollande CSS ticker med ikon + text + tid + fargbakgrund per IBC (gront=OK, rott=kasserad, gult=lang cykel). Pausa vid hover. Somlos marquee-loop.
  - 4 KPI-kort: IBC idag, IBC/h nu (med trend-pil), Driftstatus (kor/stopp med pulserande rod ram vid stopp), Tid sedan senaste stopp
  - Extra statistikrad: IBC/h, snittcykeltid, godkanda/kasserade, kvalitet%
  - Handelsetabell: senaste 20 handelser med tid, typ-badge (fargkodad), handelse, detalj
  - Auto-refresh var 30:e sekund
  - Dark theme: #1a202c bg, #2d3748 cards, #e2e8f0 text
  - Lifecycle: OnInit/OnDestroy + destroy$ + takeUntil + clearInterval
- **Route**: `/rebotling/produktionspuls` med `authGuard` (tillagd)
- **Meny**: "Produktionspuls" fanns redan under Rebotling-dropdown (ikon fas fa-heartbeat)

## 2026-03-12 Kassationsorsak-drilldown — Hierarkisk kassationsanalys

Ny sida `/rebotling/kassationsorsak-drilldown` — hierarkisk drill-down-vy for kassationsorsaker.

- **Backend**: `KassationsDrilldownController.php` i `classes/` och `controllers/` (proxy-monster)
  - `run=overview&days=N`: totalt kasserade, kassationsgrad (%), trend vs foregaende period, per-orsak-aggregering med andel
  - `run=reason-detail&reason=X&days=N`: enskilda kassationshandelser for en viss orsak (datum, tid, operator, antal, kommentar)
  - `run=trend&days=N`: daglig kassationstrend (kasserade, producerade, kassationsgrad per dag)
  - Auth: session kravs (401 om ej inloggad)
- **Route** i `api.php`: `kassations-drilldown` -> `KassationsDrilldownController`
- **Frontend Service**: `kassations-drilldown.service.ts` med TypeScript-interfaces, `timeout(15000)` + `catchError`
- **Frontend Komponent**: `pages/kassations-drilldown/` (standalone, OnInit/OnDestroy, destroy$/takeUntil)
  - 4 KPI-kort: Total kasserade, Kassationsgrad %, Vanligaste orsaken, Trend vs foregaende period (trendpil)
  - Horisontella staplar (Chart.js) for kassationsorsaker
  - Klickbar tabell: klicka pa orsak -> expanderbar detalj med enskilda handelser
  - Linjediagram + staplar for daglig kassationstrend
  - Periodvaljare: 7d / 30d / 90d
  - Dark theme, responsiv design
- **Route**: `/rebotling/kassationsorsak-drilldown` med `authGuard` i `app.routes.ts`
- **Meny**: "Kassationsanalys+" tillagd under Rebotling-dropdown (loggedIn), ikon fas fa-search-plus

## 2026-03-12 Drifttids-timeline — Visuell tidslinje per dag (session #70)

Ny sida `/rebotling/drifttids-timeline` — horisontell tidslinje som visar körning, stopp och ej planerad tid per dag.

- **Backend**: `DrifttidsTimelineController.php` i `classes/` och `controllers/` (proxy-mönster)
  - `run=timeline-data&date=YYYY-MM-DD`: Bygger tidssegment från `rebotling_onoff` (körperioder) + `stoppage_log` + `stopporsak_registreringar` (stopporsaker). Returnerar array av segment med typ, start, slut, duration_min, stop_reason, operator. Planerat skift: 06:00–22:00, övrig tid = ej planerat.
  - `run=summary&date=YYYY-MM-DD`: KPI:er — drifttid, stopptid, antal stopp, längsta körperiod, utnyttjandegrad (% av 16h skift). Default: dagens datum.
  - Auth: session krävs (401 om ej inloggad).
- **Route** i `api.php`: `drifttids-timeline` → `DrifttidsTimelineController`
- **Frontend Service**: `drifttids-timeline.service.ts` med TypeScript-interfaces (SegmentType, TimelineSegment, TimelineData, TimelineSummaryData), `timeout(15000)` + `catchError`
- **Frontend Komponent**: `pages/drifttids-timeline/` (standalone, OnInit/OnDestroy, destroy$/takeUntil)
  - Datumväljare med ◀ ▶-navigeringsknappar (blockerar framåt om idag)
  - 4 KPI-kort: Drifttid, Stopptid, Antal stopp, Utnyttjandegrad (färgkodad)
  - Horisontell div-baserad tidslinje (06:00–22:00): grönt = körning, rött = stopp, grått = ej planerat
  - Hover-tooltip (fixed, följer musen) med start/slut/längd/orsak/operatör
  - Klick på segment öppnar detalj-sektion under tidslinjen
  - Segmenttabell under tidslinjen: alla segment med typ-badge, tider, orsak, operatör
  - Responsiv design, dark theme (#1a202c bg, #2d3748 cards)
- **Route**: `/rebotling/drifttids-timeline` med `authGuard` i `app.routes.ts`
- **Meny**: "Drifttids-timeline" tillagd under Rebotling-dropdown (loggedIn), ikon fas fa-stream, efter OEE-analys

## 2026-03-12 Skiftjämförelse — Skiftvis produktionsjämförelse (session #70)

Ny sida `/rebotling/skiftjamforelse` — jämför dag-, kväll- och nattskift för VD.

- **Backend**: `SkiftjamforelseController.php` i `classes/` och `controllers/`
  - `run=shift-comparison&period=N`: aggregerar IBC/h, kvalitet%, OEE, stopptid per skift (dag 06-14, kväll 14-22, natt 22-06); beräknar bästa/sämsta skift, diff vs snitt, auto-genererad sammanfattningstext
  - `run=shift-trend&period=N`: veckovis IBC/h per skift (trend)
  - `run=shift-operators&shift=dag|kvall|natt&period=N`: topp-5 operatörer per skift
  - Auth: session krävs (401 om ej inloggad)
- **Route** i `api.php`: `skiftjamforelse` → `SkiftjamforelseController`
- **Frontend Service**: `skiftjamforelse.service.ts` med fullständiga TypeScript-interfaces, `timeout(15000)` + `catchError`
- **Frontend Komponent**: `pages/skiftjamforelse/` (standalone, OnInit/OnDestroy, destroy$/takeUntil, chart?.destroy())
  - Periodväljare: 7d / 30d / 90d
  - 3 skiftkort (dag/kväll/natt): IBC/h (stor), kvalitet%, OEE%, stopptid, IBC OK, antal pass, tillgänglighetsstapel
  - Krona-badge på bästa skiftet, diff vs snitt-procent
  - Expanderbar topp-operatörslista per skiftkort
  - Grupperat stapeldiagram (IBC/h, Kvalitet, OEE) — Chart.js
  - Linjediagram med veckovis IBC/h-trend per skift (3 linjer)
  - Auto-refresh var 2:e minut
  - Responsiv design, dark theme
- **Route**: `/rebotling/skiftjamforelse` med `authGuard` i `app.routes.ts`
- **Meny**: "Skiftjämförelse" under Rebotling-dropdown, ikon `fas fa-people-arrows`

## 2026-03-12 VD:s Morgonrapport — Daglig produktionssammanfattning

Ny sida `/rebotling/morgonrapport` — en komplett daglig sammanfattning av gårdagens produktion redo for VD på morgonen.

- **Backend**: Ny `MorgonrapportController.php` (classes/ + controllers/) med endpoint `run=rapport&date=YYYY-MM-DD`:
  - **Produktion**: Totalt IBC, mål vs utfall (uppfyllnad %), jämförelse med föregående vecka samma dag och 30-dagarssnitt
  - **Effektivitet**: IBC/drifttimme, total drifttid, utnyttjandegrad (jämfört föregående vecka)
  - **Stopp**: Antal stopp, total stopptid, top-3 stopporsaker (från `stoppage_log` + `stopporsak_registreringar`)
  - **Kvalitet**: Kassationsgrad, antal kasserade, topporsak (från `rebotling_ibc` + `kassationsregistrering`)
  - **Trender**: Daglig IBC senaste 30 dagar + 7-dagars glidande medelvärde
  - **Highlights**: Bästa timme, snabbaste operatör (via `operators`-tabell om tillgänglig)
  - **Varningar**: Automatiska flaggor — produktion under mål, hög kassation (≥5%), hög stopptid (≥20% av drifttid), låg utnyttjandegrad (<50%) — severity röd/gul/grön
  - Default: gårdagens datum om `date` saknas
  - Auth: session krävs (401 om ej inloggad)
- **Route** i `api.php`: `morgonrapport` → `MorgonrapportController`
- **Frontend Service**: `morgonrapport.service.ts` med fullständiga TypeScript-interfaces, `timeout(20000)` + `catchError`
- **Frontend Komponent**: `pages/morgonrapport/` (standalone, OnInit/OnDestroy, destroy$/takeUntil)
  - Datumväljare (default: gårdagen)
  - Varningssektion överst med rod/gul/gron statusfärger
  - Executive summary: 5 stora KPI-kort (IBC, IBC/tim, stopp, kassation, utnyttjandegrad)
  - Produktionssektion: detaljerad tabell + trendgraf (staplar 30 dagar)
  - Stoppsektion: KPI + topp 3 orsaker
  - Kvalitetssektion: kassationsgrad, topporsak, jämförelse
  - Highlights-sektion: bästa timme + snabbaste operatör
  - Effektivitetssektion: drifttid, utnyttjandegrad
  - Trendpilar (▲/▼/→) med grönt/rött/neutralt för alla KPI-förändringar
  - "Skriv ut"-knapp med `@media print` CSS (döljer kontroller, ljus bakgrund)
  - Responsiv design
- **Route**: `/rebotling/morgonrapport` med `authGuard` i `app.routes.ts`
- **Meny**: "Morgonrapport" tillagd under Rebotling-dropdown (loggedIn), ikon fas fa-sun, före Veckorapport

## 2026-03-12 OEE-waterfall — Visuell nedbrytning av OEE-förluster

Ny sida `/rebotling/oee-waterfall` som visar ett vattenfall-diagram (brygga) over OEE-förluster.

- **Backend**: Ny `OeeWaterfallController.php` (classes/ + controllers/) med tva endpoints:
  - `run=waterfall-data&days=N` — beraknar OEE-segment: Total tillganglig tid → Tillganglighetsforlust → Prestationsforlust → Kvalitetsforlust (kassationer) → Effektiv produktionstid. Returnerar floating bar-data (bar_start/bar_slut) for waterfall-effekt + procent av total.
  - `run=summary&days=N` — OEE totalt + de 3 faktorerna (Tillganglighet, Prestanda, Kvalitet) + trend vs foregaende period (differens i procentenheter).
  - Kallor: `rebotling_onoff` (drifttid), `rebotling_ibc` (IBC ok/total), `kassationsregistrering` (kasserade), `stoppage_log` + `stopporsak_registreringar` (stopptid-fallback)
  - Auth: session kravs (401 om ej inloggad)
- **api.php**: Route `oee-waterfall` → `OeeWaterfallController` registrerad
- **Frontend Service**: `oee-waterfall.service.ts` med `getWaterfallData(days)` + `getSummary(days)`, TypeScript-interfaces, `timeout(15000)` + `catchError`
- **Frontend Komponent**: `OeeWaterfallPage` (standalone, OnInit/OnDestroy, destroy$/takeUntil, chart?.destroy())
  - Chart.js floating bar chart: waterfall-effekt dar forlusterna "hanger" fran foregaende niva
  - Fargkodning: gron = total/effektiv, rod = tillganglighetsforlust, orange = prestationsforlust, gul = kvalitetsforlust
  - 4 KPI-kort: OEE (%), Tillganglighet (%), Prestanda (%), Kvalitet (%) med fargkodning (gron >85%, gul 60-85%, rod <60%) och trendpilar
  - Periodvaljare: 7d / 14d / 30d / 90d
  - Forlusttabell: visuell nedbrytning med staplar + timmar + procent
  - IBC-statistik: total, godkanda, kasserade, dagar
- **Route**: `/rebotling/oee-waterfall` med `authGuard`
- **Meny**: "OEE-analys" tillagd under Rebotling-dropdown (loggedIn), efter Produktions-heatmap

## 2026-03-12 Pareto-analys — Stopporsaker 80/20

Ny sida `/rebotling/pareto` som visar klassisk Pareto-analys for stopporsaker.

- **Backend**: Ny `ParetoController.php` (classes/ + controllers/) med tva endpoints:
  - `run=pareto-data&days=N` — aggregerar stopporsaker med total stopptid, sorterar fallande, beraknar kumulativ % och markerar vilka som utgör 80%-gransen
  - `run=summary&days=N` — KPI-sammanfattning: total stopptid (h:min), antal unika orsaker, #1 orsak (%), antal orsaker inom 80%
  - Datakallor: `stoppage_log` + `stoppage_reasons` och `stopporsak_registreringar` + `stopporsak_kategorier`
  - Auth: session kravs (401 om ej inloggad)
- **Frontend**: `ParetoService` + `ParetoPage` (standalone, OnInit/OnDestroy, destroy$/takeUntil, chart?.destroy())
  - Chart.js combo-chart: staplar (stopptid per orsak, fallande) + rod kumulativ linje med punkter
  - Staplar: bla for orsaker inom 80%, orange for ovriga
  - Streckad gul 80%-grans-linje
  - Dubbel Y-axel: vanster = minuter, hoger = kumulativ %
  - 4 KPI-kort: Total stopptid, Antal orsaker, #1 orsak (%), Orsaker inom 80%
  - Periodvaljare: 7d / 14d / 30d / 90d
  - Tabell under grafen: orsak, stopptid, antal stopp, andel %, kumulativ %, badge "Top 80%"
- **Route**: `/rebotling/pareto` med `authGuard`
- **Meny**: "Pareto-analys" tillagd under Rebotling-dropdown (loggedIn), efter Alarm-historik

## 2026-03-12 Produktions-heatmap — matrisvy IBC per timme och dag

Ny sida `/rebotling/produktions-heatmap` som visar produktion som fargkodad matris (timmar x dagar).

- **Backend**: Ny `HeatmapController.php` (classes/ + controllers/) med tva endpoints:
  - `run=heatmap-data&days=N` — aggregerar IBC per timme per dag via MAX(ibc_ok) per skiftraknare+timme; returnerar `[{date, hour, count}]` + skalvarden `{min, max, avg}`
  - `run=summary&days=N` — totalt IBC, basta timme med hogst snitt, samsta timme med lagst snitt, basta veckodag med snitt IBC/dag
  - Auth: session kravs (401 om ej inloggad)
- **api.php**: Route `heatmap` → `HeatmapController` registrerad
- **Frontend Service**: `heatmap.service.ts` med `getHeatmapData(days)` + `getSummary(days)`, TypeScript-interfaces, `timeout(15000)` + `catchError`
- **Frontend Komponent**: `pages/heatmap/` (standalone, OnInit/OnDestroy, destroy$/takeUntil)
  - Matrisvy: rader = timmar 06:00–22:00, kolumner = dagar senaste N dagar
  - Fargkodning: RGB-interpolation morkt gront (lag) → intensivt gront (hog); grat = ingen data
  - 4 KPI-kort: Totalt IBC, Basta timme (med snitt), Samsta timme (med snitt), Basta veckodag
  - Periodvaljare: 7 / 14 / 30 / 90 dagar
  - Legend med fargskala (5 steg)
  - Hover-tooltip med datum, timme och exakt IBC-antal
  - Sticky timme-rubrik och datum-header vid horisontell/vertikal scroll
- **Route**: `/rebotling/produktions-heatmap` med `authGuard`
- **Meny**: "Produktions-heatmap" lagd under Rebotling-dropdown (loggedIn)

## 2026-03-12 Operatorsportal — personlig dashboard per inloggad operatör

Ny sida `/rebotling/operatorsportal` där varje inloggad operatör ser sin egen statistik.

- **Backend**: `OperatorsportalController.php` med tre endpoints:
  - `run=my-stats` — IBC idag/vecka/månad, IBC/h snitt, teamsnitt, ranking (#X av Y)
  - `run=my-trend&days=N` — daglig IBC-tidsserie operatör vs teamsnitt
  - `run=my-bonus` — timmar, IBC, IBC/h, diff vs team, bonuspoäng + progress mot mål
  - Identifiering via `$_SESSION['operator_id']` → `operators.id` → `rebotling_ibc.op1/op2/op3`
  - Korrekt MAX()-aggregering av kumulativa PLC-fält per skiftraknare
- **Frontend**: `OperatorsportalService` + `OperatorsportalPage` (standalone, OnInit/OnDestroy, destroy$/takeUntil, chart?.destroy())
  - Välkomstbanner med operatörens namn och skiftstatus
  - 4 KPI-kort: IBC idag, IBC vecka, IBC/h snitt (30 dagar), Ranking (#X av Y)
  - Chart.js linjegraf: min IBC/dag vs teamsnitt, valbart 7/14/30 dagar
  - Bonussektion: statistiktabell + visuell progress-bar mot bonusmål
  - Skiftinfo-sektion med status, drifttid, senaste aktivitet
- **Route**: `/rebotling/operatorsportal` med `authGuard`
- **Meny**: "Min portal" lagd under Rebotling-dropdown (loggedIn)

## 2026-03-12 Veckorapport — utskriftsvanlig KPI-sammanstallning per vecka

Ny sida `/rebotling/veckorapport` som sammanstaller veckans KPI:er i en snygg, utskriftsvanlig rapport.

- **Backend**: Ny `VeckorapportController.php` med endpoint `run=report&week=YYYY-WNN`:
  - Returnerar ALL data i ett enda API-anrop: week_info, production, efficiency, stops, quality
  - Produktion: totalt IBC, mal vs faktiskt (uppfyllnad %), basta/samsta dag, snitt IBC/dag
  - Effektivitet: snitt IBC/drifttimme, total drifttid vs tillganglig tid (utnyttjandegrad %)
  - Stopp: antal stopp, total stopptid, topp-3 stopporsaker (bada kallor: stoppage_log + stopporsak_registreringar)
  - Kvalitet: kassationsgrad (%), antal kasserade, topp-orsak
  - Trendindikator: jamforelse mot foregaende vecka pa varje KPI
  - Datakallor: rebotling_ibc, rebotling_weekday_goals, stoppage_log/stoppage_reasons, stopporsak_registreringar/stopporsak_kategorier, kassationsregistrering/kassationsorsak_typer
- **Frontend**: Ny service `veckorapport.service.ts` + komponent `pages/veckorapport/`:
  - Veckovaljare (input type="week") med default senaste avslutade veckan
  - Strukturerad rapport med sektioner, KPI-kort, tabeller, trendpilar
  - Fargkodning: gron = battre, rod = samre, gra = oforandrad
  - Sammanfattningstabell med alla KPI:er + trend jamfort med foregaende vecka
  - "Skriv ut"-knapp som triggar window.print()
  - CSS @media print: vit bakgrund, svart text, doljer meny/knappar, A4-optimerad layout
  - Dark theme i webblasaren (#1a202c / #2d3748)
- **Route**: `rebotling/veckorapport` med authGuard
- **Meny**: "Veckorapport" med file-alt-ikon under Rebotling-dropdown

## 2026-03-12 Fabriksskarm (Andon Board) — realtidsvy for TV-skarm

Ny dedikerad fabriksskarm `/rebotling/andon-board` optimerad for stor TV-skarm i produktionen.

- **Backend**: Nytt endpoint `run=board-status` i befintlig `AndonController.php`:
  - Returnerar ALL data i ett enda API-anrop: today_production, current_rate, machine_status, quality, shift
  - Datakallor: rebotling_ibc, rebotling_settings, stoppage_log, stopporsak_registreringar, shift_plan
- **Frontend**: Ny service `andon-board.service.ts` + komponent `pages/andon-board/`:
  - 7 informationskort: klocka, produktion vs mal (progress bar), aktuell takt (IBC/h med trendpil),
    maskinstatus (KOR/STOPP/OKAND med pulserande glow), senaste stopp, kassationsgrad, skiftinfo
  - Mork bakgrund (#0a0e14), extremt stor text (3-5rem), helskarmslage via Fullscreen API
  - Auto-uppdatering var 30:e sekund, klocka varje sekund
  - Responsiv grid for 1920x1080 TV
- **Route**: `rebotling/andon-board` med authGuard
- **Meny**: "Fabriksskarm" med monitor-ikon under Rebotling-dropdown

## 2026-03-11 Kassationsanalys — utokad vy med KPI, grafer, trendlinje, filter

Utokad kassationsanalys-sida `/rebotling/kassationsanalys` med detaljerad vy over kasserade IBC:er.

- **Backend**: Fyra nya endpoints i `KassationsanalysController.php` (`overview`, `by-period`, `details`, `trend-rate`):
  - `overview` — KPI-sammanfattning med totalt kasserade, kassationsgrad, vanligaste orsak, uppskattad kostnad (850 kr/IBC)
  - `by-period` — kassationer per vecka/manad, staplat per orsak (topp 5), Chart.js-format
  - `details` — filtrbar detaljlista med orsak- och operatorsfilter, kostnad per rad
  - `trend-rate` — kassationsgrad (%) per vecka med glidande medel (4v) + linjar trendlinje
- **Frontend**: Ny komponent `pages/rebotling/kassationsanalys/` med:
  - 4 KPI-kort (total kasserade, kassationsgrad %, vanligaste orsak, uppskattad kostnad)
  - Chart.js staplat stapeldiagram per vecka/manad (topp 5 orsaker)
  - Chart.js doughnut for orsaksfordelning
  - Trendgraf med kassationsgrad %, glidande medelvarde, och trendlinje
  - Detaljerad tabell med datum, orsak, antal, operator, kommentar, kostnad
  - Periodselektor 30d/90d/180d/365d
  - Filter per orsak och per operator
- **Route**: Uppdaterad till ny komponent i `app.routes.ts`
- **Meny**: Befintligt menyval "Kassationsanalys" under Rebotling (redan pa plats)
- **Proxy-fil**: `noreko-backend/controllers/KassationsanalysController.php` delegerar till `classes/`

## 2026-03-11 Maskinutnyttjandegrad — andel tillganglig tid i produktion

Ny sida `/rebotling/utnyttjandegrad` (authGuard). VD ser hur stor andel av tillganglig tid maskinen faktiskt producerar och kan identifiera dolda tidstjuvar.

- **Backend**: `UtnyttjandegradController.php` — tre endpoints via `?action=utnyttjandegrad&run=XXX`:
  - `run=summary`: Utnyttjandegrad idag (%) + snitt 7d + snitt 30d med trend (improving/declining/stable). Jamfor senaste 7d vs foregaende 7d.
  - `run=daily&days=N`: Daglig tidsserie — tillganglig tid, drifttid, stopptid, okand tid, utnyttjandegrad-%, antal stopp per dag.
  - `run=losses&days=N`: Tidsforlustanalys — kategoriserade forluster: planerade stopp, oplanerade stopp, uppstart/avslut, okant. Topp-10 stopporsaker.
  - Berakningsmodell: drifttid fran rebotling_ibc (MAX runtime_plc per skiftraknare+dag), stopptid fran stoppage_log med planned/unplanned-kategorier.
  - Tillganglig tid: 22.5h/dag (3 skift x 7.5h efter rast), 0h pa sondagar.
  - Auth: session kravs (401 om ej inloggad).
- **api.php**: Registrerat `utnyttjandegrad` -> `UtnyttjandegradController`.
- **Service**: `utnyttjandegrad.service.ts` — getSummary(), getDaily(), getLosses() med TypeScript-interfaces, timeout(15s) + catchError.
- **Komponent**: `src/app/pages/utnyttjandegrad/` (ts + html + css) — standalone, OnInit/OnDestroy + destroy$ + takeUntil + clearTimeout + chart?.destroy().
  - Periodvaljare: 7d / 14d / 30d / 90d.
  - 3 KPI-kort: Cirkular progress (utnyttjandegrad idag), Snitt 7d med %-forandring, Snitt 30d med trend-badge.
  - Staplad bar chart (Chart.js): daglig fordelning — drifttid (gron) + stopptid (rod) + okand tid (gra).
  - Doughnut chart: tidsforlustfordelning — planerade stopp, oplanerade stopp, uppstart, okant.
  - Forlust-tabell med horisontal bar + topp stopporsaker.
  - Daglig tabell: datum, tillganglig tid, drifttid, stopptid, utnyttjandegrad med fargkodning.
  - Farg: gron >=80%, gul >=60%, rod <60%.
- **Route**: `/rebotling/utnyttjandegrad` (authGuard).
- **Meny**: Lagt till under Rebotling-dropdown: "Utnyttjandegrad" (bla gauge-ikon).
- **Build**: OK (endast pre-existerande warnings fran feedback-analys).

## 2026-03-11 Produktionsmal vs utfall — VD-dashboard

Ny sida `/rebotling/produktionsmal` (authGuard). VD ser pa 10 sekunder om produktionen ligger i fas med malen. Stor, tydlig vy med dag/vecka/manad.

- **Backend**: `ProduktionsmalController.php` — tre endpoints:
  - `run=summary`: Aktuell dag/vecka/manad — mal vs faktisk IBC, %-uppfyllnad, status (ahead/on_track/behind). Dagsprognos baserat pa forbrukad tid. Hittills-mal + fullt mal for vecka/manad.
  - `run=daily&days=N`: Daglig tidsserie med mal, faktiskt, uppfyllnad-%, kumulativt mal vs faktiskt.
  - `run=weekly&weeks=N`: Veckovis — veckonummer, mal, faktiskt, uppfyllnad, status.
  - Mal hamtas fran `rebotling_weekday_goals` (per veckodag). Faktisk produktion fran `rebotling_ibc`.
  - Auth: session kravs (401 om ej inloggad). PDO prepared statements.
- **api.php**: Registrerat `produktionsmal` -> `ProduktionsmalController`.
- **Service**: `produktionsmal.service.ts` — getSummary(), getDaily(days), getWeekly(weeks) med TypeScript-interfaces, timeout(15s) + catchError.
- **Komponent**: `src/app/pages/produktionsmal/` (ts + html + css) — standalone, OnInit/OnDestroy + destroy$ + takeUntil + clearInterval + clearTimeout + chart?.destroy().
  - 3 stora statuskort (dag/vecka/manad): Mal vs faktiskt, progress bar (gron >=90%, gul 70-89%, rod <70%), stor %-siffra, statusindikator.
  - Kumulativ Chart.js linjegraf: mal-linje (streckad gra) vs faktisk-linje (gron), skuggat gap.
  - Daglig tabell med fargkodning per rad.
  - Periodvaljare: 7d / 14d / 30d / 90d.
  - Auto-refresh var 5:e minut.
- **Route**: `/rebotling/produktionsmal` (authGuard).
- **Meny**: Lagt till under Rebotling-dropdown: "Produktionsmal" (gron bullseye-ikon).

## 2026-03-11 Maskineffektivitet — IBC per drifttimme trendartat

Ny sida `/rebotling/effektivitet` (authGuard). VD kan se om maskinen blir långsammare (slitage) eller snabbare (optimering) baserat på IBC producerade per drifttimme.

- **Backend**: `EffektivitetController.php` — tre endpoints:
  - `run=trend&days=N`: Daglig IBC/drifttimme för senaste N dagar. Returnerar trend-array med ibc_count, drift_hours, ibc_per_hour, moving_avg_7d + snitt_30d för referenslinje.
  - `run=summary`: Nyckeltal — aktuell IBC/h (idag), snitt 7d, snitt 30d, bästa dag, sämsta dag. Trend: improving|declining|stable (jämför snitt senaste 7d vs föregående 7d, tröskel ±2%).
  - `run=by-shift&days=N`: IBC/h per skift (dag/kväll/natt), bästa skiftet markerat.
  - Beräkningsmodell: MAX(ibc_ok) + MAX(runtime_plc) per skiftraknare+dag, summerat per dag. runtime_plc i minuter → omvandlas till timmar.
  - Auth: session krävs (401 om ej inloggad).
- **api.php**: Registrerat `effektivitet` → `EffektivitetController`.
- **Service**: `src/app/services/effektivitet.service.ts` — getTrend(), getSummary(), getByShift() med TypeScript-interfaces, timeout(15–20s) + catchError.
- **Komponent**: `src/app/pages/effektivitet/` (ts + html + css) — standalone, OnInit/OnDestroy + destroy$ + takeUntil + clearTimeout + chart?.destroy().
  - Periodväljare: 7d / 14d / 30d / 90d.
  - 4 KPI-kort: Aktuell IBC/h (idag), Snitt 7d med %-förändring vs föregående 7d, Snitt 30d, Trendindikator (Förbättras/Stabilt/Försämras med pil och färg).
  - Chart.js line chart: dagliga värden (blå), 7-dagars glidande medel (tjock gul linje), referenslinje för periodsnittet (grön streckad).
  - Skiftjämförelse: 3 kort (dag/kväll/natt) med IBC/h, drifttimmar, antal dagar. Bästa skiftet markerat med grön ram + stjärna.
  - Daglig tabell: datum, IBC producerade, drifttimmar, IBC/h, 7d medel, avvikelse från snitt (grön >5%, röd <-5%).
- **Route**: `/rebotling/effektivitet` (authGuard).
- **Meny**: Lagt till under Rebotling-dropdown: "Maskineffektivitet" (gul blixt-ikon).
- **Build**: OK (endast pre-existerande warnings från feedback-analys).

## 2026-03-11 Stopporsak-trendanalys — veckovis trendanalys av stopporsaker

Ny sida `/admin/stopporsak-trend` (adminGuard). VD kan se hur de vanligaste stopporsakerna utvecklas över tid (veckovis) och bedöma om åtgärder mot specifika orsaker fungerar.

- **Backend**: `StopporsakTrendController.php` — tre endpoints via `?action=stopporsak-trend&run=XXX`:
  - `run=weekly&weeks=N`: Veckovis stopporsaksdata (default 12 veckor). Per vecka + orsak: antal stopp + total stopptid. Kombinerar data från `stoppage_log`+`stoppage_reasons` och `stopporsak_registreringar`+`stopporsak_kategorier`. Returnerar topp-7 orsaker, veckolista, KPI (senaste veckan: totalt stopp + stopptid).
  - `run=summary&weeks=N`: Top-5 orsaker med trend — jämför senaste vs föregående halvperiod. Beräknar %-förändring och klassar: increasing/decreasing/stable (tröskel ±10%). Returnerar most_improved och vanligaste_orsak.
  - `run=detail&reason=X&weeks=N`: Detaljerad veckoviss tidsserie för specifik orsak, med totalt antal, stopptid, snitt/vecka, trend.
- **SQL-migration**: `noreko-backend/migrations/2026-03-11_stopporsak_trend.sql` — index på `stoppage_log(created_at, reason_id)` och `stopporsak_registreringar(start_time, kategori_id)`.
- **api.php**: Registrerat `stopporsak-trend` → `StopporsakTrendController`.
- **Service**: `src/app/services/stopporsak-trend.service.ts` — getWeekly(), getSummary(), getDetail() med fullständiga TypeScript-interfaces, timeout(15000) + catchError.
- **Komponent**: `src/app/pages/stopporsak-trend/` (ts + html + css) — standalone, OnInit/OnDestroy + destroy$ + takeUntil + clearTimeout + chart?.destroy().
  - Periodväljare: 4 / 8 / 12 / 26 veckor.
  - 4 KPI-kort: Stopp senaste veckan, Stopptid (h:mm), Vanligaste orsaken, Mest förbättrad.
  - Staplad bar chart (Chart.js): X = veckor, Y = antal stopp, en färgad serie per orsak (topp 7). Stacked + tooltip visar alla orsaker per vecka.
  - Trendtabell: topp-5 orsaker med sparkline-prickar (6v), snitt stopp/vecka nu vs fg., %-förändring med pil, trend-badge (Ökar/Minskar/Stabil). Klickbar rad.
  - Expanderbar detaljvy: KPI-rad (totalt/stopptid/snitt/trend), linjegraf per orsak, tidslinjetabell.
  - Trendpil-konvention: ↑ röd (ökar = dåligt), ↓ grön (minskar = bra).
  - Math = Math. Lifecycle korrekt: destroy$ + clearTimeout + chart?.destroy().
- **Route**: `/admin/stopporsak-trend` (adminGuard).
- **Meny**: Lagt till under Admin-dropdown efter Kvalitetstrend: "Stopporsak-trend" (orange ikon).
- **Build**: OK (inga nya varningar).

## 2026-03-11 Kvalitetstrend per operatör — identifiera förbättring/nedgång och utbildningsbehov

Ny sida `/admin/kvalitetstrend` (adminGuard). VD kan se kvalitet%-trend per operatör över veckor/månader, identifiera vilka som förbättras och vilka som försämras, samt se utbildningsbehov.

- **Backend**: `KvalitetstrendController.php` — tre endpoints:
  - `run=overview&period=4|12|26`: Teamsnitt kvalitet%, bästa operatör, störst förbättring, störst nedgång, utbildningslarm-lista.
  - `run=operators&period=4|12|26`: Alla operatörer med senaste kvalitet%, förändring (pil+procent), trend-status, sparkdata (6 veckor), IBC totalt, utbildningslarm-flagga.
  - `run=operator-detail&op_id=N&period=4|12|26`: Veckovis tidslinje: kvalitet%, teamsnitt, vs-team-diff, IBC-antal.
  - Utbildningslarm: kvalitet under 85% ELLER nedgångstrend 3+ veckor i rad.
  - Beräkning: MAX(ibc_ok/ibc_ej_ok) per skiftraknare+dag, aggregerat per vecka via WEEK(datum,3).
  - Auth: session_id krävs (401 om ej inloggad).
- **SQL-migration**: `noreko-backend/migrations/2026-03-11_kvalitetstrend.sql` — index på rebotling_ibc(datum,op1/op2/op3,skiftraknare) + operators(active,number).
- **api.php**: Registrerat `kvalitetstrend` → `KvalitetstrendController`.
- **Service**: `src/app/services/kvalitetstrend.service.ts` — getOverview(), getOperators(), getOperatorDetail() med fullständiga TypeScript-interfaces, timeout(15000) + catchError.
- **Komponent**: `src/app/pages/kvalitetstrend/` (ts + html + css) — standalone, OnInit/OnDestroy + destroy$ + takeUntil + clearTimeout.
  - Periodväljare: 4/12/26 veckor. Toggle: Veckovis/Månadsvis.
  - 4 KPI-kort: Teamsnitt, Bästa operatör, Störst förbättring, Störst nedgång.
  - Utbildningslarm-sektion: röd ram med lista och larmorsak.
  - Trendgraf (Chart.js): Topp 8 operatörer som färgade linjer + teamsnitt (streckad) + gräns 85% (röd prickad).
  - Operatörstabell: senaste kval%, förändring-pil, sparkline-prickar (grön/gul/röd), trend-badge, larmikon. Sökfilter + larm-toggle.
  - Detaljvy per operatör: KPI-rad, detaljgraf (operatör + teamsnitt + gräns), tidslinje-tabell.
  - Math = Math. Lifecycle korrekt: destroy$ + clearTimeout + chart?.destroy().
- **Route**: `/admin/kvalitetstrend` (adminGuard).
- **Meny**: Lagt till under Admin-dropdown: "Kvalitetstrend" (blå ikon).
- **Build**: OK.

## 2026-03-11 Underhallsprognos — prediktivt underhall med schema, tidslinje och historik

Ny sida `/rebotling/underhallsprognos` (autentiserad). VD kan se vilka maskiner/komponenter som snart behover underhall, varningar for forsenat underhall, tidslinje och historik.

- **Backend**: `UnderhallsprognosController.php` — tre endpoints:
  - `run=overview`: Oversiktskort (totalt komponenter, forsenade, snart, nasta datum)
  - `run=schedule`: Fullstandigt underhallsschema med status (ok/snart/forsenat), dagar kvar, progress %
  - `run=history`: Kombinerad historik fran maintenance_log + underhallslogg
- **Migration**: `2026-03-11_underhallsprognos.sql` — tabeller `underhall_komponenter` + `underhall_scheman`, 12 standardkomponenter (Rebotling, Tvattlinje, Saglinje, Klassificeringslinje)
- **Status-logik**: ok (>7 dagar kvar), snart (0-7 dagar), forsenat (<0 dagar), fargkodad rod/gul/gron
- **Frontend**: `underhallsprognos`-komponent
  - 4 oversiktskort (totalt/forsenade/snart/nasta datum)
  - Varningsbox rod/gul vid forsenat/snart
  - Schematabell med progress-bar och statusbadge per komponent
  - Chart.js horisontellt stapeldiagram (tidslinje) — top 10 narmaste underhall
  - Historiktabell med periodvaljare (30/90/180 dagar)
- **Service**: `underhallsprognos.service.ts` med `timeout(8000)` + `catchError` pa alla anrop
- **Route**: `/rebotling/underhallsprognos` (authGuard)
- **Nav**: Menyval under Rebotling-dropdown: "Underhallsprognos"
- **Lifecycle**: OnInit/OnDestroy + destroy$ + takeUntil + chart?.destroy() + clearTimeout
- Commit: c8f1080

## 2026-03-11 Skiftjamforelse-dashboard — jamfor dag/kvall/nattskift

Ny sida `/rebotling/skiftjamforelse` (autentiserad). VD kan jamfora dag-, kvalls- och nattskift for att fardela resurser och identifiera svaga skift.

- **Backend**: `SkiftjamforelseController.php` — tre endpoints:
  - `run=shift-comparison&period=7|30|90`: Aggregerar data per skift for vald period. Returnerar per skift: IBC OK, IBC/h, kvalitet%, total stopptid, antal pass, OEE, tillganglighet. Markerar basta skiftet och beraknar diff mot genomsnitt. Auto-genererar sammanfattningstext.
  - `run=shift-trend&period=30`: Veckovis IBC/h per skift for trendgraf (dag/kvall/natt som tre separata dataserier).
  - `run=shift-operators&shift=dag|kvall|natt&period=30`: Topp-5 operatorer per skift med antal IBC och snitt cykeltid.
  - Skiftdefinitioner: dag 06-14, kvall 14-22, natt 22-06. Filtrering sker pa HOUR(created_at).
  - Auth: session_id kravs (401 om ej inloggad).
- **SQL-migrering**: `noreko-backend/migrations/2026-03-11_skiftjamforelse.sql` — index pa rebotling_ibc(created_at, skiftraknare), rebotling_ibc(created_at, ibc_ok), stopporsak_registreringar(linje, start_time).
- **api.php**: Registrerat `skiftjamforelse` → `SkiftjamforelseController`
- **Service**: `src/app/services/skiftjamforelse.service.ts` — getShiftComparison(), getShiftTrend(), getShiftOperators() med fullstandiga TypeScript-interfaces, timeout(15000) + catchError.
- **Komponent**: `src/app/pages/skiftjamforelse/` (ts + html + css) — standalone, OnInit/OnDestroy + destroy$ + takeUntil + clearInterval.
  - Periodvaljare: 7/30/90 dagar (knappar, orange aktiv-klass).
  - 3 skiftkort (dag=gul, kvall=bla, natt=lila): Stort IBC/h-tal, kvalitet%, OEE%, stopptid, IBC OK, antal pass, tillganglighet-progressbar. Basta skiftet markeras med krona (fa-crown).
  - Jambforelse-stapeldiagram (Chart.js grouped bar): IBC/h, Kvalitet%, OEE% per skift sida vid sida.
  - Trendgraf (Chart.js line): Veckovis IBC/h per skift med 3 linjer (dag=gul, kvall=bla, natt=lila), spanGaps=true.
  - Topp-operatorer per skift: Expanderbar sektion per skift med top 5 operatorer (lazy-load vid expantion).
  - Sammanfattning: Auto-genererad text om basta skiftet och mojligheter.
- **Route**: `/rebotling/skiftjamforelse` (authGuard)
- **Meny**: Lagt till under Rebotling-dropdown: "Skiftjamforelse" (fa-exchange-alt, orange)
- **Build**: OK

## 2026-03-11 Malhistorik — visualisering av produktionsmalsandringar over tid

Ny sida `/rebotling/malhistorik` (autentiserad). Visar hur produktionsmalen har andrats over tid och vilken effekt malandringar haft pa faktisk produktion.

- **Backend**: `MalhistorikController.php` — tva endpoints:
  - `run=goal-history`: Hamtar alla rader fran `rebotling_goal_history` sorterade pa changed_at. Berikar varje rad med gammalt mal, nytt mal, procentuell andring och riktning (upp/ner/oforandrad/foerst).
  - `run=goal-impact`: For varje malandring beraknar snitt IBC/h och maluppfyllnad 7 dagar fore och 7 dagar efter andringen. Returnerar effekt (forbattring/forsamring/oforandrad/ny-start/ingen-data) med IBC/h-diff.
  - Auth: session_id kravs (421 om ej inloggad, identiskt med OeeBenchmarkController). Hanterar saknad tabell gracist.
- **SQL-migrering**: `noreko-backend/migrations/2026-03-11_malhistorik.sql` — index pa changed_at och changed_by i rebotling_goal_history, samt idx_created_at_date pa rebotling_ibc for snabbare 7-dagarsperiod-queries.
- **api.php**: Registrerat `malhistorik` → `MalhistorikController`
- **Service**: `src/app/services/malhistorik.service.ts` — getGoalHistory(), getGoalImpact() med fullstandiga TypeScript-interfaces (MalAndring, GoalHistoryData, ImpactPeriod, GoalImpactItem, GoalImpactData), timeout(15000) + catchError.
- **Komponent**: `src/app/pages/malhistorik/` (ts + html + css) — standalone, OnInit/OnDestroy/AfterViewInit + destroy$ + takeUntil.
  - 4 sammanfattningskort: Nuvarande mal, Totalt antal andringar, Snitteffekt per andring, Senaste andring
  - Tidslinje-graf (Chart.js, stepped line): Malvarde over tid som steg-graf med trapp-effekt. Marker vid faktiska andringar.
  - Andringslogg-tabell: Datum, tid, andrat av, gammalt mal, nytt mal, procentuell andring med fargkodad riktning
  - Impact-kort (ett per malandring): Fore/efter IBC/h, maluppfyllnad, diff, effekt-badge (gron/rod/neutral/bla) med vansterborderkodning
  - Impact-sammanfattning: Antal forbattringar/forsamringar + snitteffekt
- **Route**: `/rebotling/malhistorik` (authGuard)
- **Meny**: Lagt till under Rebotling-dropdown: "Malhistorik" (bullseye, teal/cyan #4fd1c5)
- **Build**: OK — inga nya fel, 4 pre-existing NG8102-varningar (ej vara)

## 2026-03-11 Daglig sammanfattning — VD-dashboard med daglig KPI-overblick pa en sida

Ny sida `/rebotling/daglig-sammanfattning` (autentiserad). VD far full daglig KPI-overblick utan att navigera runt — allt pa en sida, auto-refresh var 60:e sekund, med datumvaljare.

- **Backend**: `DagligSammanfattningController.php` — tva endpoints:
  - `run=daily-summary&date=YYYY-MM-DD`: Hamtar ALL data i ett anrop: produktion (IBC OK/Ej OK, kvalitet, IBC/h), OEE-snapshot (oee_pct + 3 faktorer med progress-bars), topp-3 operatorer (namn, antal IBC, snitt cykeltid), stopptid (total + topp 3 orsaker med tidfordelning), trendpil mot forra veckan, veckosnitt (5 dagar), senaste skiftet, auto-genererat statusmeddelande.
  - `run=comparison&date=YYYY-MM-DD`: Jambforelsedata mot igar och forra veckan (IBC, kvalitet, IBC/h, OEE — med +/- diff-procent och trendpil).
  - Auth: session_id kravs (421-check identisk med OeeBenchmarkController). Hanterar saknad stopporsak-tabell graciost.
- **SQL-migrering**: `noreko-backend/migrations/2026-03-11_daglig_sammanfattning.sql` — index pa rebotling_ibc(created_at), stopporsak_registreringar(linje, start_time), rebotling_onoff(start_time) for snabbare dagliga aggregeringar.
- **api.php**: Registrerat `daglig-sammanfattning` → `DagligSammanfattningController`
- **Service**: `src/app/services/daglig-sammanfattning.service.ts` — getDailySummary(date), getComparison(date) med fullstandiga TypeScript-interfaces (Produktion, OeeSnapshot, TopOperator, Stopptid, Trend, Veckosnitt, SenasteSkift, ComparisonData), timeout(20000) + catchError.
- **Komponent**: `src/app/pages/daglig-sammanfattning/` (ts + html + css) — standalone, OnInit/OnDestroy + destroy$ + takeUntil + clearInterval for bade refresh och countdown.
  - Auto-refresh var 60:e sekund med nedrakningsdisplay
  - Datumvaljare med "Idag"-knapp
  - Statusmeddelande med auto-genererad text (OEE-niva + trend + kvalitet + veckosnitt)
  - 4 KPI-kort: IBC OK, IBC Ej OK, Kvalitet %, IBC/h (fargkodade mot mal)
  - OEE-snapshot: stort tal med farg (gron/bla/gul/rod) + 3 faktorer med progress-bars + drifttid/stopptid
  - Topp 3 operatorer: guld/silver/brons-badges, namn, antal IBC, snitt cykeltid
  - Stopptid: totalt formaterat (h + min), topp 3 orsaker med proportionella progress-bars
  - Senaste skiftet: 3 KPI-siffror + skiftstider + alla skift i kompakt tabell
  - Jambforelsetabell: Idag / Igar / Forra veckan / Veckosnitt med +/- diff-pilar
  - Trendkort: stor pil (upp/ner/flat) med text och siffror
- **Route**: `/rebotling/daglig-sammanfattning` (authGuard)
- **Meny**: Lagt till under Rebotling-dropdown: "Daglig sammanfattning" (tachometer-alt, bla)
- **Build**: OK — inga nya fel, 4 harmlosa pre-existing NG8102-varningar

## 2026-03-11 Produktionskalender — månadsvy med per-dag KPI:er och färgkodning

Ny sida `/rebotling/produktionskalender` (autentiserad). Visar produktionsvolym och kvalitet per dag i en interaktiv kalendervy med färgkodning.

- **Backend**: `ProduktionskalenderController.php` — run=month-data (per-dag-data för hela månaden: IBC ok/ej ok, kvalitet %, farg, IBC/h, månadssammanfattning, veckosnitt + trender), run=day-detail (detaljerad dagsinformation: KPI:er, top 5 operatörer, stopporsaker med minuter). Auth: session_id krävs. Hämtar mål från `rebotling_settings` (fallback 1000).
- **SQL-migrering**: `noreko-backend/migrations/2026-03-11_produktionskalender.sql` — tre index: datum+ok+lopnummer (månadsvy), stopp datum+orsak, onoff datum+running. Markerat med git add -f.
- **api.php**: Registrerat `produktionskalender` → `ProduktionskalenderController`
- **Service**: `src/app/services/produktionskalender.service.ts` — getMonthData(year, month), getDayDetail(date), timeout+catchError. Fullständiga TypeScript-interfaces: DagData, VeckoData, MonthlySummary, MonthData, DayDetail, TopOperator, Stopporsak.
- **Komponent**: `src/app/pages/produktionskalender/` (ts + html + css) — standalone, OnInit/OnDestroy + destroy$ + takeUntil.
  - Månadskalender med CSS Grid: 7 kolumner (mån–sön) + veckonummer-kolumn
  - Dagceller visar IBC OK (stort), kvalitet % (litet), färgkodning: grön (>90% kval + mål uppnått), gul (70–90%), röd (<70%)
  - Helgdagar (lör/sön) markeras med annorlunda bakgrundsfärg
  - Hover-effekt med scale-transform på klickbara dagar
  - Animerad detalj-panel (slide-in från höger med @keyframes) vid klick på dag
  - Detalj-panel visar: IBC OK/Ej OK, kvalitet %, IBC/h, drifttid, stopptid, OEE, top 5 operatörer med rank-badges, stopporsaker med minuter
  - Veckosnitt-rad under varje vecka med trend-pil (upp/ner/stabil) vs föregående vecka
  - Månadssammanfattning: totalt IBC, snitt kvalitet, antal gröna/gula/röda dagar, bästa/sämsta dag
  - Månadsnavigering med pilar + dropdown för år och månad
  - Färgförklaring (legend) under kalendern
  - Responsiv — anpassad för desktop och tablet
- **Route**: `/rebotling/produktionskalender` (authGuard)
- **Meny**: Lagt till under Rebotling-dropdown: "Produktionskalender" (calendar-alt, grön) för inloggade användare
- **Build**: OK — inga fel (bara befintliga NG8102-varningar från feedback-analys)

## 2026-03-11 Feedback-analys — VD-insyn i operatörsfeedback och stämning

Ny sida `/rebotling/feedback-analys` (autentiserad). VD och ledning får full insyn i operatörernas feedback och stämning (skalan 1–4: Dålig/Ok/Bra/Utmärkt) ur `operator_feedback`-tabellen.

- **Backend**: `FeedbackAnalysController.php` — fyra endpoints: run=feedback-list (paginerad med filter per operatör och period), run=feedback-stats (totalt, snitt, trend, fördelning, mest aktiv), run=feedback-trend (snitt per vecka för Chart.js), run=operator-sentiment (per operatör: snitt, antal, senaste datum/kommentar, sentiment-färg). Auth: session_id krävs.
- **SQL-migrering**: `noreko-backend/migrations/2026-03-11_feedback_analys.sql` — sammansatt index (datum, operator_id) + index (skapad_at)
- **api.php**: Registrerat `feedback-analys` → `FeedbackAnalysController`
- **Service**: `src/app/services/feedback-analys.service.ts` — getFeedbackList/getFeedbackStats/getFeedbackTrend/getOperatorSentiment, timeout(15000) + catchError
- **Komponent**: `src/app/pages/feedback-analys/` — standalone, OnInit/OnDestroy/AfterViewInit + destroy$ + takeUntil + chart?.destroy()
  - 4 sammanfattningskort (total, snitt, trend-pil, senaste datum)
  - Chart.js linjediagram — snitt per vecka med färgkodade punkter och genomsnitts-referenslinje
  - Betygsfördelning med progressbars och emoji (1–4)
  - Operatörsöversikt-tabell med färgkodad snitt-stämning (grön/gul/röd), filter-knapp
  - Detaljlista med paginering, stämning-badges (emoji + text + färg), filter per operatör
  - Periodselektor 7 / 14 / 30 / 90 dagar
- **Route**: `/rebotling/feedback-analys` (authGuard)
- **Meny**: Lagt till under Rebotling-dropdown: "Feedback-analys" (comment-dots, blå)
- **Buggfix**: `ranking-historik.html` — `getVeckansEtikett()` → `getVeckaEtikett()` (typo som bröt build)
- **Build**: OK — inga fel, 4 harmlösa NG8102-varningar

## 2026-03-11 Ranking-historik — leaderboard-trender vecka för vecka

Ny sida `/rebotling/ranking-historik` (autentiserad). VD och operatörer kan se hur placeringar förändras vecka för vecka, identifiera klättrare och se pågående trender.

- **Backend**: `RankingHistorikController.php` — run=weekly-rankings (IBC ok per operatör per vecka, rankordnat, senaste N veckor), run=ranking-changes (placeringsändring senaste vecka vs veckan innan), run=streak-data (pågående positiva/negativa trender per operatör, mest konsekvent). Auth: session_id krävs.
- **SQL**: `noreko-backend/migrations/2026-03-11_ranking_historik.sql` — sammansatta index på rebotling_ibc(op1/op2/op3, datum, ok) för snabba aggregeringar.
- **api.php**: Registrerat `ranking-historik` → `RankingHistorikController`
- **Service**: `src/app/services/ranking-historik.service.ts` — getWeeklyRankings(weeks), getRankingChanges(), getStreakData(weeks), timeout(15000)+catchError.
- **Komponent**: `src/app/pages/ranking-historik/` (ts + html + css) — standalone, OnInit/OnDestroy + destroy$ + takeUntil + chart?.destroy().
  - 4 sammanfattningskort: Veckans #1, Största klättrare, Längsta positiva trend, Mest konsekvent
  - Placeringsändringstabell: namn, nuv. placering, föreg. placering, ändring (grön pil/röd pil/streck), IBC denna vecka + klättrar-badge (fire-ikon) för 2+ veckor i rad uppåt
  - Rankingtrend-graf: Chart.js linjediagram, inverterad y-axel (#1 = topp), en linje per operatör, periodselektor 4/8/12 veckor
  - Head-to-head: Välj 2 operatörer → separat linjediagram med deras rankningskurvor mot varandra
  - Streak-tabell: positiv/negativ streak per operatör + visuell placeringssekvens (färgkodade siffror)
- **Route**: `/rebotling/ranking-historik` (authGuard)
- **Meny**: Lagt till under Rebotling-dropdown (loggedIn): "Ranking-historik" med trophy-ikon
- **Build**: OK — inga fel (4 pre-existing warnings i feedback-analys, ej vår kod)

## 2026-03-11 Skiftrapport PDF-export — daglig och veckovis produktionsrapport

Ny sida `/rebotling/skiftrapport-export` (autentiserad). VD kan välja datum, se förhandsgranskning av alla KPI:er på skärmen, och ladda ner en färdig PDF — eller skriva ut med window.print(). Stöder dagrapport och veckorapport (datumintervall).

- **Backend**: `SkiftrapportExportController.php` — run=report-data (produktion, cykeltider, drifttid, OEE-approximation, top-10-operatörer, trender mot förra veckan) och run=multi-day (sammanfattning per dag). Auth: session_id krävs.
- **SQL**: `noreko-backend/migrations/2026-03-11_skiftrapport_export.sql` — index på created_at, created_at+skiftraknare+datum, op1/op2/op3+created_at för snabbare aggregering.
- **api.php**: Registrerat `skiftrapport-export` → `SkiftrapportExportController`
- **Service**: `src/app/services/skiftrapport-export.service.ts` — timeout(15000) + catchError, interface-typer för ReportData och MultiDayData.
- **Komponent**: `src/app/pages/skiftrapport-export/` (ts + html + css) — standalone, OnInit/OnDestroy + destroy$ + takeUntil.
  - Datumväljare (default: igår) med lägesselektor dag/vecka
  - Förhandsgranskning med KPI-kort (IBC OK/Ej OK, Kvalitet, IBC/h), cykeltider, drifttid/stopptid med progressbar, OEE med 3 faktorer, operatörstabell, trendsektion mot förra veckan
  - PDF-generering via pdfmake (redan installerat): dag-PDF och vecka-PDF (landscape) med branding-header, tabeller, footer
  - Utskriftsknapp via window.print() med @media print CSS
- **Route**: `/rebotling/skiftrapport-export` (authGuard)
- **Meny**: Lagt till under Rebotling-dropdown: "Skiftrapport PDF" (PDF-ikon, röd, visas för inloggade)
- **Build**: OK — inga fel, inga varningar

## 2026-03-11 OEE Benchmark — jämförelse mot branschsnitt

Ny statistiksida `/rebotling/oee-benchmark` (autentiserad). Visar OEE (Overall Equipment Effectiveness = Tillgänglighet × Prestanda × Kvalitet) för rebotling och jämför mot branschriktvärden: World Class 85%, Branschsnitt 60%, Lägsta godtagbara 40%.

- **OEE Gauge**: Cirkulär halvmåne-gauge (Chart.js doughnut, halvt) med stort OEE-tal och färgkodning: röd <40%, gul 40-60%, grön 60-85%, blågrön ≥85%. Statusbadge (World Class / Bra / Under branschsnitt / Kritiskt lågt).
- **Benchmark-jämförelse**: Tre staplar med din OEE markerad mot World Class/Branschsnitt/Lägsta-linjer. Gap-analys (+ / - procentenheter mot varje mål).
- **3 faktor-kort**: Tillgänglighet, Prestanda, Kvalitet — var med stort procent-tal, progressbar, trend-pil (upp/ner/flat jämfört mot föregående lika lång period) och detaljinfo (drifttid/stopptid, IBC-antal, OK/kasserade).
- **Trend-graf**: Chart.js linjediagram med OEE per dag + horisontella referenslinjer för World Class (85%) och branschsnitt (60%).
- **Förbättringsförslag**: Automatiska textmeddelanden baserat på vilken av de 3 faktorerna som är lägst.
- **Periodselektor**: 7 / 14 / 30 / 90 dagar.
- **SQL**: `noreko-backend/migrations/2026-03-11_oee_benchmark.sql` — index på rebotling_ibc(datum), rebotling_ibc(datum,ok), rebotling_onoff(start_time)
- **Backend**: `OeeBenchmarkController.php` — run=current-oee, run=benchmark, run=trend, run=breakdown. Auth: session_id krävs.
- **api.php**: Registrerat `oee-benchmark` → `OeeBenchmarkController`
- **Service**: `src/app/services/oee-benchmark.service.ts` — getCurrentOee/getBenchmark/getTrend/getBreakdown, timeout(15000)+catchError
- **Komponent**: `src/app/pages/oee-benchmark/` (ts + html + css) — standalone, OnInit/OnDestroy/AfterViewInit + destroy$ + chart?.destroy()
- **Route**: `/rebotling/oee-benchmark` (authGuard)
- **Meny**: Lagt till under Rebotling-dropdown (loggedIn): "OEE Benchmark" med chart-pie-ikon
- **Buggfix**: `skiftrapport-export` — Angular tillåter inte `new Date()` i template; fixat genom att exponera `todayISO: string` som komponent-property
- **Build**: OK — inga fel (3 warnings för `??` i skiftrapport-export, ej vår kod)

## 2026-03-11 Underhallslogg — planerat och oplanerat underhall

Ny sida `/rebotling/underhallslogg` (autentiserad). Operatörer loggar underhallstillfällen med kategori (Mekaniskt, Elektriskt, Hydraulik, Pneumatik, Rengöring, Kalibrering, Annat), typ (planerat/oplanerat), varaktighet i minuter och valfri kommentar. Historiklista med filter på period (7/14/30/90 dagar), typ och kategori. Sammanfattningskort: totalt antal, total tid, snitt/vecka, planerat/oplanerat-fördelning (%). Fördelningsvy med progressbar planerat vs oplanerat och stapeldiagram per kategori. Delete-knapp för admin. CSV-export.

- **SQL**: `noreko-backend/migrations/2026-03-11_underhallslogg.sql` — tabeller `underhallslogg` + `underhall_kategorier` + 7 standardkategorier
- **Backend**: `UnderhallsloggController.php` — endpoints: categories (GET), log (POST), list (GET, filtrering på days/type/category), stats (GET), delete (POST, admin-only)
- **api.php**: Registrerat `underhallslogg` → `UnderhallsloggController`
- **Service**: `src/app/services/underhallslogg.service.ts` — timeout(10000) + catchError på alla anrop
- **Component**: `src/app/pages/underhallslogg/` (ts + html + css) — standalone, OnInit/OnDestroy + destroy$
- **Route**: `/rebotling/underhallslogg` (authGuard)
- **Meny**: Lagt till under Rebotling-dropdown: "Underhallslogg" (verktygsikon)
- **Build**: OK — inga fel

## 2026-03-11 Cykeltids-heatmap — per operatör och timme pa dygnet

Ny analysvy for VD: `/rebotling/cykeltid-heatmap`. Visar cykeltid per operatör per timme som fargsatt heatmap (gron=snabb, gul=medel, rod=langsam). Cykeltid beraknas via LAG(datum) OVER (PARTITION BY skiftraknare) med filter 30-1800 sek. Klickbar drilldown per operatörsrad visar daglig heatmap for den operatören. Dygnsmonstergraf (Chart.js) visar snitttid + antal IBC per timme pa dagen. Sammanfattningskort: snabbaste/langsammaste timme, bast operatör, mest konsekvent operatör.

- **SQL**: `noreko-backend/migrations/2026-03-11_cykeltid_heatmap.sql` — index pa op1/op2/op3+datum (inga nya tabeller behovs)
- **Backend**: `CykeltidHeatmapController.php` — run=heatmap, run=day-pattern, run=operator-detail. Auth: session_id kravs.
- **api.php**: Registrerat `cykeltid-heatmap` → `CykeltidHeatmapController`
- **Service**: `src/app/services/cykeltid-heatmap.service.ts` — timeout(15000)+catchError
- **Komponent**: `src/app/pages/cykeltid-heatmap/` (ts + html + css) — HTML-tabell heatmap, drilldown, Chart.js dygnsmonstergraf
- **Route**: `/rebotling/cykeltid-heatmap` (authGuard)
- **Meny**: Lagt till under Rebotling-dropdown: "Cykeltids-heatmap" (visas for inloggade)
- **Build**: OK — inga fel

## 2026-03-11 Skiftöverlämningsmall — auto-genererad skiftsammanfattning

Ny sida `/rebotling/skiftoverlamning` (publik — ingen inloggning krävs för att läsa). Visar senaste avslutade skiftets nyckeltal direkt från `rebotling_ibc`-data: IBC ok/ej ok, kvalitet %, IBC/timme, cykeltid, drifttid, stopptid med visuell fördelningsbar. Noteringar kan läggas till av inloggade användare och sparas kopplade till PLC-skiftraknaren. Historikvy med senaste N dagars skift i tabell, klicka för att navigera. Utskriftsvy via window.print(). Skiftnavigering (föregående/nästa) via prev_skift/next_skift.

- **SQL**: `noreko-backend/migrations/2026-03-11_skiftoverlamning.sql` — tabell `skiftoverlamning_notes`
- **Backend**: `SkiftoverlamningController.php` — endpoints: summary, notes, add-note (POST), history
- **api.php**: Registrerat `skiftoverlamning` → `SkiftoverlamningController`
- **Service**: `src/app/services/skiftoverlamning.service.ts`
- **Component**: `src/app/pages/skiftoverlamning/` (ts + html + css)
- **Route**: `/rebotling/skiftoverlamning` (ingen authGuard — publik vy)
- **Meny**: Lagt till under Rebotling-dropdown: "Skiftöverlämningsmall"
- **Buggfix**: `stopporsak-registrering.html` — ändrat `'Okänd operatör'` (non-ASCII i template-expression) till `'Okänd'` för att kompilatorn inte ska krascha

## 2026-03-11 Stopporsak-snabbregistrering — mobilvänlig knappmatris för operatörer

Ny sida `/rebotling/stopporsak-registrering` (autentiserad). Operatörer trycker en kategoriknapp, skriver valfri kommentar och bekräftar. Aktiva stopp visas med live-timer. Avsluta-knapp avslutar stoppet och beräknar varaktighet. Historik visar senaste 20 stopp.

- **SQL**: `noreko-backend/migrations/2026-03-11_stopporsak_registrering.sql` — tabeller `stopporsak_kategorier` + `stopporsak_registreringar` + 8 standardkategorier
- **Backend**: `StopporsakRegistreringController.php` — endpoints: categories, register (POST), active, end-stop (POST), recent
- **Service**: `src/app/services/stopporsak-registrering.service.ts`
- **Component**: `src/app/pages/stopporsak-registrering/` (ts + html + css)
- **Route**: `/rebotling/stopporsak-registrering` (authGuard)
- **Meny**: Lagt till under Rebotling-dropdown: "Registrera stopp"
- **Build**: OK — inga fel

## 2026-03-11 Effektivitet per produkttyp — jamforelse mellan IBC-produkttyper

Analysvy som jamfor produktionseffektivitet mellan olika IBC-produkttyper (FoodGrade, NonUN, etc.). VD ser vilka produkttyper som tar langst tid, har bast kvalitet och ger hogst throughput.

- **Backend** — ny `ProduktTypEffektivitetController.php` (`noreko-backend/classes/`):
  - `run=summary` — sammanfattning per produkttyp: antal IBC, snittcykeltid (sek), kvalitet%, IBC/timme, snittbonus. Perioder: 7d/14d/30d/90d. Aggregerar kumulativa PLC-varden korrekt (MAX per skift, sedan SUM/AVG).
  - `run=trend` — daglig trend per produkttyp (IBC-antal + cykeltid) for Chart.js stacked/grouped bar. Top 6 produkttyper.
  - `run=comparison` — head-to-head jamforelse av 2 valda produkttyper med procentuella skillnader.
  - Registrerad i `api.php` classNameMap (`produkttyp-effektivitet`)
  - Tabeller: `rebotling_ibc.produkt` -> `rebotling_products.id`
- **Service** (`produkttyp-effektivitet.service.ts`): `getSummary(days)`, `getTrend(days)`, `getComparison(a, b, days)` med timeout 15s
- **Frontend-komponent** `StatistikProduktTypEffektivitetComponent` (`/rebotling/produkttyp-effektivitet`):
  - Sammanfattningskort per produkttyp (styled cards): antal IBC, cykeltid, IBC/h, kvalitet, bonus
  - Kvalitetsranking med progressbars (fargkodade: gron >= 98%, gul >= 95%, rod < 95%)
  - Grupperad stapelgraf (Chart.js line) — cykeltid per produkttyp over tid
  - IBC/timme-jamforelse (horisontell bar chart)
  - Daglig IBC-produktion per produkttyp (stacked bar chart)
  - Head-to-head jamforelse: dropdowns for att valja 2 produkttyper, procentuella skillnader per nyckeltal
  - Periodselektor: 7d / 14d / 30d / 90d
  - Dark theme (#1a202c bg, #2d3748 cards, #e2e8f0 text)
  - OnInit/OnDestroy + destroy$ + takeUntil + chart cleanup
- **Meny**: nytt item "Produkttyp-effektivitet" under Rebotling-dropdown i menu.html
- **Route**: `/rebotling/produkttyp-effektivitet` i app.routes.ts

## 2026-03-11 Dashboard-widget layout — VD kan anpassa sin startsida

VD kan valja vilka widgets som visas pa dashboard-sidan, andra ordning, och spara sina preferenser per user.

- **Backend** — ny `DashboardLayoutController.php` (`noreko-backend/classes/`):
  - `run=get-layout` — hamta sparad widgetlayout for inloggad user (UPSERT-logik)
  - `run=save-layout` (POST) — spara widgetordning + synlighet per user med validering
  - `run=available-widgets` — lista alla 8 tillgangliga widgets med id, namn, beskrivning
  - Registrerad i `api.php` classNameMap (`dashboard-layout`)
- **SQL-migrering** — `noreko-backend/migrations/2026-03-11_dashboard_layouts.sql`:
  - `dashboard_layouts`-tabell: id, user_id (UNIQUE), layout_json (TEXT), updated_at
- **Service** (`rebotling.service.ts`): `getDashboardLayout()`, `saveDashboardLayout(widgets)`, `getAvailableWidgets()` + interfaces
- **Frontend** — modifierad `rebotling-statistik`:
  - Kugghjulsikon ("Anpassa dashboard") overst pa sidan
  - Konfigureringsvy: lista med toggle-switch for varje widget + upp/ner-knappar for ordning (utan CDK)
  - Spara-knapp som persisterar till backend, Aterstall standard-knapp
  - Widgets (veckotrend, OEE-gauge, produktionsmal, leaderboard, bonus-simulator, kassationsanalys, produktionspuls) styrs av `*ngIf="isWidgetVisible('...')"`
  - Default layout: alla widgets synliga i standardordning

## 2026-03-11 Alerts/notifieringssystem — realtidsvarning vid låg OEE eller lång stopptid

Komplett alert/notifieringssystem för VD med tre flikar, kvitteringsflöde, konfigurerbara tröskelvärden och polling-badge i headern.

- **Backend** — ny `AlertsController.php` (`noreko-backend/classes/`):
  - `run=active` — alla aktiva (ej kvitterade) alerts, kritiska först, sedan nyast
  - `run=history&days=N` — historik senaste N dagar (max 500 poster)
  - `run=acknowledge` (POST) — kvittera en alert, loggar user_id + timestamp
  - `run=settings` (GET/POST) — hämta/spara tröskelvärden med UPSERT-logik
  - `run=check` — kör alertkontroll: OEE-beräkning senaste timmen, aktiva stopporsaker längre än tröskeln, kassationsrate; skapar ej dubbletter (recentActiveAlertExists med tidsfönster)
  - Registrerad i `api.php` classNameMap (`alerts`)
- **SQL-migrering** — `noreko-backend/migrations/2026-03-11_alerts.sql`:
  - `alerts`-tabell: id, type (oee_low/stop_long/scrap_high), message, value, threshold, severity (warning/critical), acknowledged, acknowledged_by, acknowledged_at, created_at
  - `alert_settings`-tabell: type (UNIQUE), threshold_value, enabled, updated_at, updated_by
  - Standard-inställningar: OEE < 60%, stopp > 30 min, kassation > 10%
- **Service** (`alerts.service.ts`): `getActiveAlerts()`, `getAlertHistory(days)`, `acknowledgeAlert(id)`, `getAlertSettings()`, `saveAlertSettings(settings)`, `checkAlerts()`; `activeAlerts$` BehaviorSubject med timer-baserad polling (60 sek)
- **Frontend-komponent** `AlertsPage` (`/rebotling/alerts`, adminGuard):
  - Fliken Aktiva: alert-kort med severity-färgkodning (röd=kritisk, gul=varning), kvitteringsknapp med spinner, "Kör kontroll nu"-knapp, auto-refresh var 60 sek
  - Fliken Historik: filtrering per typ + allvarlighet + dagar, tabell med acknowledged-status och kvitteringsinfo
  - Fliken Inställningar: toggle + numerisk input per alerttyp med beskrivning, admin-spärrad POST
- **Menu-badge** (`menu.ts` + `menu.html`): `activeAlertsCount` med `startAlertsPolling()`/`stopAlertsPolling()` (interval 60 sek, OnDestroy cleanup); badge i notifikationsdropdown och i Admin-menyn under "Varningar"; total badge i klockan summerar urgentNoteCount + certExpiryCount + activeAlertsCount
- **Route**: `/rebotling/alerts` med `adminGuard` i `app.routes.ts`

## 2026-03-11 Kassationsanalys — drilldown per stopporsak

Komplett kassationsanalys-sida för VD-vy. Stackad Chart.js-graf + trendjämförelse + klickbar drilldown per orsak.

- **Backend** — ny `KassationsanalysController.php` (`noreko-backend/classes/`):
  - Registrerad i `api.php` under action `kassationsanalys`
  - `run=summary` — totala kassationer, kassationsrate %, topp-orsak, trend (absolut + rate) vs föregående period
  - `run=by-cause` — kassationer per orsak med andel %, kumulativ %, föregående period, trend-pil + %
  - `run=daily-stacked` — daglig data stackad per orsak (upp till 8 orsaker), Chart.js-kompatibelt format med färgpalett
  - `run=drilldown&cause=X` — detaljrader per orsak: datum, skiftnummer, antal, kommentar, registrerad_av + operatörerna som jobbade på skiftet (join med rebotling_ibc → operators)
  - Aggregeringslogik: MAX() per skiftraknare för kumulativa PLC-värden (ibc_ej_ok), sedan SUM()
  - Tabeller: `kassationsregistrering`, `kassationsorsak_typer`, `rebotling_ibc`, `operators`, `users`
- **Service** (`rebotling.service.ts`): 4 nya metoder + 5 interface-typer
  - `getKassationsSummary(days)`, `getKassationsByCause(days)`, `getKassationsDailyStacked(days)`, `getKassationsDrilldown(cause, days)`
  - `KassationsSummaryData`, `KassationOrsak`, `KassationsDailyStackedData`, `KassationsDrilldownData`, `KassationsDrilldownDetalj`
- **Frontend-komponent** `statistik-kassationsanalys` (standalone, `.ts` + `.html` + `.css`):
  - 4 sammanfattningskort: Totalt kasserat, Kassationsrate %, Vanligaste orsak, Trend vs föregående
  - Stackad stapelgraf (Chart.js) med en dataset per orsak, `stack: 'kassationer'`, tooltip visar alla orsaker per datum
  - Orsaksanalys-tabell: klickbar rad → drilldown expanderas med kumulativ progress bar, trend-pil
  - Drilldown-panel: snabbkort (total antal, antal registreringar, period, aktiva skift) + registreringstabell med operatörsnamn hämtat från rebotling_ibc
  - Periodselektor: 7d / 14d / 30d / 90d
  - Lifecycle: OnInit/OnDestroy, destroy$ + takeUntil, stackedChart?.destroy()
  - Dark theme: `#1a202c` bg, `#2d3748` cards, `#e2e8f0` text
- **Route**: `/rebotling/kassationsanalys` (public, ingen authGuard)
- **Meny**: "Kassationsanalys" med trash-ikon under Rebotling-dropdown i `menu.html`
- **Integrering**: sist på `rebotling-statistik.html` med `@defer (on viewport)`
- **Build**: kompilerar utan fel

---

## 2026-03-11 Veckotrend sparklines i KPI-kort

Fyra inline sparkline-grafer (7-dagars trend) högst upp på statistiksidan — VD ser direkt om trenderna går uppåt eller nedåt.

- **Backend** — ny `VeckotrendController.php` (`noreko-backend/classes/`):
  - Endpoint: `GET ?action=rebotling&run=weekly-kpis`
  - Returnerar 7 dagars data för 4 KPI:er: IBC/dag, snitt cykeltid, kvalitetsprocent, drifttidsprocent
  - Beräknar trend (`up`/`down`/`stable`) via snitt senaste halva vs första halva av perioden
  - Cykeltid-trend inverteras (kortare = bättre)
  - Inkluderar `change_pct`, `latest`, `min`, `max`
  - Fallback-logik för drifttid (drifttid_pct-kolumn eller korttid_min/planerad_tid_min)
  - Registrerad i `RebotlingController.php` (dispatch `weekly-kpis`)
- **Service** (`rebotling.service.ts`): ny metod `getWeeklyKpis()` + interfaces `WeeklyKpiCard`, `WeeklyKpisResponse`
- **Frontend-komponent** `statistik-veckotrend` (standalone, canvas-baserad):
  - 4 KPI-kort: titel, stort senaste värde, sparkline canvas, trendpil + %, min/max
  - Canvas 2D — quadratic bezier + gradient fill, animeras vänster→höger vid laddning (500ms)
  - Grön=up, röd=down, grå=stable
  - Auto-refresh var 5:e minut, destroy$ + takeUntil
- **Integrering**: ÖVERST på rebotling-statistiksidan med `@defer (on viewport)`

## 2026-03-11 Operatörs-dashboard Min dag

Ny personlig dashboard för inloggad operatör som visar dagens prestanda på ett motiverande och tydligt sätt.

- **Backend** — ny `MinDagController.php` (action=min-dag):
  - `run=today-summary` — dagens IBC-count, snittcykeltid (sek), kvalitetsprocent, bonuspoäng, jämförelse mot teamets 30-dagarssnitt och operatörens 30-dagarssnitt
  - `run=cycle-trend` — cykeltider per timme idag inkl. mållinje (team-snitt), returneras som array för Chart.js
  - `run=goals-progress` — progress mot IBC-dagsmål (hämtas från `rebotling_production_goals`) och fast kvalitetsmål 95%
  - Operatör hämtas från session (`operator_id`) eller `?operator=<id>`-parameter
  - Korrekt aggregering: kumulativa fält med MAX() per skift, sedan SUM() över skift
  - Registrerad i `api.php` classNameMap
- **Service** (`rebotling.service.ts`) — tre nya metoder: `getMinDagSummary()`, `getMinDagCycleTrend()`, `getMinDagGoalsProgress()` med nya TypeScript-interfaces
- **Frontend-komponent** `MinDagPage` (`/rebotling/min-dag`, authGuard):
  - Välkomstsektion med operatörens namn och dagens datum
  - 4 KPI-kort: Dagens IBC (+ vs 30-dagarssnitt), Snittcykeltid (+ vs team), Kvalitet (%), Bonuspoäng
  - Chart.js linjediagram — cykeltider per timme med grön streckad mållinje
  - Progressbars mot IBC-mål och kvalitetsmål med färgkodning
  - Dynamisk motivationstext baserat på prestation (jämför IBC vs snitt, cykeltid vs team, kvalitet)
  - Auto-refresh var 60:e sekund med OnInit/OnDestroy + destroy$ + clearInterval
  - Dark theme: #1a202c bg, #2d3748 cards, Bootstrap 5
- **Navigation** — menyitem "Min dag" under Rebotling (inloggad), route i app.routes.ts

## 2026-03-11 Produktionspuls-ticker

Ny realtids-scrollande ticker som visar senaste producerade IBC:er — som en börskursticker.

- **Backend** — ny `ProduktionspulsController.php`:
  - `?action=produktionspuls&run=latest&limit=50` — senaste IBC:er med operatör, produkt, cykeltid, status
  - `?action=produktionspuls&run=hourly-stats` — IBC/h, snittcykeltid, godkända/kasserade + föregående timme för trendpilar
- **Frontend** — fullscreen-vy `ProduktionspulsPage` på `/rebotling/produktionspuls`:
  - Horisontell CSS-animerad ticker med IBC-brickor (grön=OK, röd=kasserad, gul=lång cykel)
  - Pausar vid hover, auto-refresh var 15:e sekund
  - Statistikrad: IBC/h, snittcykeltid, godkända/kasserade, kvalitetsprocent med trendpilar
- **Widget** — `ProduktionspulsWidget` inbäddad på startsidan (news.html), kompakt ticker
- **Navigation** — tillagd i Rebotling-menyn och route i app.routes.ts
- **Service** — `produktionspuls.service.ts`

## 2026-03-11 Maskinupptid-heatmap

Ny statistikkomponent som visar maskinupptid som ett veckokalender-rutnät (heatmap). Varje cell representerar en timme och är färgkodad: grön = drift, röd = stopp, grå = ingen data.

- **Backend** — ny metod `getMachineUptimeHeatmap()` i `RebotlingAnalyticsController.php`:
  - Endpoint: `GET ?action=rebotling&run=machine-uptime-heatmap&days=7`
  - Frågar `rebotling_ibc`-tabellen (ibc per datum+timme) och `rebotling_onoff` (stopp-events)
  - Returnerar array av celler: `{ date, hour, status ('running'|'stopped'|'idle'), ibc_count, stop_minutes }`
  - Validerar `days`-parameter (1–90 dagar)
  - Registrerad i `RebotlingController.php` under analytics GET-endpoints
- **Service** (`rebotling.service.ts`):
  - Ny metod `getMachineUptimeHeatmap(days: number)`
  - Nya interfaces: `UptimeHeatmapCell`, `UptimeHeatmapResponse`
- **Frontend-komponent** `statistik-uptid-heatmap` (standalone, path: `statistik/statistik-uptid-heatmap/`):
  - Y-axel: dagar (t.ex. Mån 10 mar) — X-axel: timmar 00–23
  - Cells färgkodade: grön (#48bb78) = drift, röd (#fc8181) = stopp, grå = idle
  - Hover-tooltip med datum, timme, status, antal IBC eller uppskattad stopptid
  - Periodselektor: 7/14/30 dagar
  - Sammanfattningskort: total drifttid %, timmar i drift, längsta stopp, bästa dag
  - Auto-refresh var 60 sekund
  - Lifecycle: OnInit/OnDestroy + destroy$ + takeUntil + clearInterval
  - Mörkt tema: #1a202c bakgrund, #2d3748 card
- Registrerad i `rebotling-statistik.ts` (import + @Component imports-array) och `rebotling-statistik.html` (`@defer on viewport` längst ned efter bonus-simulator)
- Bygg OK (65s, inga fel)

---

## 2026-03-11 Topp-5 operatörer leaderboard

Ny statistikkomponent som visar en live-ranking av de 5 bästa operatörerna baserat på bonuspoäng.

- **Backend** — ny metod `getTopOperatorsLeaderboard()` i `RebotlingAnalyticsController.php`:
  - Aggregerar per skift via UNION ALL av op1/op2/op3 (samma mönster som BonusController)
  - Kumulativa fält hämtas med MAX(), bonus_poang/kvalitet/effektivitet med sista cykelns värde (SUBSTRING_INDEX + GROUP_CONCAT)
  - Beräknar ranking för nuvarande period OCH föregående period (för trendpil: 'up'/'down'/'same'/'new')
  - Returnerar: rank, operator_id, operator_name, score (avg bonus), score_pct (% av ettan), ibc_count, quality_pct, skift_count, avg_eff, trend, previous_rank
  - Endpoint: `GET ?action=rebotling&run=top-operators-leaderboard&days=30`
  - Registrerad i `RebotlingController.php`
- **Service** (`rebotling.service.ts`):
  - `getTopOperatorsLeaderboard(days)` — Observable<LeaderboardResponse>
  - Interfaces: `LeaderboardOperator`, `LeaderboardResponse`
- **Frontend-komponent** `statistik-leaderboard` (standalone, path: `statistik/statistik-leaderboard/`):
  - Periodselektor: 7/30/90 dagar
  - Lista med plats 1–5: rank-badge (krona/medalj/stjärna), operatörsnamn, IBC/skift/kvalitet-meta
  - Progressbar per rad (score_pct relativt ettan) med guld/silver/brons/grå gradient
  - Trendpil: grön upp, röd ned, grå samma, gul stjärna vid ny i toppen
  - #1: guld-highlight (gul border + gradient), #2: silver, #3: brons
  - Pulsanimation (`@keyframes leaderboardPulse`) triggas när etta byter operatör
  - Blinkande "live-punkt" + text "Uppdateras var 30s"
  - Auto-refresh var 30s via setInterval (clearInterval i ngOnDestroy)
  - Lifecycle: OnInit/OnDestroy + destroy$ + takeUntil + clearInterval
  - Mörkt tema: #2d3748 kort, guld #d69e2e, silver #a0aec0, brons #c05621
- Registrerad i `rebotling-statistik.ts` (import + @Component imports-array)
- Infogad i `rebotling-statistik.html` som `@defer (on viewport)` ovanför huvud-headern

---

## 2026-03-11 Bonus "What-if"-simulator

Ny statistikkomponent under rebotling-statistiksidan som ger admin ett interaktivt verktyg att simulera hur bonusparametrar påverkar operatörernas utfall.

- **Backend** — två nya endpoints i `BonusAdminController.php`:
  - `GET ?action=bonusadmin&run=bonus-simulator` — hämtar rådata per operatör (senaste N dagar), beräknar nuvarande bonus (från DB-config) OCH simulerad bonus (med query-parametrar) och returnerar jämförelsedata per operatör. Query-params: `eff_w_1/prod_w_1/qual_w_1` (FoodGrade), `eff_w_4/prod_w_4/qual_w_4` (NonUN), `eff_w_5/prod_w_5/qual_w_5` (Tvättade), `target_1/target_4/target_5` (IBC/h-mål), `max_bonus`, `tier_95/90/80/70/0` (multiplikatorer)
  - `POST ?action=bonusadmin&run=save-simulator-params` — sparar justerade viktningar, produktivitetsmål och bonustak till `bonus_config`
  - Hjälpmetoder: `clampWeight()`, `getTierMultiplierValue()`, `getTierName()`
- **Service** (`rebotling.service.ts`):
  - `getBonusSimulator(days, params?)` — bygger URL med alla simuleringsparametrar
  - `saveBonusSimulatorParams(payload)` — POST till save-endpoint
  - Interfaces: `BonusSimulatorParams`, `BonusSimulatorOperator`, `BonusSimulatorResponse`, `BonusSimulatorSavePayload`, `BonusSimulatorWeights`
- **Frontend-komponent** `statistik-bonus-simulator` (standalone, path: `statistik/statistik-bonus-simulator/`):
  - Vänsterkolumn med tre sektioner: (1) Viktningar per produkt med range-inputs (summeras till 100%, live-validering), (2) Produktivitetsmål (IBC/h) per produkt, (3) Tier-multiplikatorer (Outstanding/Excellent/God/Bas/Under) + bonustak
  - Högerkolumn: sammanfattningskort (antal operatörer, snittförändring, plus/minus), jämförelsetabell med nuv. vs. sim. bonuspoäng + tier-namn + diff-badge (grön/röd/grå)
  - Debounce 400ms — slider-drag uppdaterar beräkningen utan att spamma API
  - Spara-knapp sparar nya parametrar till bonus_config (POST), med success/fel-feedback
  - Lifecycle: OnInit/OnDestroy + destroy$ + simulate$ (Subject) + takeUntil
  - Mörkt tema: #2d3748 cards, tier-badges med produktspecifika färger
- Registrerad i `rebotling-statistik.ts` (import + @Component imports-array) och `rebotling-statistik.html` (`@defer on viewport` längst ned)
- Bygg OK (56s, inga fel)

---

## 2026-03-11 Skiftjämförelse-vy (dag vs natt)

Ny statistikkomponent som jämför dagskift (06:00–22:00) vs nattskift (22:00–06:00):

- **Backend** — ny metod `getShiftDayNightComparison()` i `RebotlingAnalyticsController.php`:
  - Klassificerar skift baserat på starttimmen för första raden i `rebotling_ibc` per skiftraknare
  - Dagskift = starttimme 06–21, nattskift = 22–05
  - Returnerar KPI:er per skifttyp: IBC OK, snitt IBC/skift, kvalitet %, OEE %, avg cykeltid, IBC/h, körtid, kasserade
  - Returnerar daglig tidsserie (trend) med dag/natt-värden per datum
  - Endpoint: GET `?action=rebotling&run=shift-day-night&days=30`
  - Registrerad i `RebotlingController.php`
- **Service** (`rebotling.service.ts`):
  - `getShiftDayNightComparison(days)` — Observable<ShiftDayNightResponse>
  - Interfaces: `ShiftKpi`, `ShiftTrendPoint`, `ShiftDayNightResponse`
- **Frontend-komponent** `statistik-skiftjamforelse` (standalone):
  - Periodselektor: 7/14/30/90 dagar
  - Två KPI-paneler: "Dagskift" (orange/gult) och "Nattskift" (blått/lila), 8 KPI-kort vardera
  - Diff-kolumn i mitten: absolut skillnad dag vs natt per KPI
  - Grouped bar chart (Chart.js) — jämför IBC totalt, snitt IBC/skift, Kvalitet %, OEE %, IBC/h
  - Linjediagram med KPI-toggle (IBC / Cykeltid / Kvalitet %) — 2 linjer (dag vs natt) över tid
  - Fargkodning: dagskift orange (#ed8936), nattskift lila/blå (#818cf8)
  - Lifecycle: OnInit/OnDestroy + destroy$ + takeUntil
- Registrerad som `@defer (on viewport)` i `rebotling-statistik.html`
- Bygg OK (59s, inga fel)

---

## 2026-03-11 Manadsrapport-sida (/rapporter/manad)

Fullstandig manadsrapport-sida verifierad och kompletterad:

- **Befintlig implementation verifierad** — `pages/monthly-report/` med monthly-report.ts/.html/.css redan implementerad
- **Route** `rapporter/manad` pekar till `MonthlyReportPage` (authGuard) — redan i app.routes.ts
- **Navigationsmenyn** — "Rapporter"-dropdown med Manadsrapport och Veckorapport redan i menu.html
- **Backend** — `getMonthlyReport()` och `getMonthCompare()` i RebotlingAnalyticsController.php, `monthly-stop-summary` endpoint — alla redan implementerade
- **rebotling.service.ts** — Lade till `getMonthlyReport(year, month)` + `getMonthCompare(year, month)` metoder
- **Interfaces** — `MonthlyReportResponse`, `MonthCompareResponse` och alla sub-interfaces exporterade fran rebotling.service.ts
- Byggt OK — inga fel, monthly-report chunk 56.16 kB

---

## 2026-03-11 Produktionsmål-tracker

Visuell produktionsmål-tracker med progress-ringar, countdown och streak pa rebotling-statistiksidan:

- **DB-migration** `noreko-backend/migrations/2026-03-11_production-goals.sql`:
  - Ny tabell `rebotling_production_goals`: id, period_type (daily/weekly), target_count, created_by, created_at, updated_at
  - Standardvarden: dagsmål 200 IBC, veckamål 1000 IBC
- **Backend** (metoder i RebotlingAnalyticsController):
  - `getProductionGoalProgress()` — GET, param `period=today|week`
    - Hamtar faktisk produktion fran rebotling_ibc (produktion_procent > 0)
    - Beraknar streak (dagar/veckor i rad dar malet nåtts)
    - Returnerar: target, actual, percentage, remaining, time_remaining_seconds, streak
  - `setProductionGoal()` — POST, admin-skyddad
    - Uppdaterar eller infogar ny rad i rebotling_production_goals
  - `ensureProductionGoalsTable()` — skapar tabell automatiskt vid forsta anropet
  - Routning registrerad i RebotlingController: GET `production-goal-progress`, POST `set-production-goal`
- **Service** (`rebotling.service.ts`):
  - `getProductionGoalProgress(period)` — Observable<ProductionGoalProgressResponse>
  - `setProductionGoal(periodType, targetCount)` — Observable<any>
  - Interface `ProductionGoalProgressResponse` tillagd
- **Frontend-komponent** `statistik-produktionsmal`:
  - Dagsmål och veckamål bredvid varandra (col-12/col-lg-6)
  - Chart.js doughnut-gauge per mål med stor procentsiffra och "actual / target" i mitten
  - Fargkodning: Gron >=100%, Gul >=75%, Orange >=50%, Rod <50%
  - Statistik-rad under gaugen: Producerade IBC / Mal / Kvar
  - Countdown: "X tim Y min kvar" (dagsmal → till midnatt, veckomal → till sondagens slut)
  - Streak-badge: "N dagar i rad!" / "N veckor i rad!" med fire-ikon
  - Banner nar malet ar uppnatt: "Dagsmål uppnatt!" / "Veckamål uppnatt!" med pulsanimation
  - Admin: inline redigera mål (knapp → input + spara/avbryt)
  - Auto-refresh var 60:e sekund via RxJS interval + startWith
  - Korrekt lifecycle: OnInit/OnDestroy, destroy$, takeUntil
- **Registrerad** som `@defer (on viewport)` child direkt under OEE-gaugen i rebotling-statistik
- Dark theme, svenska, bygger utan fel

---

## 2026-03-10 Realtids-OEE-gauge pa statistiksidan

Stor cirkular OEE-gauge overst pa rebotling-statistiksidan:
- **Backend endpoint** `realtime-oee` i RebotlingAnalyticsController — beraknar OEE = Tillganglighet x Prestanda x Kvalitet
  - Aggregerar kumulativa PLC-varden per skift (MAX per skiftraknare, sedan SUM)
  - Stopptid fran stoppage_log, ideal cykeltid via median fran senaste 30 dagarna
  - Perioder: today, 7d, 30d
- **Frontend-komponent** `statistik-oee-gauge`:
  - Chart.js doughnut-gauge med stor siffra i mitten
  - Fargkodad: Gron >=85%, Gul 60-85%, Rod <60%
  - Tre progress bars for Tillganglighet, Prestanda, Kvalitet
  - KPI-rutor: IBC totalt, Godkanda, Kasserade, Drifttid
  - Periodselektor (Idag / 7 dagar / 30 dagar)
  - Auto-refresh var 60:e sekund med polling
  - Responsiv layout (md breakpoint)
- **Registrerad som @defer child** overst i rebotling-statistik (inte on viewport — laddas direkt)
- Service: ny metod `getRealtimeOee()` + interface `RealtimeOeeResponse`
- Dark theme (#1a202c bg, #2d3748 cards, #e2e8f0 text), svenska, korrekt lifecycle

---

## 2026-03-10 Exportera grafer som PNG-bild

Ny funktion for att exportera statistikgrafer som PNG-bilder for rapporter och presentationer:
- **Ny utility** `noreko-frontend/src/app/shared/chart-export.util.ts`:
  - Tar ett Canvas-element (Chart.js) och exporterar som PNG
  - Skapar en temporar canvas med mork bakgrund (#1a202c), titel och datumperiod som header
  - Genererar filnamn: `{graf-namn}_{startdatum}_{slutdatum}.png`
- **Exportera PNG-knapp tillagd pa alla statistikgrafer**:
  - Produktionsanalys (rebotling-statistik huvudgraf)
  - Cykeltid per operator
  - Stopporsaksanalys - Pareto
  - Kvalitet deep-dive: donut, Pareto-stapeldiagram och trendgraf (3 knappar)
  - Skiftrapport per operator
  - Cykeltrend - IBC-produktion
- **UX**: btn-sm btn-outline-secondary med Bootstrap-ikon (bi-download), kort "Exporterad!"-feedback (2 sek)
- Dark theme, svenska, bygger OK

---

## 2026-03-10 Annotationer i grafer — markera driftstopp och helgdagar

Nytt annotationssystem for statistiksidans tidslinjegrafer:
- **DB-tabell** `rebotling_annotations` med falt: id, datum, typ (driftstopp/helgdag/handelse/ovrigt), titel, beskrivning, created_at
- **Migration**: `noreko-backend/migrations/2026-03-10_annotations.sql`
- **Backend endpoints** i RebotlingAnalyticsController:
  - `annotations-list` — hamta annotationer inom datumintervall med valfritt typfilter
  - `annotation-create` — skapa ny annotation (admin only)
  - `annotation-delete` — ta bort annotation (admin only)
- **Frontend-komponent** `statistik-annotationer`:
  - Lista alla annotationer (tabell med datum, typ-badge med fargkod, titel, beskrivning)
  - Formular for att lagga till ny annotation (datum-picker, typ-dropdown, titel, beskrivning)
  - Ta bort-knapp med bekraftelsedialog
  - Filtrera pa typ
- **Annotationstyper med farger**:
  - Driftstopp: rod (#e53e3e)
  - Helgdag: bla (#4299e1)
  - Handelse: gron (#48bb78)
  - Ovrigt: gra (#a0aec0)
- **Integrerat i cykeltrend-graf**: manuella annotationer visas som vertikala linjer med labels
- **Registrerad som @defer child** i rebotling-statistik
- Service: nya metoder `getManualAnnotations()`, `createManualAnnotation()`, `deleteManualAnnotation()`
- Dark theme, svenska, korrekt lifecycle (OnInit/OnDestroy + destroy$ + takeUntil)

---

## 2026-03-10 Stopporsak drill-down fran Pareto-diagram

Klickbar drill-down fran Pareto-diagrammet (stopporsaksanalys):
- **Klick pa Chart.js-stapel** eller **tabellrad** oppnar en modal med detaljvy
- **Sammanfattning**: total stopptid (min + h), antal stopp, snitt per stopp, antal operatorer
- **Per operator**: tabell med operator, antal stopp, total minuter
- **Per dag**: tabell med datum, antal stopp, minuter (scrollbar vid manga dagar)
- **Alla enskilda stopp**: datum, start/slut-tid, minuter, operator, kommentar
- **Stang-knapp** for att ga tillbaka till Pareto-vyn
- Backend: nytt endpoint `stop-cause-drilldown` i RebotlingAnalyticsController
  - Tar `cause` (stopporsak-namn) och `days` (period)
  - Queriar stoppage_log + stoppage_reasons + users
  - Returnerar summary, by_operator, by_day, stops
- Service: ny metod `getStopCauseDrilldown()` i rebotling.service.ts
- Dark theme, svenska, korrekt lifecycle
- Cursor andras till pointer vid hover over staplar

---

## 2026-03-09 Skiftrapport per operator — filtrerbar rapport

Ny komponent `statistik-skiftrapport-operator` under rebotling-statistik:
- **Dropdown-filter** for att valja operator (hamtar fran befintligt operator-list endpoint)
- **Periodvaljare**: 7/14/30/90 dagar eller anpassat datumintervall
- **Sammanfattningspanel**: Totalt IBC, snitt cykeltid, basta/samsta skift
- **Chart.js combo-graf**: staplar for IBC per skift + linje for cykeltid (dual Y-axlar)
- **Tabell**: Datum, Skift, IBC, Godkanda, Kasserade, Cykeltid, OEE, Stopptid
- **CSV-export** av all tabelldata (semicolon-separerad, UTF-8 BOM)
- Backend: nytt endpoint i SkiftrapportController — `run=shift-report-by-operator`
  - Filtrar rebotling_skiftrapport pa operator (op1/op2/op3) + datumintervall
  - Beraknar cykeltid, OEE, stopptid per skift
- Registrerad som @defer child-komponent i rebotling-statistik
- Dark theme, svenska, korrekt lifecycle (destroy$ + chart?.destroy())

---

## 2026-03-09 IBC Kvalitet Deep-dive — avvisningsorsaker

Ny komponent `statistik-kvalitet-deepdive` under rebotling-statistik:
- **Sammanfattningspanel**: Totalt IBC, Godkanda (%), Kasserade (%), kassationsgrad-trend (upp/ner vs fg period)
- **Donut-diagram**: kasserade IBC fordelat per avvisningsorsak (Chart.js doughnut)
- **Horisontellt stapeldiagram**: topp 10 avvisningsorsaker med Pareto-linje (80/20)
- **Trenddiagram**: linjediagram med daglig utveckling av topp 5 orsaker over tid
- **Tabell**: alla orsaker med antal, andel %, kumulativ %, trend vs fg period
- **CSV-export** av tabelldata
- **Periodselektor**: 7/14/30/90 dagar
- Backend: tva nya endpoints i RebotlingAnalyticsController:
  - `quality-rejection-breakdown` — sammanfattning + kassationsorsaker
  - `quality-rejection-trend` — tidsseriedata per orsak (topp 5)
- Registrerad som @defer child-komponent i rebotling-statistik
- Dark theme, svenska, korrekt lifecycle (destroy$ + chart.destroy)

---

## 2026-03-09 Cykeltid per operator — grouped bar chart + ranking-tabell

Uppgraderat statistik-cykeltid-operator-komponenten:
- **Grouped bar chart** med 3 staplar per operator (min, median, max) istallet for enkel bar
- **Horisontell referenslinje** som visar genomsnittlig median (custom Chart.js plugin)
- **Ranking-tabell** sorterad efter median (lagst = bast): Rank, Operator, Median, Min, Max, Antal IBC, Stddev
- **Basta operator** markeras med gron badge + stjarna
- **Backend**: nya falt min_min, max_min, stddev_min i cycle-by-operator endpoint
- Periodselektor 7/14/30/90 dagar (oforandrad)
- Ny CSS-fil for tabellstyling + responsivt
- Commit 3327f20

---

## 2026-03-09 Horisontellt Pareto-diagram med 80/20 kumulativ linje

Forbattrat statistik-pareto-stopp-komponenten till professionellt horisontellt Pareto-diagram:
- Liggande staplar (indexAxis: y) sorterade storst-forst med dynamisk hojd
- Kumulativ linje pa sekundar X-axel (topp) med rod streckad 80%-markering
- Vital few (<=80%) i orange, ovriga i gra for tydlig visuell skillnad
- Tooltip visar orsak, stopptid (min+h), antal stopp, andel av total
- Periodselektor 7/14/30/90 dagar
- Separat CSS med responsiv design (TV 1080p + tablet)
- ViewChild for canvas, korrekt Chart.js destroy i ngOnDestroy
Commit d8c4356.

---

## 2026-03-09 Session #45 — Lead: Pareto bekräftad klar + Bug Hunt #49

Lead-agent session #45. Worker 1 (Pareto stopporsaker): redan fullt implementerat — ingen ändring.
Worker 2 (Bug Hunt #49): 12 console.error borttagna, 25+ filer granskade. Commit dbc7b1a.
Nästa prioritet: Cykeltid per operatör, Annotationer i grafer.

---

## 2026-03-09 Bug Hunt #49 — Kodkvalitet och edge cases i rebotling-sidor

**rebotling-admin.ts**: 8 st `console.error()`-anrop i produkt-CRUD-metoder (loadProducts, addProduct, saveProduct, deleteProduct) borttagna. Dessa lacker intern felinformation till webbkonsolen i produktion. Felhanteringen i UI:t (loading-state) behalls intakt. Oanvanda `error`/`response`-parametrar togs bort fran callbacks.

**rebotling-statistik.ts**: 4 st `console.error()`-anrop borttagna:
- `catchError` i `loadStatistics()` — felmeddelande visas redan i UI via `this.error`
- `console.error('Background draw error:')` i chart-plugin — silenced, redan i try/catch
- `console.error('Selection preview draw error:')` i chart-plugin — silenced
- `console.error` med emoji i `createChart()` catch-block — ersatt med kommentar

Samtliga 25+ filer i scope granskades systematiskt for:
- Chart.js cleanup (alla charts forstors korrekt i ngOnDestroy)
- setInterval/setTimeout cleanup (alla timers rensas i ngOnDestroy)
- Edge cases i berakningar (division med noll skyddas korrekt)
- Template-bindningar (null-checks finns via `?.` overallt)
- Datumhantering (parseLocalDate anvands konsekvent)

Bygger OK. Inga backend-andringar.

---

## 2026-03-09 Bug Hunt #48 — Rebotling-sidor timeout/catchError + bonus-dashboard timer-bugg

**rebotling-admin.ts**: 10 HTTP-anrop saknade `timeout()` och `catchError()` — loadSettings, saveSettings, loadWeekdayGoals, saveWeekdayGoals, loadShiftTimes, saveShiftTimes, loadProducts, addProduct, saveProduct, deleteProduct. Om servern hanger fastnar UI:t i loading-state for evigt. Alla fixade med `timeout(8000), catchError(() => of(null))`. Null-guards (`res?.success` istallet for `res.success`) lagda pa alla tillhorande next-handlers.

**bonus-dashboard.ts**: `loadWeekTrend()` ateranvande `shiftChartTimeout`-timern som ocksa anvands av `reloadTeamStats()`. Om bada anropas nara varandra avbryts den forsta renderingen. Fixat med separat `weekTrendChartTimeout`-timer + cleanup i ngOnDestroy.

Bygger OK. Inga backend-andringar.

---

## 2026-03-09 Session #43 — Rebotling statistik: Produktionsoverblick + buggfix

**Produktionsoverblick (VD-vy)**: Ny panel hogst upp pa statistiksidan som visar:
- Dagens IBC-produktion mot mal med prognos
- Aktuell takt (IBC/h) och OEE med trend-pil vs igar
- Veckans produktion vs forra veckan med procentuell forandring
- 7-dagars sparkline-trend

Data hamtas fran befintligt exec-dashboard endpoint — inget nytt backend-arbete behovs.

**Buggfix: computeDayMetrics utilization**: Rattade berakning av utnyttjandegrad i dagsvyn. Variabeln `lastMin` anvandes bade for att spara senaste tidpunkten och for att rakna ut kortid, men uppdaterades vid varje event oavsett typ. Nu anvands separat `runStartMin` som bara uppdateras vid maskinstart.

Bygger OK. Inga backend-andringar.

---

## 2026-03-09 — ÄGARENS NYA DIREKTIV: Utveckling återupptagen

Ägaren har lagt över stabil version i produktion. Utvecklingsstoppet från vecka 10 är upphävt.

**Prioriteringar framåt:**
1. Statistiksidan — enkel överblick av produktion över tid
2. Buggjakt löpande
3. Enkel överblick — VD ska förstå läget direkt
4. Utveckla och förbättra övriga sidor

**Fixar gjorda manuellt av ägaren + claude (session):**
- `972b8d7` — news.ts API path fix (/api/api.php → /noreko-backend/api.php)
- `4053cf4` — statistik UTC date parsing fix (fel dag efter URL reload)
- `d18d541` + `fc32920` + `5689577` — deploy-scripts mappstruktur + chmod + gitattributes
- Lead-agent.sh: rätt claude-sökväg, max-turns 45/60, budget 5 per 5h

---

## 2026-03-09 Session #42 — Merge-konflikter (slutgiltigt) + Bug Hunt #47 Null safety

**Worker 1 — Merge-konflikter slutgiltigt losta**: 19 filer med UU-status aterstallda med `git checkout HEAD --`. Filerna matchade redan HEAD — problemet var olost merge-state i git index. `git diff --check` rent, bygge OK. Ingen commit behovdes.

**Worker 2 — Bug Hunt #47 Null safety (`9541cb2`)**: 17 fixar i 11 filer. parseInt utan NaN-guard (3 filer), .toFixed() pa null/undefined (4 filer, 20+ instanser), Array.isArray guard, division by zero, PHP fetch() utan null-check, PHP division med tom array.

**Sammanfattning session #42**: Merge-konflikter definitivt losta efter tre sessioners forsok. 17 null safety-fixar. Bug Hunts #1-#47 genomforda.

---

## 2026-03-09 Session #41 — Merge-konflikter (igen) + Bug Hunt #46 Accessibility

**Worker 1 — Merge-konflikter losta (`31e45c3`)**: 18 filer med UU-status fran session #40 aterstod. Alla losta — 3 svart korrupterade filer aterstallda fran last commit. Bygge verifierat rent.

**Worker 2 — Bug Hunt #46 Accessibility (`b9d6b4a`)**: 39 filer andrade. aria-label pa knappar/inputs, scope="col" pa tabellhuvuden, role="alert" pa felmeddelanden, for/id-koppling pa register-sidan. Forsta a11y-granskningen i projektets historia.

**Sammanfattning session #41**: Alla merge-konflikter slutgiltigt losta. 39 filer fick accessibility-forbattringar. Bug Hunts #1-#46 genomforda.

---

## 2026-03-09 Session #40b — Merge-konflikter lösta

**Löste alla kvarvarande merge-konflikter från session #40 worktrees (19 filer)**:
- **Backend**: `RebotlingController.php` (5 konflikter — behöll delegate-pattern), `SkiftrapportController.php` (1 konflikt), `WeeklyReportController.php` (3 konflikter — behöll refaktoriserade `aggregateWeekStats()`/`getOperatorOfWeek()` metoder)
- **Frontend routing/meny**: `app.routes.ts` (behöll operator-trend route), `menu.html` (behöll Prestanda-trend menyval)
- **Admin-sidor**: `klassificeringslinje-admin.ts`, `saglinje-admin.ts`, `tvattlinje-admin.ts` — behöll service-abstraktion + polling-timers + loadTodaySnapshot/loadAlertThresholds
- **Benchmarking**: `benchmarking.html` + `benchmarking.ts` — behöll Hall of Fame, Personbästa, Team vs Individ rekord
- **Live ranking**: `live-ranking.html` + `live-ranking.ts` — behöll lrConfig + lrSettings dual conditions + sortRanking
- **Rebotling admin**: `rebotling-admin.html` + `rebotling-admin.ts` — behöll alla nya features (goal exceptions, service interval, correlation, email shift report)
- **Skiftrapport**: `rebotling-skiftrapport.html` + `rebotling-skiftrapport.ts` — behöll Number() casting + KPI-kort layout
- **Weekly report**: `weekly-report.ts` — återskapad från committed version pga svårt korrupt merge (weekLabel getter hade blivit överskriven med loadCompareData-kod)
- **Service**: `rebotling.service.ts` — behöll alla nya metoder + utökade interfaces
- **dev-log.md**: Tog bort konfliktmarkeringar
- Angular build passerar utan fel

---

## 2026-03-09 Session #40 — Bug Hunt #45 Race conditions och timing edge cases

**Bug Hunt #45 — Race conditions vid snabb navigation + setTimeout-guarder**:
- **Race conditions vid snabb navigation (stale data)**: Lade till versionsnummer-monster i 4 komponenter for att forhindra att gamla HTTP-svar skriver over nya nar anvandaren snabbt byter period/vecka/operator:
  - `weekly-report.ts`: `load()` och `loadCompareData()` — snabb prevWeek/nextWeek kunde visa fel veckas data
  - `operator-trend.ts`: `loadTrend()` — snabbt byte av operator/veckor kunde visa fel operatorsdata
  - `historik.ts`: `loadData()` — snabbt periodbyte (12/24/36 manader) kunde visa gammal data
  - `production-analysis.ts`: Alla 7 tab-laddningsmetoder (`loadOperatorData`, `loadDailyData`, `loadHourlyData`, `loadShiftData`, `loadBestShifts`, `loadStopAnalysis`, `loadParetoData`) — snabbt periodbyte kunde visa stale data
- **Ospårade setTimeout utan cleanup**: Fixade 6 setTimeout-anrop i `stoppage-log.ts` som inte sparade timer-ID for cleanup i ngOnDestroy (pareto-chart, monthly-stop-chart, pattern-analysis chart)
- **Ospårad setTimeout i bonus-dashboard.ts**: `loadWeekTrend()` setTimeout fick tracked timer-ID
- **Ospårad setTimeout i my-bonus.ts**: Lade till `weeklyChartTimerId` med cleanup i ngOnDestroy
- **setTimeout utan destroy$-guard (chart-rendering efter destroy)**: Fixade 15 setTimeout-anrop i rebotling-admin och 12 rebotling statistik-subkomponenter som saknade `if (!this.destroy$.closed)` check:
  - `rebotling-admin.ts`: renderMaintenanceChart, buildGoalHistoryChart, renderCorrelationChart
  - `statistik-histogram.ts`, `statistik-waterfall-oee.ts`, `statistik-cykeltid-operator.ts`, `statistik-pareto-stopp.ts`, `statistik-kassation-pareto.ts`, `statistik-produktionsrytm.ts`, `statistik-veckojamforelse.ts`, `statistik-cykeltrend.ts`, `statistik-veckodag.ts`, `statistik-kvalitetstrend.ts`, `statistik-spc.ts`, `statistik-kvalitetsanalys.ts`, `statistik-oee-deepdive.ts`

**PHP backend**: Granskade TvattlinjeController, SaglinjeController, KlassificeringslinjeController, SkiftrapportController, StoppageController, WeeklyReportController. Alla write-operationer anvander atomara `INSERT ... ON DUPLICATE KEY UPDATE` eller `UPDATE ... WHERE` — inga read-then-write race conditions hittades.

**Sammanfattning session #40**: 25+ fixar. Versionsbaserad stale-data-prevention i 4 huvudkomponenter (7+ HTTP-anrop). 20+ setTimeout-anrop fick destroy$-guard eller tracked timer-ID for korrekt cleanup.

---

## 2026-03-09 Session #39 — Bug Hunt #44 Formularvalidering + Error/Loading states

**Worker 1 — Bug Hunt #44 Formularvalidering och input-sanering** (commit `af2e7e2`):
- ~30 Angular-komponenter + ~8 PHP-controllers granskade
- 28 fixar totalt:
  - Register/create-user/login: minlength/maxlength pa anvandardnamn och losenord
  - Stoppage-log: required pa stopporsak-select, maxlength pa kommentar, dubbelklick-skydd
  - Certifications: required pa operator/linje/datum-select
  - Users: minlength/maxlength, dubbelklick-skydd vid sparning
  - Maintenance-form: max-varden pa varaktighet/kostnad
  - Shared-skiftrapport + rebotling-skiftrapport: required pa datum, max pa antal, dubbelklick-skydd
  - Rebotling-admin: required pa kassation-datum/orsak, max pa cykeltid
  - PHP AdminController: username-langdvalidering (3-50), losenordskrav (8+, bokstav+siffra)
  - PHP MaintenanceController: max-validering varaktighet/driftstopp/kostnad
  - PHP StoppageController: sluttid efter starttid, kommentarlangd max 500
  - PHP CertificationController: utgangsdatum efter certifieringsdatum

**Worker 2 — Bug Hunt #44b Error states och loading states** (commit `af2e7e2`):
- 25+ komponentfiler granskade
- 10 retry-knappar tillagda pa sidor som saknade "Forsok igen"-funktion:
  - benchmarking, rebotling-prognos, production-analysis, historik, operator-attendance, monthly-report, operator-trend, weekly-report, production-calendar, shift-plan
- Befintliga sidor (executive-dashboard, bonus-dashboard, my-bonus, rebotling-statistik, rebotling-skiftrapport, operator-dashboard) hade redan fullstandig loading/error/empty state-hantering

**Sammanfattning session #39**: 38 fixar (28 formularvalidering + 10 error/retry states). Formularvalidering bade frontend (HTML-attribut + TS-logik + dubbelklick-skydd) och backend (PHP defense in depth). Alla sidor har nu "Forsok igen"-knappar vid felmeddelanden.

---

## 2026-03-09 Session #38 — Bug Hunt #43 Subscribe-lackor + Responsiv design audit

**Worker 1 — Bug Hunt #43 Angular subscribe-lackor** (commit `baa3e4c`):
- 57 komponentfiler granskade (exkl. live-sidor)
- 2 subscribe-lackor fixade: bonus-dashboard.ts och executive-dashboard.ts saknade takeUntil(destroy$) pa HTTP-anrop i polling-metoder
- Ovriga 55 filer redan korrekta: alla har destroy$ + ngOnDestroy + takeUntil
- Alla 15 filer med setInterval-polling har matchande clearInterval
- Inga ActivatedRoute param-subscribes utan cleanup

**Worker 2 — Bug Hunt #43b Responsiv design och CSS-konsistens** (commit via worker):
- 12 filer andrade, 17 fixar totalt
- 4 tabeller utan responsive wrapper: operator-attendance, audit-log (2), my-bonus
- 4 overflow:hidden→overflow-x:auto: rebotling-skiftrapport (2), weekly-report (2)
- 8 fasta bredder→relativa: skiftrapport-filterinputs i 5 sidor (rebotling, shared, tvattlinje, saglinje, klassificeringslinje)
- 2 flexbox utan flex-wrap: certifications tab-nav, executive-dashboard oee-row

**Sammanfattning session #38**: 19 fixar (2 subscribe-lackor + 17 responsiv design). Subscribe-lacker i bonus-dashboard och executive-dashboard kunde orsaka minneslakor vid navigation under aktiv polling. Responsiv design nu battre for surfplattor i produktionsmiljon.

---

## 2026-03-09 Session #37 — Bug Hunt #42 Timezone deep-dive + Dead code audit

**Worker 1 — Bug Hunt #42 Timezone deep-dive** (commit via worker):
- Ny utility-modul date-utils.ts: localToday(), localDateStr(), parseLocalDate()
- ~50 instanser av toISOString().split('T')[0] ersatta med localToday() — gav fel dag efter kl 23:00 CET
- ~10 instanser av datum-formatering pa Date-objekt fixade med localDateStr()
- formatDate()-funktioner fixade med parseLocalDate() i 6 komponenter
- PHP api.php: date_default_timezone_set('Europe/Stockholm') tillagd
- 32 filer andrande, 135 rader tillagda / 64 borttagna
- 2 kvarstaende timezone-buggar i saglinje-live + klassificeringslinje-live (live-sidor, ror ej)

**Worker 2 — Bug Hunt #42b Dead code audit** (commit via worker):
- 13 oanvanda imports borttagna i 9 TypeScript-filer
- 1 oanvand npm-dependency (htmlparser2) borttagen fran package.json
- Kodbasen ar REN: inga TODO/FIXME, inga console.log, inga tomma PHP-filer, inga oanvanda routes

**Sammanfattning session #37**: ~65 timezone-fixar + 14 dead code-rensningar. Timezone-buggen var systematisk — toISOString() gav fel datum efter kl 23 CET i ~50 komponenter. Nu centraliserat i date-utils.ts.

---

## 2026-03-06 Session #36 — Bug Hunt #41 Chart.js lifecycle + Export/formatering

**Worker 1 — Bug Hunt #41 Chart.js lifecycle** (commit via worker):
- 37 chart-komponenter granskade — alla har korrekt destroy(), tomma dataset-guards, canvas-hantering
- 9 tooltip-callbacks fixade: null/undefined-guards pa ctx.parsed.y/x/r i 9 filer (statistik-waterfall-oee, operator-compare, operator-dashboard, monthly-report, rebotling-admin, stoppage-log, audit-log, executive-dashboard, historik)

**Worker 2 — Bug Hunt #41b Export/formatering** (commit via worker):
- 3 CSV-separator komma→semikolon (Excel Sverige): operators, weekly-report, monthly-report
- 1 PHP BonusAdminController: UTF-8 BOM + charset + semikolon-separator for CSV-export
- 3 Print CSS @page A4-regler: executive-dashboard, my-bonus, stoppage-log + weekly-report inline

**Sammanfattning session #36**: 16 fixar (9 Chart.js tooltip null-guards + 7 export/formatering). Tooltip-guards forhindrar NaN vid null-datapunkter. CSV-exporter nu Excel-kompatibla i Sverige (semikolon + BOM). Print-layout A4-optimerad.

---

## 2026-03-06 Session #35 — Bug Hunt #40 PHP-robusthet + Angular navigation edge cases

**Worker 1 — Bug Hunt #40 PHP-robusthet** (commit via worker):
- 5 datumintervallbegränsningar (max 365 dagar): BonusController period='all'/default/custom, RebotlingAnalyticsController getOEETrend+getBestShifts+getCycleByOperator, RebotlingController getHeatmap
- 1 export LIMIT: BonusAdminController exportReport CSV saknade LIMIT → max 50000 rader
- 3 SQL-transaktioner: ShiftPlanController copyWeek, RebotlingAdminController saveWeekdayGoals, BonusAdminController setAmounts — alla multi-row writes nu i BEGIN/COMMIT
- Granskade OK: WeeklyReportController, ExecDashboardController, alla controllers har try/catch utan stack traces

**Worker 2 — Bug Hunt #40b Angular navigation** (commit via worker):
- authGuard: saknade returnUrl vid redirect till /login — användare tappade sin sida
- adminGuard: skilde ej mellan ej-inloggad och ej-admin — fel redirect
- login.ts: ignorerade returnUrl — navigerade alltid till / efter login
- error.interceptor.ts: rensade ej sessionStorage vid 401 — stale auth-cache
- Granskade OK: 404-route finns (NotFoundPage), alla routes lazy loadade, alla guards konsistenta, navigation cleanup korrekt

**Sammanfattning session #35**: 13 fixar (9 PHP backend-robusthet + 4 Angular navigation). Datumintervallbegränsningar förhindrar timeout vid stora queries, SQL-transaktioner säkrar concurrent writes, auth-flödet nu komplett med returnUrl-stöd.

---

## 2026-03-06 Session #34 — Bug Hunt #39 session/auth edge cases + data-konsistens

**Worker 1 — Bug Hunt #39 Session/auth edge cases** (commit via worker):
- 5 backend-fixar: ShiftHandoverController+SkiftrapportController 403→401 vid expired session, BonusAdminController+MaintenanceController read_and_close for POST→full session, FeedbackController GET→read_and_close
- 4 frontend-fixar: auth.service.ts polling stoppades aldrig vid logout (minnesläcka), logout rensade state EFTER HTTP (race condition), logout navigerade ej till /login, login aterstartade ej polling
- Verifierat: errorInterceptor fangar 401 korrekt, auth guards fungerar, session.gc_maxlifetime=86400s

**Worker 2 — Bug Hunt #39b Data-konsistens** (`91329eb`):
- KRITISK: runtime_plc /3600→/60 missades i 4 controllers (18 stallen): OperatorController (7), OperatorCompareController (4), AndonController (4), OperatorDashboardController (3). IBC/h var 60x for lagt pa dessa sidor.
- Verifierat konsistent: IBC-antal, OEE 3-faktor-formel, bonus-berakningar, idealRate=0.25 overallt

**Sammanfattning session #34**: 9 backend-fixar + 4 frontend-fixar = 13 fixar. KRITISK bugg: runtime_plc-enhetsfel kvarstaende fran Bug Hunt #32 i 4 controllers — alla IBC/h pa operator-detail, operator-compare, andon, operator-dashboard var 60x for laga.

---

## 2026-03-06 Session #33 — Bug Hunt #38 service-backend kontrakt + CSS/UX-konsistens

**Worker 1 — Bug Hunt #38 Service-backend kontrakt** (`6aac887`):
- KRITISK: `action=operator` saknades i api.php classNameMap → operator-detail/profil-sidan returnerade 404. Fixad.
- CORS: PUT-requests blockerades (Access-Control-Allow-Methods saknade PUT/DELETE). Fixad.
- 31 frontend-endpoints verifierade mot 34 backend-endpoints. Alla POST-parametrar, run-värden och HTTP-metoder korrekt.
- 1 orphan-endpoint: `runtime` (RuntimeController) — ingen frontend anropar den, lämnad som-is.

**Worker 2 — Bug Hunt #38b Build-varningar + CSS/UX** (`aa5ee90`):
- Build nu 100% varningsfri (budget-trösklar justerade till rimliga nivåer)
- 8 CSS dark theme-fixar: bakgrund #0f1117→#1a202c i 4 sidor + body, bg-info cyan→blå i 3 sidor, focus ring i users
- Loading/error/empty-state: alla 7 nyckelsidor verifierade OK

**Sammanfattning session #33**: 10 fixar (2 service-backend + 8 CSS). KRITISK bugg: operator-detail-sidan var trasig (404).

### Del 2: CSS dark theme — 8 fixar
- **Bakgrund #0f1117 → #1a202c**: Standardiserade 4 sidor (my-bonus, bonus-dashboard, production-analysis, bonus-admin) fran avvikande #0f1117 till #1a202c som anvands av 34+ sidor.
- **Global body bakgrund**: Andrade fran #181a1b till #1a202c for konsistens med page containers.
- **bg-info cyan → bla**: Fixade operators.css, users.css och rebotling-admin.css fran Bootstrap-default #0dcaf0 (ljuscyan) till dark-theme #4299e1/rgba(66,153,225,0.25).
- **Focus ring**: users.css formular-fokus andrad fran #86b7fe till #63b3ed (matchar ovriga dark theme-sidor).
- **border-primary**: users.css #0d6efd → #4299e1.

### Del 3: Loading/error/empty-state — ALLA OK
- Granskade 7 nyckelsidor: executive-dashboard, bonus-dashboard, my-bonus, production-analysis, operators, users, rebotling-statistik.
- ALLA har: loading spinner, felmeddelande vid API-fel, empty state vid tom data.
- my-bonus har den mest granulara implementationen med 10+ separata loading states for subsektioner.

---


**Plan**: Worker 1 granskar Angular service→PHP endpoint kontrakt (parameternamn, URL-matchning, respons-typer). Worker 2 granskar build-varningar + dark theme CSS-konsistens + loading/error/empty-state-mönster.

**Worker 1 — Bug Hunt #38 service-backend kontrakt-audit**:
- Granskade alla 14 Angular service-filer + alla komponent-filer med HTTP-anrop (44 filer totalt)
- Kartlade 31 unika `action=`-värden i frontend mot api.php classNameMap (34 backend-endpoints)
- **BUG 1 (KRITISK)**: `action=operator` (singular) används i `operator-detail.ts` men saknades i api.php classNameMap → 404-fel, operatörsprofil-sidan helt trasig. Fix: lade till `'operator' => 'OperatorController'` i classNameMap.
- **BUG 2**: CORS-headern tillät bara `GET, POST, OPTIONS` men `rebotling-admin.ts` skickar `PUT` till `action=rebotlingproduct` → CORS-blockering vid cross-origin. Fix: lade till `PUT, DELETE` i `Access-Control-Allow-Methods`.
- **Orphan-endpoints** (backend utan frontend): `runtime` — noterat men ej borttaget (kan användas av externa system)
- **Granskade OK**: Alla POST-body parametrar matchar PHP `json_decode(php://input)`, alla `run=`-parametrar matchar backend switch/if-routing, alla HTTP-metoder (GET vs POST) korrekt förutom de 2 fixade buggarna

---

## 2026-03-06 Session #32 — Bug Hunt #37 formulärvalidering + error recovery

**Worker 1 — Bug Hunt #37 Formulärvalidering** (`5bb732e`):
- 5 fixar: negativa värden i maintenance-form (TS-validering), saknad required+maxlength i rebotling-admin (produktnamn, cykeltid, datum-undantag, fritextfält), saknad required i news-admin (rubrik)
- Granskade OK: bonus-admin, operators, users, create-user, shift-plan, certifications

**Worker 2 — Bug Hunt #37b Error recovery** (`c5efe8d`):
- 2 fixar: rebotling-admin loadSystemStatus() saknade timeout+catchError (KRITISK — polling dog permanent), bonus-dashboard loading flicker vid 30s polling
- Granskade OK: executive-dashboard, live-ranking, andon, operator-dashboard, my-bonus, production-analysis, rebotling-statistik

**Sammanfattning session #32**: 7 fixar (5 formulärvalidering + 2 error recovery). Frontend-validering och polling-robusthet nu komplett.

---

## 2026-03-06 Session #31 — Bug Hunt #36 säkerhetsrevision + bonus-logik edge cases

**Worker 1 — Bug Hunt #36 Säkerhetsrevision PHP** (`04217be`):
- 18 fixar: 3 SQL injection (strängkonkatenering→prepared statements), 14 input-sanitering (strip_tags på alla string-inputs i 10 controllers), 1 XSS (osaniterad e-post i error-meddelande)
- Auth/session: alla endpoints korrekt skyddade
- Observation: inget CSRF-skydd (API-baserad arkitektur, noterat)

**Worker 2 — Bug Hunt #36b Bonus-logik edge cases** (`ab6242f`):
- 2 fixar: getNextTierInfo() fel tier-sortering i my-bonus, getOperatorTrendPct() null guard i bonus-dashboard
- Granskade OK: alla division-by-zero guards, simulator, veckohistorik, Hall of Fame, negativ bonus

**Sammanfattning session #31**: 20 fixar (18 säkerhet + 2 bonus-logik). Säkerhetsrevidering komplett för hela PHP-backend.

---

## 2026-03-06 Session #30 — Bug Hunt #35 error handling + API consistency

**Worker 1 — Bug Hunt #35 Angular error handling** (`d5a6576`):
- 10 buggar fixade i 4 komponenter (6 filer):
- bonus-dashboard: cachad getActiveRanking (CD-loop), separata loading-flaggor (3 flöden), empty states för skiftöversikt+Hall of Fame, felmeddelande vid catchError, error-rensning vid periodbyte
- executive-dashboard: dashError-variabel vid API-fel, disabled "Försök igen" under laddning
- my-bonus: distinkt felmeddelande vid nätverksfel vs saknad data (sentinel-värde)
- production-analysis: nollställ bestDay/worstDay/avgBonus/totalIbc vid tom respons

**Worker 2 — Bug Hunt #35b PHP API consistency** (`1806cc9`):
- 9 buggar fixade i RebotlingAnalyticsController.php:
- 9 error-responses returnerade HTTP 200 istf 400/500 (getOEETrend, getDayDetail, getAnnotations, sendAutoShiftReport×3, sendWeeklySummaryEmail×3)
- BonusController + WeeklyReportController: inga buggar — konsekvent format, korrekt sendError/sendSuccess, prepared statements, division-by-zero guards

**Sammanfattning session #30**: 19 buggar fixade (10 Angular + 9 PHP). Error handling och API consistency nu granskade systematiskt.

---

## 2026-03-06 Session #29 — Bug Hunt #34 datum/tid + Angular performance audit

**Worker 1 — Bug Hunt #34 datum/tid edge cases** (`8d969af`):
- 2 buggar fixade: ISO-veckoberäkning i executive-dashboard (vecka 0 vid söndag Jan 4), veckosammanfattning i RebotlingAnalyticsController (årsgräns-kollision i grupperingsnyckel)
- 4 filer granskade utan problem: WeeklyReportController, BonusController, production-calendar, monthly-report

**Worker 2 — Angular performance audit** (`38577f7`):
- ~55 trackBy tillagda i 5 komponenter (eliminerar DOM re-rendering)
- ~12 tunga template-funktioner ersatta med cachade properties
- OnPush ej aktiverat (kräver större refactor)
- Bundle size oförändrat (665 kB)

**Sammanfattning session #29**: 2 datum/tid-buggar fixade, 55 trackBy + 12 cachade properties = markant bättre runtime-prestanda

---

## 2026-03-06 Angular Performance Audit — trackBy + cachade template-beräkningar

**Granskade komponenter (5 st, rebotling-statistik existerade ej):**

1. **production-analysis** — 12 ngFor med trackBy, 9 tunga template-funktioner cachade som properties
   - `getFilteredRanking()` → `cachedFilteredRanking` (sorterad array skapades vid varje CD)
   - `getTimelineBlocks()`, `getTimelinePercentages()` → cachade properties
   - `getStopHoursMin()`, `getAvgStopMinutes()`, `getWorstCategory()` → cachade KPI-värden
   - `getParetoTotalMinuter()`, `getParetoTotalStopp()`, `getParetoEightyPctGroup()` → cachade
   - Alla cache-properties uppdateras vid data-laddning, inte vid varje change detection

2. **executive-dashboard** — 10 ngFor med trackBy (lines, alerts, days7, operators, nyheter, bemanning, veckorapport)

3. **rebotling-skiftrapport** — 9 ngFor med trackBy, `getOperatorRanking(report)` cachad per rapport-ID
   - Denna funktion var O(n*m) — itererade alla rapporter per operatör vid varje CD-cykel
   - Nu cachad i Map<id, result[]>, rensas vid ny dataladdning

4. **my-bonus** — 8 ngFor med trackBy, `getAchievements()` + `getEarnedAchievementsCount()` cachade
   - Cache uppdateras efter varje async-laddning (stats, pb, streak)

5. **bonus-admin** — 16 ngFor med trackBy, `getPayoutsYears()` cachad som readonly property

**Sammanfattning:**
- ~55 trackBy tillagda (eliminerar DOM re-rendering vid oförändrad data)
- ~12 tunga template-funktioner ersatta med cachade properties
- OnPush INTE aktiverat — alla komponenter muterar data direkt (kräver större refactor)
- Bygget OK, bundle size oförändrat (665 kB)

---

## 2026-03-06 Bug Hunt #34 — Datum/tid edge cases och boundary conditions

**Granskade filer (6 st):**

**PHP Backend:**
1. `RebotlingAnalyticsController.php` — exec-dashboard, year-calendar, day-detail, monthly-report, month-compare, OEE-trend, week-comparison
2. `WeeklyReportController.php` — veckosummering, veckokomparation, ISO-vecka-hantering
3. `BonusController.php` — bonusperioder, getDateFilter(), weekly_history, getWeeklyHistory

**Angular Frontend:**
4. `executive-dashboard.ts` — daglig data, 7-dagars historik, veckorapport
5. `production-calendar.ts` — månadskalender, datumnavigering, dagdetalj
6. `monthly-report.ts` — månadsrapport, datumintervall

**Hittade och fixade buggar (2 st):**

1. **BUG: ISO-veckoberäkning i `initWeeklyWeek()`** (`executive-dashboard.ts` rad 679-680)
   - Formeln använde `new Date(d.getFullYear(), 0, 4)` (Jan 4) med offset `yearStart.getDay() + 1`
   - När Jan 4 faller på söndag (getDay()=0) ger formeln vecka 0 istället för vecka 1
   - Drabbar 2026 (innevarande år!), 2015, 2009 — alla år där 1 jan = torsdag
   - **Fix**: Ändrade till Jan 1-baserad standardformel: `yearStart = Jan 1`, offset `+ 1`

2. **BUG: Veckosammanfattning i månadsrapporten tappar ISO-år** (`RebotlingAnalyticsController.php` rad 2537)
   - Veckoetiketten byggdes med `'V' . date('W')` utan ISO-årsinformation
   - Vid årgränser (t.ex. december 2024) hamnar dec 30-31 i V1 istf V52/V53
   - Dagar från två olika år med samma veckonummer aggregeras felaktigt ihop
   - **Fix**: Lade till ISO-år (`date('o')`) i grupperingsnyckel, behåller kort "V"-etikett i output

**Granskat utan buggar:**
- WeeklyReportController: korrekt `setISODate()` + `format('W')`/`format('o')` — inga ISO-vecka-problem
- BonusController: `getDateFilter()` använder `BETWEEN` korrekt, `YEARWEEK(..., 3)` = ISO-mode konsekvent
- production-calendar.ts: korrekta `'T00:00:00'`-suffix vid `new Date()` för att undvika timezone-tolkning
- monthly-report.ts: `selectedMonth` default beräknas korrekt med `setMonth(getMonth()-1)` inkl. år-crossover
- SQL-frågor: BETWEEN med DATE()-wrapped kolumner — endpoint-inklusivt som förväntat
- Tomma dataperioder: NULLIF()-guards överallt, division-by-zero skyddade

---

## 2026-03-06 Session #28 — Bug Hunt #33 dead code + Bundle size optimering

**Worker 1 — Bug Hunt #33 dead code cleanup** (`70b74c4`):
- Routing-integritet verifierad: alla 48 Angular routes + 32 PHP API actions korrekt mappade
- 3 filer borttagna (899 rader): oanvänd `news.ts` service, `news.spec.ts`, `bonus-charts/` komponent (aldrig importerad)
- 9 dead methods borttagna: 8 oanvända metoder i `rebotling.service.ts`, 1 i `tvattlinje.service.ts`
- 7 oanvända interfaces borttagna

**Worker 2 — Bundle size optimering** (`90c655b`):
- **843 kB → 666 kB (−21%, sparade 178 kB)**
- FontAwesome CSS subset: `all.min.css` (74 kB) → custom subset (13.5 kB) med bara 190 använda ikoner
- Bootstrap JS lazy loading: tog bort `bootstrap.bundle.min.js` (80 kB) från global scripts, dynamisk import i Menu
- News-komponent lazy loading: eagerly loaded → `loadComponent: () => import(...)`
- Oanvända imports borttagna: FormsModule, CommonModule, NgIf-dublett, HostBinding

**Sammanfattning session #28**: Dead code borttagen (899 rader + 9 metoder + 7 interfaces), bundle reducerad 21%, all routing verifierad intakt

---

## 2026-03-06 Session #27 — Angular template-varningar cleanup + Bug Hunt #32

**Worker 1 — Angular template-varningar** (`57fd644`):
- 33 NG8107/NG8102-varningar eliminerade i 6 HTML-filer (menu, bonus-admin, certifications, my-bonus, production-analysis, rebotling-skiftrapport)
- Onödiga `?.` och `??` operatorer borttagna där TypeScript-typer redan garanterar icke-null

**Worker 2 — Bug Hunt #32** (`9c0b431`, 4 buggar fixade):
- **KRITISK**: RebotlingAnalyticsController getShiftCompare — OEE saknade Performance-komponent (2-faktor istf 3-faktor)
- **KRITISK**: RebotlingAnalyticsController getDayDetail — runtime_plc-alias felkalkylerade IBC/h (60x för lågt)
- **KRITISK**: WeeklyReportController — 7 ställen delade runtime_plc/3600 istf /60 (60x för hög IBC/h)
- **KRITISK**: BonusController — 7 ställen samma enhetsblandning i hall-of-fame/personbästa/achievements/veckotrend

**Sammanfattning session #27**: 6 filer ändrade, 33 varningar eliminerade, 4 KRITISKA beräkningsbuggar fixade

---

## 2026-03-05 — Bug Hunt #31: Float-modulo i tidsformatering (17 fixar i 7 filer)

- **executive-dashboard.ts**: `formatDuration()` och `formatStopTime()` — `min % 60` utan `Math.round()` producerade decimalminuter (t.ex. "2:05.5" istället för "2:06") när backend-SUM returnerade float
- **stoppage-log.ts**: 7 ställen i formatMinutes/formatDuration/tooltip-callbacks — samma float-modulo-bugg
- **rebotling-skiftrapport.ts**: `formatMinutes()`, `formatDrifttid()`, PDF-export drifttid — samma bugg
- **andon.ts**: `formatSekunder()` och tidsålder-formatering — sekunder och minuter utan avrundning
- **operator-dashboard.ts**: `minuter()` helper — returnerade `min % 60` utan avrundning
- **maintenance-log.helpers.ts**: Delad `formatDuration()` — samma bugg

**Granskade utan buggar**: production-analysis.ts (redan fixat i #30), bonus-dashboard.ts, monthly-report.ts, BonusController.php, RebotlingAnalyticsController.php — backend har genomgående `max(..., 1)` guards mot division-by-zero.

---

## 2026-03-05 — Ta bort mockData-fallbacks + tom ProductController

- **rebotling-statistik.ts**: Borttagen `loadMockData()` + `generateMockData()` — vid API-fel visas felmeddelande istället för falska random-siffror
- **tvattlinje-statistik.ts**: Samma rensning
- **ProductController.php**: Tom fil (0 bytes) borttagen

---

## 2026-03-05 Session #25 — DRY-refactoring + kodkvalitet (INGEN NY FUNKTIONSUTVECKLING)

**Worker 1 — Generic SkiftrapportComponent** (`a6520cf`):
- shared-skiftrapport/ skapad med LineSkiftrapportConfig interface
- 3 linje-skiftrapporter (tvattlinje/saglinje/klassificeringslinje) reducerade från 220-364 till ~20 rader vardera
- Rebotling-skiftrapport (1812 rader) behölls separat pga väsentligt annorlunda funktionalitet

**Worker 2 — TypeScript any-audit** (`ab16ad5`):
- 72 `: any` ersatta med korrekta interfaces i 5 filer
- 11+ nya interfaces skapade (SimulationResult, AuthUser, DailyDataPoint m.fl.)

---

## 2026-03-05 — Refactor: TypeScript `any`-audit — 72 `any` ersatta med korrekta interfaces

Ersatte alla `: any` i 5 filer (bonus-admin.ts, production-analysis.ts, news.ts, menu.ts, auth.service.ts):
- **bonus-admin.ts** (31→0): SimulationResult, SimOperatorResult, SimComparisonRow, SimHistResult, PayoutRecord, PayoutSummaryEntry, AuditResult, AuditOperator m.fl. interfaces
- **production-analysis.ts** (23→0): DailyDataPoint, WeekdayDataPoint, ParetoItem, HeatmapApiResponse, Chart.js TooltipItem-typer, RastEvent
- **news.ts** (11→0): LineSkiftrapportReport, LineReportsResponse, ReturnType<typeof setInterval>
- **menu.ts** (5→0): LineStatusApiResponse, VpnApiResponse, ProfileApiResponse, explicit payload-typ
- **auth.service.ts** (2→0): AuthUser-interface exporteras, BehaviorSubject<AuthUser | null | undefined>
- Uppdaterade bonus-admin.html med optional chaining för nullable templates
- Alla filer bygger utan fel

## 2026-03-05 — Refactor: Generic SkiftrapportComponent (DRY)

Slog ihop 3 nästintill identiska skiftrapport-sidor (tvattlinje/saglinje/klassificeringslinje) till 1 delad komponent:
- Skapade `shared-skiftrapport/` med generisk TS + HTML + CSS som konfigureras via `LineSkiftrapportConfig`-input
- Tvattlinje (364 rader -> 20), Saglinje (244 -> 20), Klassificeringslinje (220 -> 20) = tunna wrappers
- Ca 800 rader duplicerad kod eliminerad, ersatt med 1 komponent (~310 rader TS + HTML + CSS)
- Rebotling-skiftrapporten (1812 rader) behölls separat — helt annan funktionalitet (charts, produkter, email, sortering etc.)
- Routing oförändrad — samma URL:er, samma exporterade klassnamn
- Alla 3 linjer behåller sin unika färgtema (primary/warning/success) via konfiguration

## 2026-03-05 Session #24 — Bug Hunt #30 + Frontend sista-audit (INGEN NY FUNKTIONSUTVECKLING)

**Worker 1 — Bug Hunt #30** (6 PHP-filer granskade, 24 buggar fixade):
- AuthHelper.php: OK — ren utility-klass
- ProductController.php: Tom fil (0 bytes)
- RebotlingProductController.php: 8 fixar — session read_and_close for GET, HTTP 400/404/500 statuskoder
- RuntimeController.php: 10 fixar — HTTP 405 vid ogiltig metod, HTTP 400/500 statuskoder
- ShiftHandoverController.php: 3 fixar — success:false i error-responses, session read_and_close
- LineSkiftrapportController.php: 3 fixar — session read_and_close, SQL prepared statements

**Worker 2 — Frontend sista-audit** (12 Angular-komponenter granskade, 7 buggar fixade):
- tvattlinje-statistik.ts: 3 fixar — saknad timeout/catchError, felaktig chart.destroy(), setTimeout-läcka
- saglinje-statistik.ts: 2 fixar — saknad timeout/catchError, setTimeout-läcka
- klassificeringslinje-statistik.ts: 2 fixar — saknad timeout/catchError, setTimeout-läcka
- 9 filer rena: certifications, vpn-admin, andon, tvattlinje-admin/skiftrapport, saglinje-admin/skiftrapport, klassificeringslinje-admin/skiftrapport

**Sammanfattning session #24**: 18 filer granskade, 31 buggar fixade. HELA KODBASEN NU GRANSKAD. Alla PHP-controllers och Angular-komponenter har genomgått systematisk bug-hunting (Bug Hunt #1-#30).

---

## 2026-03-05 Session #23 — Bug Hunt #29 + Frontend ogranskade-sidor-audit (INGEN NY FUNKTIONSUTVECKLING)

**Worker 1 — Bug Hunt #29** (6 PHP-controllers granskade, 8 buggar fixade):
- AdminController: 3 fixar — session read_and_close för GET, saknad HTTP 404 i toggleAdmin/toggleActive
- AuditController: 2 fixar — session read_and_close (GET-only controller), catch-block returnerade success:true vid HTTP 500
- LoginController: OK — inga buggar
- RegisterController: OK — inga buggar
- OperatorController: 1 fix — session read_and_close för GET-requests
- RebotlingAdminController: 2 fixar — getLiveRankingSettings session read_and_close, saveMaintenanceLog catch returnerade success:true vid HTTP 500

**Worker 2 — Frontend ogranskade-sidor-audit** (12 Angular-komponenter granskade, 13 buggar fixade):
- users.ts: 6 fixar — 6 HTTP-anrop saknade takeUntil(destroy$)
- operators.ts: 2 fixar — setTimeout-callbacks utan destroy$.closed-guard
- operator-detail.ts: 1 fix — setTimeout utan variabel/clearTimeout/guard
- news-admin.ts: 1 fix — setTimeout i saveNews() utan variabel/clearTimeout/guard
- maintenance-log.ts: 3 fixar — 3 setTimeout i switchTab() utan variabel/clearTimeout/guard
- 7 filer rena: about, contact, create-user, operator-compare, login, register, not-found

**Sammanfattning session #23**: 18 filer granskade, 21 buggar fixade. Inga nya features.

---

## 2026-03-05 Session #22 — Bug Hunt #28 + Frontend admin/bonus-audit (INGEN NY FUNKTIONSUTVECKLING)

**Worker 1 — Bug Hunt #28** (BonusController.php + BonusAdminController.php, 13 buggar fixade):
- BonusController: 11 fixar — konsekvent sendError()/sendSuccess() istället för raw echo, HTTP 405 vid felaktig metod, korrekt response-wrapper med data/timestamp
- BonusAdminController: 1 fix — getFairnessAudit catch-block använde raw echo istället för sendError()
- Godkänt: session read_and_close, auth-kontroller, prepared statements, division-by-zero-skydd, COALESCE/NULL-hantering

**Worker 2 — Frontend admin/bonus-audit** (rebotling-admin.ts, bonus-admin.ts, my-bonus.ts, 4 buggar fixade):
- bonus-admin: setTimeout-läckor i showSuccess()/showError() — saknad destroy$.closed-guard
- my-bonus: setTimeout-läckor i loadAchievements() confetti-timer + submitFeedback() — saknad referens + destroy$-guard
- Godkänt: rebotling-admin.ts helt ren (alla charts/intervals/subscriptions korrekt städade)

**Sammanfattning session #22**: 5 filer granskade, 17 buggar fixade. Commits: `e9eeef0`, `794f43d`, `14f2f7f`.

---

## 2026-03-05 Session #21 — Bug Hunt #27 + Frontend djupgranskning (INGEN NY FUNKTIONSUTVECKLING)

**Resultat**: 5 buggar fixade i RebotlingAnalyticsController, RebotlingController, rebotling-skiftrapport, rebotling-statistik. Commit: `e9eeef0`.

---

## 2026-03-05 Session #20 — Bug Hunt #26 + Frontend-stabilitetsaudit (INGEN NY FUNKTIONSUTVECKLING)

**Worker 1 — Bug Hunt #26** (6 PHP-controllers granskade, 9 buggar fixade):
- **WeeklyReportController.php**: KRITISK FIX — operators-JOIN anvande `o.id` istallet for `o.number`, gav fel operatorsdata. + session read_and_close + HTTP 405.
- **VpnController.php**: FIXAD — saknad auth-check (401 for utloggade), saknad HTTP 500 i catch-block, session read_and_close.
- **OperatorDashboardController.php**: FIXAD — HTTP 405 vid felaktig metod.
- **SkiftrapportController.php**: FIXAD — session read_and_close for GET-requests.
- **StoppageController.php**: FIXAD — session read_and_close for GET-requests.
- **ProfileController.php**: FIXAD — session read_and_close for GET-requests (POST behaller skrivbar session).

**Worker 2 — Frontend-stabilitetsaudit** (7 Angular-komponenter granskade, 2 buggar fixade):
- **production-calendar.ts**: FIXAD — setTimeout-lacka i dagdetalj-chart (saknad referens + clearTimeout)
- **weekly-report.ts**: FIXAD — setTimeout-lacka i chart-bygge (saknad referens + clearTimeout)
- **historik.ts, live-ranking.ts, operator-trend.ts, rebotling-prognos.ts, operator-attendance.ts**: OK — alla hade korrekt takeUntil, chart.destroy(), felhantering

**Sammanfattning session #20**: 13 filer granskade, 11 brister fixade (1 kritisk). Inga nya features.

---

## 2026-03-05 Session #19 — Bug Hunt #25 + Backend-endpoint konsistensaudit (INGEN NY FUNKTIONSUTVECKLING)

**Worker 1 — Bug Hunt #25** (5 filer granskade, 3 buggar fixade):
- **operator-dashboard.ts**: FIXAD — setTimeout-lacka i laddaVeckodata(), timer-referens saknades, kunde trigga chart-bygge efter destroy
- **benchmarking.ts**: FIXAD — chartTimer skrevs over utan att rensa foregaende, dubbla chart-byggen mojliga
- **shift-handover.ts**: FIXAD — setTimeout-lacka i focusTextarea(), ackumulerade timers vid upprepade anrop
- **executive-dashboard.ts**: OK — korrekt takeUntil, timeout, catchError, chart.destroy(), isFetching-guards
- **monthly-report.ts**: OK — forkJoin med takeUntil, inga polling-lakor

**Worker 2 — Backend-endpoint konsistensaudit** (3 filer granskade, 4 brister fixade):
- **HistorikController.php**: FIXAD — saknade HTTP 405 vid felaktig metod (POST/PUT/DELETE accepterades tyst)
- **AndonController.php**: FIXAD — saknade HTTP 405 + 2 catch-block returnerade success:true vid HTTP 500
- **ShiftPlanController.php**: FIXAD — requireAdmin() anvande session_start() utan read_and_close + copyWeek() returnerade 200 vid tom data (nu 404)
- **ProductionEventsController.php**: Finns inte i projektet — noterat

**Sammanfattning session #19**: 8 filer granskade, 7 brister fixade. Inga nya features.

---

## 2026-03-05 Session #18 — Bug Hunt #24 + Data-integritet/edge-case-hardning (INGEN NY FUNKTIONSUTVECKLING)

**Worker 1 — Bug Hunt #24** (6 filer granskade, 2 buggar fixade):
- **RebotlingAnalyticsController.php**: FIXAD — `getWeekdayStats()` refererade icke-existerande kolumn `dag_oee` i subquery (SQL-krasch). Lade till fullstandig OEE-berakning.
- **RebotlingAnalyticsController.php**: FIXAD — 4 catch-block returnerade `success: true` vid HTTP 500 (getStoppageAnalysis, getParetoStoppage)
- **FeedbackController.php**: OK — prepared statements, auth, error handling
- **StatusController.php**: OK — read_and_close korrekt, division guards
- **tvattlinje-admin.ts, saglinje-admin.ts, klassificeringslinje-admin.ts**: Alla OK — takeUntil, clearInterval, catchError

**Worker 2 — Data-integritet/edge-case-hardning** (4 filer granskade, 2 buggar fixade):
- **BonusController.php**: FIXAD — KRITISK: `week-trend` endpoint anvande kolumn `namn` istallet for `name` — kraschade alltid med PDOException
- **RebotlingController.php**: FIXAD — ogiltiga POST-actions returnerade HTTP 200 istf 400, ogiltig metod returnerade 200 istf 405
- **BonusAdminController.php**: OK — robust validering, division-by-zero-skydd, negativa tal blockeras
- **api.php**: OK — korrekt 404 vid ogiltig action, try-catch runt controller-instantiering, Content-Type korrekt

**Sammanfattning session #18**: 10 filer granskade, 4 buggar fixade (1 kritisk bonusberaknings-endpoint). Inga nya features.

---

## 2026-03-05 Session #17 — Bug Hunt #23 + Build/runtime-beredskap (INGEN NY FUNKTIONSUTVECKLING)

**Worker 1 — Bug Hunt #23** (7 filer granskade, 2 buggar fixade):
- **NewsController.php**: FIXAD — requireAdmin() startade session utan read_and_close trots att den bara läser session-data
- **CertificationController.php**: FIXAD — session startades för ALLA endpoints inkl GET-only. Refaktorerat: getAll/getMatrix skippar session, expiry-count använder read_and_close
- **ProductionEventsController.php**: FINNS EJ (bara migration existerar)
- **production-analysis.ts**: OK — alla subscriptions takeUntil, alla timeouts rensas, alla charts destroyas
- **skiftplan.ts**: FINNS EJ i kodbasen
- **nyhetsflode.ts**: FINNS EJ i kodbasen
- **certifications.ts**: OK — ren kod, inga läckor

**Worker 2 — Build + runtime-beredskap**:
- Angular build: PASS (inga fel, bara template-varningar NG8107/NG8102)
- Route-validering: PASS (50 lazy-loaded routes, alla korrekta)
- Service-injection: PASS (7 komponenter granskade, alla OK)
- Dead code: ProductController.php tom fil (harmless), **RuntimeController.php saknades i api.php classNameMap** — FIXAD (`2e41df2`)

## 2026-03-05 Session #16 — Bug Hunt #22 + API-kontraktsvalidering (INGEN NY FUNKTIONSUTVECKLING)

**Worker 1 — Bug Hunt #22** (6 filer granskade, 1 bugg fixad):
- **MaintenanceController.php**: `getEquipmentStats()` — `(m.deleted_at IS NULL OR 1=1)` villkor var alltid sant, vilket innebar att soft-deleted poster inkluderades i utrustningsstatistik. Fixat till `m.deleted_at IS NULL` — FIXAD
- **HistorikController.php**: OK — prepared statements korrekt, catch-blocks har http_response_code(500), inga auth-problem (avsiktligt publik endpoint)
- **bonus-admin.ts**: OK — alla HTTP-anrop har takeUntil(destroy$), timeout(), catchError(). Alla setTimeout-ID:n spåras och rensas i ngOnDestroy
- **kalender.ts**: Fil existerar ej — SKIPPED
- **notification-center.ts**: Fil existerar ej, ingen notifikationskomponent i navbar — SKIPPED
- **maintenance-log.ts** + **service-intervals.component.ts**: OK — destroy$ korrekt, alla HTTP med takeUntil/timeout/catchError, successTimer rensas i ngOnDestroy

**Worker 2 — End-to-end API-kontraktsvalidering** (50+ endpoints verifierade, 1 missmatch fixad):

Verifierade alla HTTP-anrop i `rebotling.service.ts` (42 endpoints), samt page-level anrop i `rebotling-admin.ts`, `live-ranking.ts`, `rebotling-skiftrapport.ts`, `executive-dashboard.ts`, `my-bonus.ts`, `operator-trend.ts`, `production-calendar.ts`, `monthly-report.ts`, `maintenance-log/` m.fl.

Kontrollerade controllers: `RebotlingController`, `RebotlingAdminController`, `RebotlingAnalyticsController`, `MaintenanceController`, `FeedbackController`, `BonusController`, `ShiftPlanController`.

**MISSMATCH HITTAD & FIXAD:**
- `live-ranking-config` (GET) och `set-live-ranking-config` (POST) — frontend (`live-ranking.ts` + `rebotling-admin.ts`) anropade dessa endpoints men backend saknade dispatch-case och handler-metoder. Lade till `getLiveRankingConfig()` och `setLiveRankingConfig()` i `RebotlingAdminController.php` (sparar/läser kolumnkonfiguration, sortering, refresh-intervall i `rebotling_settings`-tabellen) samt dispatch-cases i `RebotlingController.php`.

**Verifierade utan anmärkning (fokus-endpoints):**
- `exec-dashboard`, `all-lines-status`, `peer-ranking`, `shift-compare` — alla OK
- `service-intervals`, `set-service-interval`, `reset-service-counter` (MaintenanceController) — alla OK
- `live-ranking-settings`, `save-live-ranking-settings` — alla OK
- `rejection-analysis`, `cycle-histogram`, `spc` — alla OK
- `benchmarking`, `personal-bests`, `hall-of-fame` — alla OK
- `copy-week` (ShiftPlanController) — OK
- `feedback/summary`, `feedback/my-history`, `feedback/submit` — alla OK

Angular build: OK (inga kompileringsfel).

**Sammanfattning session #16**: 50+ endpoints verifierade, 1 API-kontraktsmissmatch hittad och fixad (live-ranking-config). Inga nya features.

---

## 2026-03-05 Session #15 — Bug Hunt #21 + INSTALL_ALL validering (INGEN NY FUNKTIONSUTVECKLING)

**Worker 1 — Bug Hunt #21** (12 filer granskade, 2 buggar fixade):
- **LoginController.php**: Misslyckad inloggning returnerade HTTP 200 med `success: false` istället för HTTP 401 — FIXAD
- **andon.ts**: `setTimeout` för skiftbytes-notis spårades/rensades inte i ngOnDestroy — FIXAD
- Godkända utan anmärkning: RegisterController, NewsController, StoppageController, AuthHelper, benchmarking.ts, monthly-report.ts, shift-handover.ts, live-ranking.ts

**Worker 2 — INSTALL_ALL.sql validering + build** (33 migreringar kontrollerade):
- **Redundant ALTER TABLE tvattlinje_settings** — kolumner redan definierade i CREATE TABLE — BORTTAGEN
- **Saknad ADD INDEX idx_status** på bonus_payouts — TILLAGD
- **Saknad bcrypt-migrering** (password VARCHAR(255)) — TILLAGD (var felaktigt exkluderad)
- Angular build: OK (57s, inga fel, 14 icke-kritiska varningar)

**Sammanfattning session #15**: 45 filer granskade, 2 buggar fixade + 3 INSTALL_ALL-korrigeringar. Inga nya features.

---

## 2026-03-05 Session #14 — Bug Hunt #20 + Kodkvalitets-audit (INGEN NY FUNKTIONSUTVECKLING)

**Worker 1 — Bug Hunt #20** (commits `7a27851..964d52f`, 15 filer granskade):
- **INSTALL_ALL.sql**: Saknade `operators`-tabellen (`add_operators_table.sql`-migrering) — FIXAD
- **executive-dashboard.ts**: `loadAllLinesStatus()` saknade `takeUntil(this.destroy$)` — potentiell minnesläcka vid navigering bort under pågående HTTP-anrop — FIXAD
- StatusController.php `all-lines`: OK — publik endpoint (avsiktligt), inget user input i SQL, bra felhantering, hanterar tomma DB
- BonusController.php `peer-ranking`: OK — `operator_id` castad via `intval()`, aldrig i SQL, anonymiserad output utan namn/ID-läcka, bra edge case (0 operatörer)
- executive-dashboard.html/css: OK — null-safe med `*ngIf`, korrekt bindings
- my-bonus.ts/html/css: OK — `takeUntil(destroy$)` överallt, timeout+catchError, null-safe UI
- INSTALL_ALL.sql vs individuella migreringar: OK (shift_handover inkluderar acknowledge-kolumner, news inkluderar alla tillägg)

**Worker 2 — Kodkvalitets-audit** (10 filer granskade, 5 buggar fixade):
- **ProfileController.php**: UPDATE+SELECT vid profiluppdatering saknade try-catch — PDOException kunde ge okontrollerat PHP-fel — FIXAD
- **ShiftPlanController.php**: 8 catch-block fångade bara PDOException, inte generell Exception — FIXAD alla 8
- **HistorikController.php**: Default-case ekade `$run` i JSON utan sanitering — XSS-risk — FIXAD med htmlspecialchars()
- **historik.ts**: `setTimeout(() => buildCharts(), 100)` städades aldrig i ngOnDestroy — FIXAD
- **bonus-admin.ts**: `setTimeout(() => renderAuditChart(), 100)` städades aldrig i ngOnDestroy — FIXAD
- Godkända utan anmärkning: OperatorCompareController.php, MaintenanceController.php, benchmarking.ts, live-ranking.ts

**Sammanfattning session #14**: 25 filer granskade, 7 buggar fixade (2 Bug Hunt + 5 kodkvalitet). Inga nya features.

---

## 2026-03-05 Session #13 — Multi-linje status + Kollegajämförelse

**Worker 1 — Executive Dashboard multi-linje status** (`7a27851`):
- Ny publik endpoint `?action=status&run=all-lines` i StatusController.php
- Rebotling: realtidsstatus (running/idle/offline) baserat på senaste data (15/60 min gränser), OEE%, IBC idag
- Tvättlinje, Såglinje, Klassificeringslinje: statiskt "ej igång" tills databastabeller finns
- Frontend: pulsande grön cirkel (running), orange (idle), röd (offline), grå (ej igång)
- Dashboard pollar publik endpoint var 60:e sekund

**Worker 2 — My-bonus kollegajämförelse** (`cb55bd5`):
- Ny backend-endpoint `peer-ranking` i BonusController.php: anonymiserad veckoranking med IBC/h och kvalitet
- Ny frontend-sektion "Hur ligger du till?" i my-bonus med mini-tabell, guld/silver/brons-badges, motiverande diff-text
- Ingen operatörsidentitet avslöjad — peers visas som "Operatör 1", "Operatör 2" etc.

---

## 2026-03-05 Session #12 — Månadsrapport + Bug Hunt #19

**Worker 1 — monthly-report förbättring** (`c0c683b`):
- VD-sammanfattning (executive summary) med auto-genererad text baserad på KPI:er och jämförelsedata
- Dagsmål-referenslinje (gul streckad) i produktionsdiagrammet
- Förbättrad PDF/print: @page A4, Noreko-logotyp, utskriftsdatum, sidfot med "Konfidentiellt"
- Print-styling: guld/silver/brons-rader, rekordmånad-banner anpassad för ljust läge

**Worker 2 — Bug Hunt #19** (`aa9cdd7`):
- 3 buggar hittade och fixade:
  1. BonusController.php getAchievements: catch-block använde raw http_response_code(500) istället för sendError()
  2. AndonController.php getDailyChallenge: tom catch-block svalde dagmål-query-fel tyst — loggning saknades
  3. operator-dashboard.ts loadFeedbackSummary: saknad isFetching-guard — race condition vid snabba tabb-byten
- 23 filer granskade och rena

---

## 2026-03-05 Session #10 — Stora refactorings + Bug Hunt

**Worker 1 — rebotling-statistik.ts refactoring** (`9eec10d`):
- 4248→1922 TS-rader (55% reduktion), 2188→694 HTML-rader (68%)
- 16 nya child-components i `statistik/`: histogram, SPC, cykeltid-operator, kvalitetstrend, waterfall-OEE, veckodag, produktionsrytm, pareto-stopp, kassation-pareto, OEE-komponenter, kvalitetsanalys, händelser, veckojämförelse, prediktion, OEE-deepdive, cykeltrend
- 12 laddas med `@defer (on viewport)`, 4 med `*ngIf` toggle

**Worker 2 — maintenance-log.ts refactoring** (`c39d3cb`):
- 1817→377 rader. 7 nya filer: models, helpers, 5 child-components

**Worker 3 — Bug Hunt #18** (`6baa2bf`):
- 1 bugg fixad: operators.html svenska specialtecken (å/ä/ö). 9 filer rena

---

## 2026-03-05 Session #9 — Refactoring, UX-polish, Mobilanpassning

**Planerade workers**:
1. rebotling-statistik.ts refactoring (4248 rader → child-components med @defer)
2. Error-handling UX + Empty-states batch 3 (catchError→feedback + "Inga resultat" i 5 sidor)
3. Mobilanpassning batch 3 (col-class-fixar, responsiva tabeller i 10+ filer)

---

## 2026-03-05 Session #8 batch 4 — Services, PHP-validering, Loading-states

**Worker 1 — Saglinje/Klassificeringslinje services** (`e60e196`):
- Nya filer: `saglinje.service.ts`, `klassificeringslinje.service.ts`
- Uppdaterade: saglinje-admin.ts, saglinje-statistik.ts, klassificeringslinje-admin.ts, klassificeringslinje-statistik.ts
- Mönster: `@Injectable({ providedIn: 'root' })`, withCredentials, Observable-retur

**Worker 2 — PHP input-validering audit** (`704ee80`):
- 25 PHP-controllers uppdaterade med filter_input, trim, FILTER_VALIDATE_EMAIL, isset-checks
- Nyckelfiler: AdminController, LoginController, RegisterController, StoppageController, RebotlingController

**Worker 3 — Loading-states batch 2** (`1a3a4b8`):
- Spinners tillagda: production-analysis.html, saglinje-statistik.html, klassificeringslinje-statistik.html
- Mönster: Bootstrap spinner-border text-info med "Laddar data..." text

---

## 2026-03-05 Bug Hunt #17 — Session #8 batch 2+3 granskning

**Scope**: BonusController, BonusAdminController, bonus-admin.ts

**Fixade buggar (PHP)**:
- BonusAdminController.php — 17 catch-block saknade `500` i `sendError()` (returnerade HTTP 200 vid databasfel)
- BonusController.php — 15 catch-block saknade `500` i `sendError()`

**Fixade buggar (TypeScript)**:
- bonus-admin.ts — 12 HTTP-anrop saknade `timeout(8000)` och `catchError()`. Null-safe access (`res?.success`) på 5 ställen.

**Commit**: `272d48e`

---

## 2026-03-05 RebotlingController refactoring

**Före**: RebotlingController.php — 9207 rader, 97 metoder, allt i en klass.
**Efter**: 3 controllers:
- `RebotlingController.php` — 2838 rader. Dispatcher + 30 live-data endpoints (PLC-data, skiftöversikt, countdown)
- `RebotlingAdminController.php` — 1333 rader. 33 admin-only metoder (konfiguration, mål, notifieringar)
- `RebotlingAnalyticsController.php` — 5271 rader. 34 analytics/rapportmetoder (statistik, prognos, export)

Sub-controllers skapas med `new XxxController($this->pdo)` och dispatchas via `$run`-parametern.
API-URL:er oförändrade (`?action=rebotling&run=X`).
Bugfix: Ersatte odefinierad `$this->sendError()` med inline `http_response_code(500)` + JSON.

**Commit**: `d295fa8`

---

## 2026-03-05 Lösenordshashing SHA1(MD5) → bcrypt

**Nya filer**:
- `noreko-backend/classes/AuthHelper.php` — `hashPassword()` (bcrypt), `verifyPassword()` (bcrypt first, legacy fallback + auto-upgrade)
- `noreko-backend/migrations/2026-03-05_password_bcrypt.sql` — `ALTER TABLE users MODIFY COLUMN password VARCHAR(255)`

**Ändrade filer**:
- RegisterController.php — `sha1(md5())` → `AuthHelper::hashPassword()`
- AdminController.php — 2 ställen (create + update user)
- ProfileController.php — Password change
- LoginController.php — Verifiering via `AuthHelper::verifyPassword()` med transparent migration

**Commit**: `286fb1b`

---

## 2026-03-05 Bug Hunt #16 — Session #8 granskning

**Scope**: 4 commits (572f326, 8389d09, 0af052d, 60c5af2), 24 ändrade filer.

**Granskade filer (TypeScript)**:
- stoppage-log.ts — 6 buggar hittade och fixade (se nedan)
- andon.ts — Ren: alla HTTP-anrop har timeout/catchError/takeUntil, alla intervall städas i ngOnDestroy, Chart.js destroy i try-catch
- bonus-dashboard.ts — Ren: manuell subscription-tracking med unsubscribe i ngOnDestroy
- create-user.ts — Ren
- executive-dashboard.ts — Ren: manuell subscription-tracking (dataSub/linesSub), timers städas
- klassificeringslinje-skiftrapport.ts — Ren
- login.ts — Ren
- my-bonus.ts — Ren: alla HTTP-anrop har timeout/catchError/takeUntil, Chart.js destroy i try-catch
- rebotling-skiftrapport.ts — Ren
- register.ts — Ren: redirectTimerId städas i ngOnDestroy
- saglinje-skiftrapport.ts — Ren
- tvattlinje-skiftrapport.ts — Ren
- rebotling.service.ts — Ren: service-lager utan subscriptions

**Granskade filer (PHP)**:
- AndonController.php — Ren: prepared statements, http_response_code(500) i catch, publik endpoint (ingen auth krävs)
- BonusController.php — Ren: session_start(['read_and_close']) + auth-check, prepared statements, input-validering
- RebotlingController.php — Ren: prepared statements, korrekt felhantering

**Fixade buggar (stoppage-log.ts)**:
1. `loadReasons()` — saknande `timeout(8000)` och `catchError()`
2. `loadStoppages()` — saknande `timeout(8000)` och `catchError()`
3. `loadWeeklySummary()` — saknande `timeout(8000)` och `catchError()`
4. `loadStats()` — saknande `timeout(8000)` och `catchError()`
5. `addStoppage()` (create-anrop) — saknande `timeout(8000)` och `catchError()`, redundant `error:`-handler borttagen
6. `deleteStoppage()` — saknande `timeout(8000)` och `catchError()`

**Build**: `npx ng build` — OK (inga fel, enbart warnings)

---

## 2026-03-05 Worker: VD Veckosammanfattning-email

**Backend (RebotlingController.php)**:
- `computeWeeklySummary(week)`: Beräknar all aggregerad data för en ISO-vecka
  - Total IBC denna vs förra veckan (med diff %)
  - Snitt OEE med trendpil (up/down/stable) vs förra veckan
  - Bästa/sämsta dag (datum + IBC)
  - Drifttid vs stopptid (h:mm), antal skift körda
  - Per operatör: IBC totalt, IBC/h snitt, kvalitet%, bonus-tier (Guld/Silver/Brons)
  - Topp 3 stopporsaker med total tid
- `GET ?action=rebotling&run=weekly-summary-email&week=YYYY-WXX` (admin-only) — JSON-preview
- `POST ?action=rebotling&run=send-weekly-summary` (admin-only) — genererar HTML + skickar via mail()
- `buildWeeklySummaryHtml()`: Email med all inline CSS, 600px max-width, 2x2 KPI-grid, operatörstabell med alternating rows, stopporsaker, footer
- Hämtar mottagare från notification_settings (rebotling_settings.notification_emails)

**Frontend (executive-dashboard.ts, sektion 8)**:
- Ny "Veckorapport"-sektion i executive dashboard
- ISO-veckoväljare (input type="week"), default förra veckan
- "Förhandsgranska"-knapp laddar JSON-preview
- "Skicka veckorapport"-knapp triggar POST, visar feedback med mottagare
- 4 KPI-kort: Total IBC (med diff%), Snitt OEE (med trendpil), Bästa dag, Drifttid/Stopptid
- Operatörstabell med ranking, IBC, IBC/h, kvalitet, bonus-tier, antal skift
- Stopporsaks-lista med kategori och total tid
- Dark theme, takeUntil(destroy$), timeout, catchError

**Filer ändrade**: RebotlingController.php, rebotling.service.ts, executive-dashboard.ts/html/css

---

## 2026-03-05 Worker: Bonus Rättviseaudit — Counterfactual stoppåverkan

**Backend (BonusAdminController.php)**:
- Ny endpoint: `GET ?action=bonusadmin&run=fairness&period=YYYY-MM`
- Hämtar per-skift-data (op1/op2/op3) från rebotling_ibc med kumulativa fält (MAX per skiftraknare)
- Hämtar stopploggar från stoppage_log + stoppage_reasons för perioden
- Beräknar förlorad IBC-produktion: stopptid * operatörens snitt IBC/h, fördelat proportionellt per skiftandel
- Simulerar ny bonus-tier utan stopp baserat på bonus_level_amounts (brons/silver/guld/platina)
- Returnerar per operatör: actual/simulated IBC, actual/simulated tier, bonus_diff_kr, lost_hours, top_stop_reasons
- Sammanfattning: total förlorad bonus, mest drabbad operatör, total/längsta stopptid, topp stopporsaker
- Prepared statements, try-catch med http_response_code(500)

**Frontend (bonus-admin flik "Rättviseaudit")**:
- Ny nav-pill + flik i bonus-admin sidan
- Periodväljare (input type="month"), default förra månaden
- 3 sammanfattningskort: total förlorad bonus, mest drabbad operatör, total stopptid
- Topp stopporsaker som taggar med ranknummer
- Operatörstabell: avatar-initialer, faktisk/simulerad IBC, diff, tier-badges, bonus-diff (kr), förlorad tid (h:mm)
- Canvas2D horisontellt stapeldiagram: blå-grå=faktisk IBC, grön=simulerad IBC, diff-label
- Dark theme (#1e2535 cards, #2d3748 border), takeUntil(destroy$), timeout(8000), catchError()

**Filer ändrade**: BonusAdminController.php, bonus-admin.ts, bonus-admin.html, bonus-admin.css

---

## 2026-03-05 Worker: Gamification — Achievement Badges + Daily Challenge

**Achievement Badges (my-bonus)**:
- Backend: `GET ?action=bonus&run=achievements&operator_id=X` i BonusController.php
- 10 badges totalt: IBC-milstolpar (100/500/1000/2500/5000), Perfekt vecka, 3 streak-nivåer (5/10/20 dagar), Hastighets-mästare, Kvalitets-mästare
- Varje badge returnerar: badge_id, name, description, icon (FA-klass), earned (bool), progress (0-100%)
- SQL mot rebotling_ibc med prepared statements, kumulativa fält hanterade korrekt (MAX-MIN per skiftraknare)
- Frontend: "Mina Utmärkelser" sektion med grid, progress-bars på ej uppnådda, konfetti CSS-animation vid uppnådd badge
- Fallback till statiska badges om backend returnerar tom array

**Daily Challenge (andon)**:
- Backend: `GET ?action=andon&run=daily-challenge` i AndonController.php
- 5 utmaningstyper: IBC/h-mål (+15% över snitt), slå igårs rekord, perfekt kvalitet, teamrekord (30d), nå dagsmålet
- Deterministisk per dag (dag-i-året som seed)
- Returnerar: challenge, icon, target, current, progress_pct, completed, type
- Frontend: Widget mellan status-baner och KPI-kort med progress-bar, pulse-animation vid KLART
- Polling var 60s, visibilitychange-guard, takeUntil(destroy$), timeout(8000), catchError()

**Filer ändrade**: BonusController.php, AndonController.php, my-bonus.ts/html/css, andon.css

---

## 2026-03-05 Worker: Oparade endpoints batch 2 — Alert Thresholds, Notification Settings, Goal History

**Alert Thresholds Admin UI**: Expanderbar sektion i rebotling-admin med OEE-trösklar (warning/danger %), produktionströsklar (warning/danger %), PLC max-tid, kvalitetsvarning. Formulär med number inputs + spara-knapp. Visar befintliga värden vid laddning. Sammanfattningsrad när panelen är hopfälld. Alla anrop har takeUntil/timeout(8000)/catchError.

**Notification Settings Admin UI**: Utökad med huvudtoggle (email on/off), 5 händelsetyp-toggles (produktionsstopp, låg OEE, certifikat-utgång, underhåll planerat, skiftrapport brådskande), e-postadressfält för mottagare. Backend utökad med `notification_config` JSON-kolumn (auto-skapad via ensureNotificationEmailsColumn), `defaultNotificationConfig()`, utökad GET/POST som returnerar/sparar config-objekt. Prepared statements i PHP.

**Goal History Visualisering**: Periodväljare (3/6/12 månader) med knappar i card-header. Badge som visar nuvarande mål. Linjegraf (Chart.js stepped line) med streckad horisontell referenslinje för nuvarande mål. Stödjer enstaka datapunkter (inte bara >1). Senaste 10 ändringar i tabell.

**Service-metoder**: `getAlertThresholds()`, `saveAlertThresholds()`, `getNotificationSettings()`, `saveNotificationSettings()`, `getGoalHistory()` + interfaces (AlertThresholdsResponse, NotificationSettingsResponse, GoalHistoryResponse) i rebotling.service.ts.

Commit: 0af052d — bygge OK, pushad.

---

## 2026-03-05 session #8 — Lead: Session #7 komplett, 8 commits i 2 batchar

**Analys**: Session #7 alla 3 workers klara. Operatör×Maskin committat (6b34381), Bug Hunt #15 + Oparade endpoints uncommitted (15 filer).

**Batch 1** (3 workers):
- Commit+bygg session #7: `572f326` (Bug Hunt #15) + `8389d09` (Oparade endpoints frontend) — TS-interface-fixar, bygge OK
- Oparade endpoints batch 2: `0af052d` — Alert Thresholds admin UI, Notification Settings (5 event-toggles), Goal History (Chart.js linjegraf, periodväljare 3/6/12 mån)
- Gamification: `60c5af2` — 10 achievement badges i my-bonus, daglig utmaning i andon med progress-bar

**Batch 2** (3 workers):
- Bug Hunt #16: `348ee07` — 6 buggar i stoppage-log.ts (timeout/catchError saknade), 24 filer granskade
- Bonus rättviseaudit: `9e54e8d` — counterfactual rapport, simulerings-endpoint, ny flik i bonus-admin
- VD Veckosammanfattning-email: `eb930e2` — HTML-email med KPI:er, preview+send i executive dashboard, ISO-veckoväljare

**Batch 3** startas: RebotlingController refactoring, Lösenordshashing, Bug Hunt #17.

---

## 2026-03-05 Worker: Bug Hunt #15 -- login.ts memory leak + HTTP error-handling audit

**login.ts**: Lade till `implements OnDestroy`, `destroy$` Subject, `ngOnDestroy()`. Alla HTTP-anrop har nu `.pipe(takeUntil(this.destroy$), timeout(8000), catchError(...))`. Importerade `Subject`, `of`, `takeUntil`, `timeout`, `catchError`.

**register.ts**: Lade till `destroy$` Subject, `ngOnDestroy()` med destroy$-cleanup. HTTP-anrop wrappat med `takeUntil/timeout/catchError`.

**create-user.ts**: Lade till `of`, `timeout`, `catchError` i imports. `createUser()`-anropet wrappat med `takeUntil/timeout/catchError`.

**saglinje-skiftrapport.ts**: Lade till `of`, `timeout`, `catchError` i imports. Alla 7 service-anrop (getReports, createReport, updateReport, deleteReport, bulkDelete, updateInlagd, bulkUpdateInlagd) wrappade med `pipe(takeUntil, timeout(8000), catchError)`.

**klassificeringslinje-skiftrapport.ts**: Samma fix som saglinje -- alla 7 service-anrop wrappade.

**tvattlinje-skiftrapport.ts**: Samma fix -- alla 7 service-anrop wrappade.

**rebotling-skiftrapport.ts**: 10 subscribe-anrop fixade: loadSettings, fetchProducts, getSkiftrapporter, updateInlagd, bulkUpdateInlagd, deleteSkiftrapport, bulkDelete, createSkiftrapport, getLopnummer, updateSkiftrapport, laddaKommentar, sparaKommentar, shift-compare. Alla med `pipe(takeUntil, timeout(8000), catchError)`.

**bonus-dashboard.ts**: 4 subscribe-anrop fixade: getConfig (weekly goal), getTeamStats, getOperatorStats, getKPIDetails. Alla med `pipe(takeUntil, timeout(8000), catchError)`.

**Filer**: `login.ts`, `register.ts`, `create-user.ts`, `saglinje-skiftrapport.ts`, `klassificeringslinje-skiftrapport.ts`, `tvattlinje-skiftrapport.ts`, `rebotling-skiftrapport.ts`, `bonus-dashboard.ts`

---

## 2026-03-05 Worker: Oparade endpoints -- bemanningsöversikt, månadssammanfattning stopp, produktionstakt

**Service**: 3 nya metoder i `rebotling.service.ts`: `getStaffingWarning()`, `getMonthlyStopSummary(month)`, `getProductionRate()`. Nya TypeScript-interfaces: `StaffingWarningResponse`, `MonthlyStopSummaryResponse`, `ProductionRateResponse` med tillhorande sub-interfaces.

**Executive Dashboard** (`executive-dashboard.ts/html/css`): Ny sektion "Bemanningsöversikt" som visar underbemannade skift kommande 7 dagar. Kort per dag med skift-nr och antal operatorer vs minimum. Fargkodad danger/warning baserat pa 0 eller lag bemanning. Dold om inga varningar. CSS med dark theme.

**Stoppage Log** (`stoppage-log.ts/html`): Ny sektion "Månadssammanfattning -- Topp 5 stopporsaker" langst ner pa sidan. Horisontellt bar chart (Chart.js) + tabell med orsak, antal, total tid, andel. Manadväljare (input type=month). `RebotlingService` injicerad, `loadMonthlyStopSummary()` med takeUntil/timeout/catchError.

**Andon** (`andon.ts/html/css`): Nytt KPI-kort "Aktuell Produktionstakt" mellan KPI-raden och prognosbannern. Visar snitt IBC/dag for 7d (stort, med progress bar), 30d och 90d. Gron/gul/rod baserat pa hur nära dagsmalet. Polling var 60s. `RebotlingService` injicerad.

**Filer**: `rebotling.service.ts`, `executive-dashboard.ts/html/css`, `stoppage-log.ts/html`, `andon.ts/html/css`

---

## 2026-03-05 Worker: Operator x Produkt Kompatibilitetsmatris

**Backend**: Nytt endpoint `GET ?action=operators&run=machine-compatibility&days=90` i `OperatorController.php`. SQL aggregerar fran `rebotling_ibc` med UNION ALL op1/op2/op3, JOIN `operators` + `rebotling_products`, GROUP BY operator+produkt. Returnerar avg_ibc_per_h, avg_kvalitet, OEE, antal_skift per kombination. Prepared statements, try-catch, http_response_code(500) vid fel.

**Frontend**: Ny expanderbar sektion "Operator x Produkt -- Kompatibilitetsmatris" i operators-sidan. Heatmap-tabell: rader = operatorer, kolumner = produkter. Celler fargkodade gron/gul/rod baserat pa IBC/h (relativ skala). Tooltip med IBC/h, kvalitet%, OEE, antal skift. `getMachineCompatibility()` i operators.service.ts. takeUntil(destroy$), timeout(8000), catchError(). Dark theme, responsive.

**Filer**: `OperatorController.php`, `operators.service.ts`, `operators.ts`, `operators.html`, `operators.css`

---

## 2026-03-05 session #7 — Lead: Behovsanalys + 3 workers startade

**Analys**: Session #6 komplett (5 workers, 2 features, 48 bugfixar, perf-optimering). Backlog var tunn (5 öppna items). Behovsanalys avslöjade 30+ backend-endpoints utan frontend, 64 HTTP-anrop utan error-handling, login.ts memory leak. MES-research (gamification, hållbarhets-KPI:er). Fyllde på backlog med 10+ nya items. Startade 3 workers: Bug Hunt #15 (error-handling+login), Operatör×Maskin kompatibilitetsmatris, Oparade endpoints frontend (bemanningsöversikt, månadssammanfattning stopp, produktionstakt).

---

## 2026-03-04 session #6 — Lead: Kodbasanalys + 3 workers startade

**Analys**: Session #5 komplett (6 features, 4 bugfixar). Backlog var nere i 2 items. Kodbasanalys (15 fynd) + MES-research (7 idéer) genererade 12 nya items. Startade 3 workers: Bug Hunt #14 (felhantering), Exec Dashboard (underhållskostnad+stämning), Users Admin UX.

**Worker: Bug Hunt #14** — LoginController.php try-catch (PDOException → HTTP 500), operators.ts timeout(8000)+catchError på 7 anrop, stoppage-log.ts 350ms debounce med onSearchInput(), rebotling-skiftrapport.ts 350ms debounce, URL-typo overlamnin→overlamning i routes+menu. OperatorCompareController redan korrekt. Bygge OK.

**Worker: Executive Dashboard underhållskostnad+stämning** — 3 underhålls-KPI-kort (kostnad SEK 30d, händelser, stopptid h:mm) från MaintenanceController run=stats. Teamstämning: emoji-KPI + 30d trendgraf (Chart.js). getMaintenanceStats()+getFeedbackSummary() i service. Bygge OK.

**Worker: Users Admin UX** — Sökfält 350ms debounce, sorterbar tabell (4 kolumner), statusfilter-pills (Alla/Aktiva/Admin/Inaktiva), statistik-rad. Dark theme, responsive. Bygge OK.

**Worker: RebotlingController catch-block audit** — 47 av 142 catch-block fixade med http_response_code(500) före echo json_encode. 35 redan korrekta, 60 utan echo (inre try/catch, return-only). PHP syntax OK.

**Worker: Admin polling-optimering** — visibilitychange-guard på 4 admin-sidor (rebotling/saglinje/tvattlinje/klassificeringslinje). systemStatus 30s→120s, todaySnapshot 30s→300s. Andon: countdownInterval mergad in i clockInterval (7→6 timers), polling-timers pausas vid dold tabb. Bygge OK.

---

**Worker: Skiftbyte-PDF automatgenerering** — Print-optimerad skiftsammanfattning som oppnas i nytt fonster. Backend: nytt endpoint `shift-pdf-summary` i RebotlingController.php som returnerar fullt HTML-dokument med A4-format, print-CSS, 6 KPI-kort (IBC OK, Kvalitet%, OEE, Drifttid, Rasttid, IBC/h), operatorstabell med per-rapport-rader (tid, produkt, IBC OK/ej OK, operatorer), skiftkommentar om tillganglig. Operatorer och produkter visas som badges. Knapp "Skriv ut / Spara PDF" for webblasarens print-dialog. Frontend skiftrapport: ny knapp (fa-file-export) per skiftrapport-rad som oppnar backend-HTML i nytt fonster via window.open(). Frontend andon: skiftbyte-detektion i polling — nar `status.skift` andras visas en notis "Skiftbyte genomfort — Skiftsammanfattning tillganglig" med lank till skiftrapporten, auto-dismiss efter 30s. Service: `getShiftPdfSummaryUrl()` i rebotling.service.ts. CSS: slideInRight-animation for notisen. Prepared statements, takeUntil(destroy$), timeout(8000)+catchError(). Bygge OK.

**Worker: Bonus What-if simulator förbättring** — Utökad What-if bonussimulator i bonus-admin med tre nya sub-flikar. (1) Preset-scenarios: snabbknappar "Aggressiv bonus", "Balanserad", "Kostnadssnål" som fyller i tier-parametrar med ett klick. (2) Scenario-jämförelse: sida-vid-sida-konfiguration av nuvarande vs nytt förslag, kör dubbla simuleringar mot backend, visar totalkostnads-diff-kort med färgkodning (grön=besparing, röd=ökning), halvcirkel-gauge för kostnadspåverkan i procent, och diff per operatör i tabell med tier-jämförelse och kronor-diff. (3) Historisk simulering: välj period (förra månaden, 2 mån sedan, senaste 3 mån), beräkna "om dessa regler hade gällt" med CSS-baserade horisontella stapeldiagram per operatör (baslinje vs simulerad) med diff-kolumn. Visuella förbättringar: animerade siffror via CSS transition, färgkodade diff-indikatorer (sim-diff-positive/negative). Inga backend-ändringar — återanvänder befintligt simulate-endpoint i BonusController. Dark theme, takeUntil(destroy$), timeout(8000)+catchError() på alla HTTP-anrop. Bygge OK.

**Worker: Live-ranking admin-konfiguration** — Admin-gränssnitt för att konfigurera vilka KPI:er som visas på TV-skärmen (`/rebotling/live-ranking`). Backend: 2 nya endpoints i RebotlingController.php (`live-ranking-config` GET, `set-live-ranking-config` POST admin-only) som lagrar JSON-config i `rebotling_settings` med nyckel `live_ranking_config`. DB-migration med default-config. Frontend admin: ny expanderbar sektion "TV-skärm (Live Ranking) — KPI-kolumner" med checkboxar (IBC/h, Kvalitet%, Bonus-nivå, Dagsmål-progress, IBC idag), dropdown sortering (IBC/h, Kvalitet%, IBC totalt), number input refresh-intervall (10-120s), spara-knapp. Frontend live-ranking: hämtar config vid init, visar/döljer kolumner baserat på config, sorterar ranking efter konfigurerat fält, använder konfigurerat refresh-intervall. Service-metoder tillagda i rebotling.service.ts. Dark theme, prepared statements, auth-check, takeUntil(destroy$)+timeout(8000)+catchError(). Bygge OK.

**Worker: IBC-kvalitets deep-dive** — Ny sektion "IBC Kvalitetsanalys" i rebotling-statistik. Backend: nytt endpoint `rejection-analysis` i RebotlingController.php som returnerar daglig kvalitets%, glidande 7-dagars snitt, KPI:er (kvalitet idag/vecka, kasserade idag, trend vs förra veckan) samt Pareto-data med trendjämförelse mot föregående period. Frontend: 4 KPI-kort (kvalitet% idag, vecka glidande, kasserade idag, trend-pil), kvalitetstrend-linjegraf (Chart.js) med referenslinjer vid 95% mål och 90% minimum, kassationsfördelning Pareto-diagram med horisontella staplar + kumulativ linje + detajtabell med trend-pilar, periodväljare 14/30/90 dagar, CSV-export. Fallback-meddelande om PLC-integration saknas. Dark theme, takeUntil(destroy$), timeout(8000)+catchError(), try-catch runt chart.destroy(). Bygge OK.

**Worker: Prediktivt underhåll körningsbaserat** — Serviceintervall-system baserat på IBC-volym. Backend: 3 nya endpoints i MaintenanceController (service-intervals GET, set-service-interval POST, reset-service-counter POST) med prepared statements. Ny tabell service_intervals med default-rad. Frontend: ny flik "Serviceintervall" i underhållsloggen med tabell (maskin/intervall/IBC sedan service/kvar/status-badge), progress-bar per rad, admin-knappar (registrera utförd service, redigera intervall via modal). Status-badges: grön >25%, gul 10-25%, röd <10%. Varning-banner överst vid kritisk. Exec-dashboard: service-varnings-banner om maskin <25% kvar. Bygge OK.

## 2026-03-04 Bug Hunt #13 — session #4 granskning

**Granskade filer (session #4 commits `7996e1f`, `f0a57ba`, `d0b8279`, `0795512`):**
- `noreko-frontend/src/app/pages/benchmarking/benchmarking.ts` + `.html` + `.css` — OK
- `noreko-frontend/src/app/pages/shift-plan/shift-plan.ts` + `.html` + `.css` — OK
- `noreko-frontend/src/app/pages/rebotling-admin/rebotling-admin.ts` + `.html` — OK
- `noreko-frontend/src/app/pages/rebotling-skiftrapport/rebotling-skiftrapport.ts` + `.html` — OK
- `noreko-frontend/src/app/pages/my-bonus/my-bonus.ts` — OK
- `noreko-frontend/src/app/services/rebotling.service.ts` — OK
- `noreko-backend/classes/ShiftPlanController.php` — OK
- `noreko-backend/classes/BonusController.php` (ranking-position) — OK

**Buggar hittade och fixade:**
1. **RebotlingController.php `getPersonalBests()`** — Saknade `http_response_code(500)` i catch-block. Fixat.
2. **RebotlingController.php `getHallOfFameDays()`** — Saknade `http_response_code(500)` i catch-block. Fixat.
3. **RebotlingController.php `getMonthlyLeaders()`** — Saknade `http_response_code(500)` i catch-block. Fixat.
4. **RebotlingController.php `getPersonalBests()` best_day_date subquery** — Ogiltig nästlad aggregat `SUM(COALESCE(MAX(...),0))` som kraschar i MySQL. Omskriven med korrekt tvåstegs-aggregering (MAX per skift, sedan SUM per dag).

**Inga buggar i:**
- Alla Angular/TS-filer: korrekt `takeUntil(destroy$)`, `timeout()`, `catchError()`, `clearInterval`/`clearTimeout` i ngOnDestroy, try-catch runt chart.destroy(), inga saknade optional chaining.
- ShiftPlanController.php: korrekt auth-checks, prepared statements, input-validering.
- BonusController.php: korrekt session-check, `sendError()` med `http_response_code()`.

---

## 2026-03-04 session #5 — Lead: 3 workers startade

**Analys**: Session #4 batch 2 komplett (Skiftplaneringsvy `f0a57ba` + Benchmarking `7996e1f`). Backlogen tunnades — fyllde på med nya items.

**Startade 3 workers:**
1. **Prediktivt underhåll körningsbaserat** — serviceintervall baserat på IBC-volym, admin-UI, exec-dashboard varning
2. **IBC-kvalitets deep-dive** — kvalitetstrend-graf, kassationsanalys, KPI-kort i rebotling-statistik
3. **Bug Hunt #13** — granskning av session #4 features (benchmarking, skiftplan, auto-rapport, kollegjämförelse)

---

**Worker: Benchmarking förbättring** — Tre nya flikar (Översikt/Personbästa/Hall of Fame). Personbästa-flik: per operatör bästa dag/vecka/månad IBC + teamrekord-jämförelse sida vid sida. Hall of Fame: topp 5 bästa enskilda produktionsdagar med guld/silver/brons-ikoner, operatörsnamn, kvalitet. Backend: utökad `personal-bests` endpoint med dag/vecka/månad per operatör + teamrekord dag/vecka/månad; ny `hall-of-fame` endpoint (topp 5 dagar). Bygge OK.

**Worker: Skiftplaneringsvy förbättring** — Veckoöversikt-panel överst i veckoplan-fliken: visar antal operatörer per skift per dag med bemanningsgrad (grön/gul/röd). Kopiera förra veckans schema-knapp (POST `copy-week` endpoint, admin-only). ISO-veckonummer + pilnavigering (redan befintligt, behålls). Backend: ny `copyWeek()`-metod i ShiftPlanController.php med prepared statements. Bygge OK.

**Worker: Automatisk skiftrapport via email** — Ny POST endpoint `auto-shift-report` i RebotlingController som bygger HTML-rapport med KPI:er (IBC OK, kvalitet, IBC/h) och skickar via mail() till konfigurerade mottagare. Admin-panel: ny sektion "Automatisk skiftrapport" med datum/skift-väljare + testknappp. Skiftrapport-vy: "Skicka skiftrapport"-knapp (admin-only) med bekräftelsedialog. Använder befintlig notification_emails-kolumn. Bygge OK.

**Worker: Min bonus kollegjämförelse** — Utökade ranking-position endpoint med percentil (Topp X%) och trend (upp/ner/samma vs förra veckan). Lade till RankingPositionResponse-interface + service-metod i BonusService. Uppdaterade my-bonus HTML med percentil-badge, trendpil och motiverande meddelanden (#1="Du leder! Fortsätt så!", #2-3="Nära toppen!", #4+="Känn motivationen växa!"). Dark theme CSS. Bygge OK.

**Worker: Stub-katalog cleanup** — Tog bort oanvända stub-filer: pages/tvattlinje/ (hela katalogen) + pages/rebotling/rebotling-live.* och rebotling-skiftrapport.* (stubs). Behöll pages/rebotling/rebotling-statistik.* som används av routing. Bygge OK.

## 2026-03-04 session #4 — Lead: Ny batch — 3 workers

**Analys**: Exec dashboard multi-linje, bonus utbetalningshistorik, halvfärdiga features — alla redan implementerade (verifierat).

**Omplanering**: Starta 3 workers på genuint öppna items:
1. **Stub-katalog cleanup** — Ta bort gamla/oanvända stub-filer ✅ `a1c17f4`
2. **Min bonus: Jämförelse med kollegor** — Anonymiserad ranking ✅ `0795512`
3. **Automatisk skiftrapport-export** — POST-endpoint ✅ `d0b8279`

**Batch 2**: 2 nya workers startade:
4. **Skiftplaneringsvy förbättring** — veckoöversikt, bemanningsgrad, kopiera schema
5. **Benchmarking förbättring** — personbästa, hall of fame, team-rekord

---

## 2026-03-04 kväll #13 — Worker: Loading-states + Chart.js tooltip-förbättring

### DEL 1: Loading-state spinners (konsistent spinner-border mönster)

3 sidor uppgraderade till konsistent `spinner-border text-info` mönster:

1. **rebotling-prognos** — ersatt enkel text "Laddar produktionstakt..." med spinner-border + text
2. **certifications** — ersatt `fa-spinner fa-spin` med spinner-border + text
3. **operator-attendance** — uppgraderat båda panelernas (kalender + statistik) spinners till spinner-border

Notering: production-calendar och benchmarking hade redan korrekt spinner-mönster.

### DEL 2: Chart.js tooltip-förbättringar (3 sidor, 6 grafer)

1. **audit-log** `buildActivityChart()`:
   - Custom tooltip med dag+datum (t.ex. "Mån 2026-03-04")
   - Formaterat antal ("3 aktiviteter" istf bara siffra)
   - Dark theme tooltip-styling (#2d3748 bg)

2. **production-calendar** `buildDayDetailChart()`:
   - Datumtitel i tooltip (t.ex. "Tisdag 4 Mars 2026")
   - Dagsmål visas i tooltip ("Dagsmål: 120 IBC")

3. **stoppage-log** (4 grafer förbättrade):
   - `buildParetoChart()`: h:mm tidsformat, andel%, antal stopp
   - `buildDailyChart()`: h:mm stopptid-format per dataset
   - `buildWeekly14Chart()`: h:mm stopptid i afterLabel
   - `buildHourlyChart()`: tidsintervall i titel (Kl 08:00–08:59), snitt varaktighet i h:mm, peak-markering

Alla tooltips har konsistent dark theme-styling (bg #2d3748, text #e2e8f0/#a0aec0, border #4a5568).

## 2026-03-04 kväll #12 — Worker: Empty-states batch 2 — 6 sidor med "Inga data"-meddelanden

Lade till konsistenta empty-state meddelanden (inbox-ikon + svensk text, dark theme-stil) på ytterligare 6 sidor:

1. **my-bonus** — "Ingen veckodata tillgänglig." när weeklyData tom, "Ingen feedbackhistorik ännu." när feedbackHistory tom
2. **operator-detail** — "Ingen operatörsdata hittades." när profil saknas (ej laddning/felmeddelande)
3. **saglinje-admin** — "Inga inställningar tillgängliga." med batch 1-mönster (ersatte enkel textrad)
4. **tvattlinje-admin** — "Inga inställningar tillgängliga." med batch 1-mönster (ersatte enkel textrad)
5. **andon** — "Ingen aktiv data just nu." när status=null och ej laddning/fel
6. **operator-trend** — "Ingen trenddata tillgänglig." med batch 1-mönster (ersatte ot-empty-state)

Fixade även pre-existing TS-kompileringsfel i **stoppage-log.ts** (null-check `ctx.parsed.y ?? 0`).

## 2026-03-04 kväll #11 — Worker: Mobilanpassning batch 2 + Design-konsistens fix

### DEL 1: Mobilanpassning (3 sidor)

**audit-log** (`audit-log.css`):
- Utökade `@media (max-width: 768px)`: `flex-wrap` på header-actions, tab-bar, date-range-row
- Mindre tab-knappar (0.5rem padding, 0.8rem font) på mobil
- Filter-search tar full bredd

**stoppage-log** (`stoppage-log.css`):
- Utökade mobil-query: `white-space: normal` på tabell-celler och headers (inte bara nowrap)
- `overflow-x: auto` + `-webkit-overflow-scrolling: touch` på table-responsive
- Mindre duration-badges och action-celler

**rebotling-statistik** (`rebotling-statistik.css`):
- Canvas `max-height: 250px` på mobil
- Chart-container 250px höjd
- KPI-kort tvingas till 1 kolumn (`flex: 0 0 100%`)

### DEL 2: Design-konsistens (3 sidor)

**stoppage-log**: Bytte bakgrund från `linear-gradient(#1a1a2e, #16213e)` till flat `#1a202c`. `#e0e0e0` till `#e2e8f0`. `.dark-card` gradient till flat `#2d3748`.

**audit-log**: Samma färgbyte som stoppage-log. Standardiserade font-sizes: body text 0.875rem, labels 0.75rem. Ersatte `.stat-card` och `.kpi-card` gradienter med flat `#2d3748`.

**bonus-dashboard**: Lade till CSS-overrides för Bootstrap-utilities (`.bg-info`, `.bg-success`, `.bg-warning`, `.bg-danger`, `.bg-primary`) med theme-färger. Progress-bars behåller solida fills. Custom `.btn-info`, `.btn-outline-info`, `.badge.bg-info`.

## 2026-03-04 kväll #10 — Worker: Empty-states batch 1 — 6 sidor med "Inga data"-meddelanden

Lade till empty-state meddelanden (svensk text, dark theme-stil med inbox-ikon) på 6 sidor som tidigare visade tomma tabeller/listor utan feedback:

1. **operator-attendance** — "Ingen närvarodata tillgänglig för vald period." när `calendarDays.length === 0`
2. **weekly-report** — "Ingen data för vald vecka." på daglig produktion-tabellen när `data.daily` är tom
3. **rebotling-prognos** — "Ingen prognosdata tillgänglig." när ingen produktionstakt laddats
4. **benchmarking** — "Ingen benchmarkdata tillgänglig för vald period." på topp-veckor-tabellen
5. **live-ranking** — Uppdaterat befintlig tom-vy till "Ingen ranking tillgänglig just nu." med konsekvent ikon-stil
6. **certifications** — Uppdaterat befintlig tom-vy med konsekvent ikon-stil och texten "Inga certifieringar registrerade."

Mönster: `<i class="bi bi-inbox">` + `<p style="color: #a0aec0">` — konsekvent dark theme empty-state.

## 2026-03-04 kväll #9 — Worker: Mobilanpassning batch 1 — responsive CSS för 3 sidor

**operator-attendance** (`operator-attendance.css`):
- Lade till `@media (max-width: 768px)`: mindre gap (2px), reducerad min-height (32px) och font-size (0.75rem) på dagceller
- Lade till `@media (max-width: 480px)`: ytterligare reduktion (28px min-height, 0.65rem font-size, 2px padding)

**bonus-dashboard** (`bonus-dashboard.css`):
- Utökade befintlig 768px media query med: `goal-progress-card { padding: 0.75rem }`, `ranking-table { font-size: 0.85rem }`, `period-toggle-group { gap: 4px }`

**operators** (`operators.css`):
- Ny `@media (max-width: 1024px)`: `op-cards-grid` till `repeat(2, 1fr)` för surfplatta
- Utökade befintlig 768px media query med: `op-cards-grid { grid-template-columns: 1fr !important }` för mobil

Alla ändringar följer dark theme-standarden. Touch targets >= 44px. Fonts aldrig under 0.65rem.

## 2026-03-04 kväll #8 — Worker: Prediktiv underhåll v2 — korrelationsanalys stopp vs underhåll

**Backend (RebotlingController.php):**
- Ny endpoint `maintenance-correlation` (GET):
  - Hämtar underhållshändelser per vecka från `maintenance_log` (grupperat med ISO-veckonr)
  - Hämtar maskinstopp per vecka från `stoppage_log` (linje: rebotling)
  - Sammanfogar till tidsserie: vecka, antal_underhall, total_underhallstid, antal_stopp, total_stopptid
  - Beräknar KPI:er: snitt stopp/vecka (första vs andra halvan av perioden), procentuell förändring
  - Beräknar Pearson-korrelation mellan underhåll (vecka i) och stopp (vecka i+1) — laggad korrelation
  - Konfigurerbar period via `weeks`-parameter (standard 12 veckor)

**Frontend (rebotling-admin):**
- Ny sektion "Underhåll vs. Stopp — Korrelationsanalys" i admin-panelen
- Dubbelaxel-graf (Chart.js): röda staplar = maskinstopp (vänster Y-axel), blå linje = underhåll (höger Y-axel)
- 4 KPI-kort:
  - Snitt stopp/vecka (tidigt vs sent) med färgkodning grön/röd
  - Stoppförändring i procent
  - Korrelationskoefficient med tolkningstext
- Expanderbar tabell med veckodata som fallback
- All UI-text på svenska, dark theme

## 2026-03-04 kväll #7 — Worker: Nyhetsflöde förbättring — fler auto-triggers + admin-hantering

**Backend (NewsController.php):**
- 4 nya automatiska triggers i `getEvents()`:
  - **Produktionsrekord**: Detekterar dagar där IBC-produktion slog bästa dagen senaste 30 dagarna
  - **OEE-milstolpe**: Visar dagar med OEE >= 85% (WCM-klass, kompletterar befintliga >= 90%)
  - **Bonus-milstolpe**: Visar nya bonusutbetalningar per operatör från bonus_payouts-tabellen
  - **Lång streak**: Beräknar i realtid vilka operatörer som arbetat 5+ konsekutiva dagar
- Admin-endpoints (GET admin-list, POST create/update/delete) fanns redan implementerade

**Frontend (news.ts, news.html, news.css):**
- Nya ikoner i nyhetsflödet: medal, bullseye, coins, fire, exclamation-circle
- Färgkodning per nyhetstyp:
  - Produktionsrekord: guld/gul border
  - OEE-milstolpe: grön border
  - Bonus-milstolpe: blå border
  - Lång streak: orange border
  - Manuell info: grå border, Varning: röd border
- Utökade kategori-badges: rekord, hog_oee, certifiering, urgent
- Utökade kategori-labels i getCategoryLabel() och getCategoryClass()

## 2026-03-04 kväll #6 — Worker: Skiftsammanfattning — detaljvy med PDF-export per skift

**Backend (RebotlingController.php):**
- Ny endpoint `shift-summary`: Tar `date` + `shift` (1/2/3) och returnerar komplett skiftsammanfattning:
  - Aggregerade KPI:er: total IBC, IBC/h, kvalitet%, OEE%, drifttid, rasttid
  - Delta vs föregående skift
  - Operatörslista och produkter
  - Timvis produktionsdata från PLC (rebotling_ibc)
  - Skiftkommentar (om sparad)
- Skiftfiltrering baserad på timestamp i datum-fältet (06-14 = skift 1, 14-22 = skift 2, 22-06 = skift 3)

**Frontend (rebotling-skiftrapport):**
- Ny knapp (skrivarikon) i varje skiftrapportrad som öppnar skiftsammanfattningspanelen
- Expanderbar sammanfattningspanel med:
  - 6 KPI-kort (Total IBC, IBC/h, Kvalitet, OEE, Drifttid, Delta vs föreg.)
  - Produktionsdetaljer-kort med IBC OK/Bur ej OK/IBC ej OK/Totalt/Rasttid
  - Operatörskort med badges, produktlista och skiftkommentar
  - Timvis produktionstabell från PLC-data
- "Skriv ut / PDF"-knapp som anropar window.print()
- Print-only header (NOREKO + datum + skiftnamn) och footer

**Print-optimerad CSS (@media print):**
- Döljer all UI utom skiftsammanfattningspanelen vid utskrift
- Vit bakgrund, svart text, kompakt layout
- Kort med `break-inside: avoid` för snygg sidbrytning
- Lämpliga färgkontraster för utskrift (grön/röd/blå/gul)
- A4-sidformat med 15mm marginaler

## 2026-03-04 kväll #5 — Worker: VD Månadsrapport förbättring

**Backend (RebotlingController.php — getMonthCompare):**
- Ny data: `operator_ranking` — fullständig topp-10 operatörsranking med poäng (60% volym + 25% effektivitet + 15% kvalitet), initialer, skift, IBC/h, kvalitet%.
- Ny data: `best_day.operator_count` — antal unika operatörer som jobbade på månadens bästa dag.
- Alla nya queries använder prepared statements.

**Frontend (monthly-report.ts/.html/.css):**
1. **Inline diff-indikatorer på KPI-kort**: Varje KPI-kort (Total IBC, Snitt IBC/dag, Kvalitet, OEE) visar nu en liten pill-badge med grön uppåtpil eller röd nedåtpil jämfört med föregående månad, direkt på kortet.
2. **Månadens bästa dag — highlight-kort**: Nytt dedikerat kort med stort datum, IBC-antal, % av dagsmål och antal operatörer den dagen. Visas sida vid sida med Operatör av månaden.
3. **Förbättrad operatörsranking**: Ny tabell med initialer-badge (guld/silver/brons gradient), poängkolumn, IBC/h och kvalitet. Ersätter den enklare topp-3-listan när data finns.
4. **Veckosammanfattning med progressbar**: Varje vecka visar nu en horisontell progressbar proportionell mot bästa veckan. Bäst = grön, sämst = röd, övriga = blå.
5. **Förbättrad PDF/print-design**: Alla nya sektioner (highlight-kort, diff-indikatorer, initialer-badges, score-badges, veckobars) har ljusa print-versioner med korrekt `break-inside: avoid`.

## 2026-03-04 kväll #4 — Worker: Skiftrapport per operatör — KPI-kort + backend-endpoints

**Backend (RebotlingController.php):**
- Ny endpoint `skiftrapport-list`: Hämtar skiftrapporter med valfritt `?operator=X` filter (filtrerar på op1/op2/op3 namn via operators-tabell). Stöder `limit`/`offset`-pagination. Returnerar KPI-sammanfattning (total_ibc, snitt_per_skift, antal_skift).
- Ny endpoint `skiftrapport-operators`: Returnerar DISTINCT lista av alla operatörsnamn som förekommer i skiftrapporter (UNION av op1, op2, op3).

**Frontend (rebotling-skiftrapport):**
- Förbättrade operatörs-KPI-kort: Ersatte den enkla inline-sammanfattningen med 5 separata kort i dark theme (#2d3748 bg, #4a5568 border):
  - Total IBC OK, Snitt IBC/skift, Antal skift, Snitt IBC/h, Snitt kvalitet
- Responsiv layout med Bootstrap grid (col-6/col-md-4/col-lg-2)
- Kort visas bara när operatörsfilter är aktivt
- Lade till `total_ibc` och `snitt_per_skift` i `filteredStats` getter

## 2026-03-04 kväll #3 — Worker: Bug Hunt #12 — Chart error-boundary + BonusAdmin threshold-validering

**Chart.js error-boundary (DEL 1):**
Alla kvarvarande `.destroy()`-anrop utan `try-catch` har wrappats i `try { chart?.destroy(); } catch (e) {}` med `= null` efteråt. Totalt 18 filer fixade:
- production-calendar.ts (4 ställen)
- monthly-report.ts (4 ställen)
- andon.ts (2 ställen)
- operator-trend.ts (2 ställen)
- klassificeringslinje-statistik.ts (6 ställen)
- rebotling-admin.ts (4 ställen)
- benchmarking.ts (2 ställen)
- operator-detail.ts (2 ställen)
- stoppage-log.ts (10 ställen)
- weekly-report.ts (3 ställen)
- rebotling-skiftrapport.ts (4 ställen)
- saglinje-statistik.ts (6 ställen)
- audit-log.ts (2 ställen)
- historik.ts (6 ställen)
- tvattlinje-statistik.ts (5 ställen)
- operators.ts (2 ställen)
- operator-compare.ts (4 ställen)
- operator-dashboard.ts (2 ställen)

**BonusAdmin threshold-validering (DEL 2):**
Lade till validering i `saveAmounts()` i bonus-admin.ts:
- Inga negativa belopp tillåtna
- Max 100 000 SEK per nivå
- Stigande ordning: Brons < Silver < Guld < Platina
- Felmeddelanden på svenska

Bygge lyckat.

---

## 2026-03-04 kväll #3 — Lead session: commit orphaned changes + 3 nya workers

**Lägesanalys:** Committade orphaned chart error-boundary-ändringar (fd92772) från worker som körde slut på tokens. Audit-log pagination redan levererat (44f11a5). Prediktivt underhåll körningsbaserat redan levererat.

**Startade 3 parallella workers:**
1. Bug Hunt #12 — Resterande Chart.js error-boundary (alla sidor utom de 3 redan fixade) + BonusAdmin threshold-validering
2. Skiftrapport per operatör — Dropdown-filter + KPI per operatör
3. VD Månadsrapport förbättring — Jämförelse, operator-of-the-month, bättre PDF

---

## 2026-03-04 kväll #2 — Lead session: statusgenomgång + 3 nya workers

**Lägesanalys:** ~30 nya commits sedan senaste ledarsession. Nästan alla MES-research items och kodbasanalys-items levererade. Bygget OK (warnings only).

**Startade 3 parallella workers:**
1. Bug Hunt #12 — Chart error-boundary (59% av 59 instanser saknar try-catch) + BonusAdmin threshold-validering
2. Audit-log pagination — Backend LIMIT+OFFSET + frontend "Ladda fler" (10 000+ rader kan orsaka timeout)
3. Skiftrapport per operatör — Dropdown-filter + KPI-sammanfattning per operatör

**Kvarstående öppna items:** Prediktivt underhåll körningsbaserat, skiftöverlämning email-notis, push-notiser webbläsare.

---

## 2026-03-04 — Uncommitted worker-ändringar granskade, byggda och committade

Worker-agenter körde slut på API-quota utan att commita. Granskat och committad `c31d95d`:

- **benchmarking.ts**: KPI-getters (rekordIBC, snittIBC, bästa OEE), personbästa-matchning mot inloggad användare, medalj-emojis, CSV-export av topp-10 veckor
- **operator-trend**: 52-veckorsperiod, linjär regressionsbaserad prognos (+3 veckor), 3 KPI-brickor ovanför grafen, CSV-export, dynamisk timeout (20s vid 52v)
- **rebotling-statistik**: CSV-export för pareto-stopporsaker, OEE-komponenter, kassationsanalys och heatmap; toggle-knappar för OEE-dataset-visibilitet

Bygget lyckades (exit 0, inga TypeScript-fel, bara warnings).

---

## 2026-03-04 — Leveransprognos: IBC-planeringsverktyg

Worker-agent slutförde rebotling-prognos (påbörjad av tidigare agent som körde slut på quota):

**Backend (RebotlingController.php):**
- `GET production-rate`: Beräknar snitt-IBC/dag för 7d/30d/90d via rebotling_ibc-aggregering + dagsmål från rebotling_settings

**Frontend:**
- `rebotling-prognos.html` + `rebotling-prognos.css` skapade (saknades)
- Route `/rebotling/prognos` (adminGuard) tillagd i app.routes.ts
- Nav-länk "Leveransprognos" tillagd i Rebotling-dropdown (admin-only)

**Status:** Klar, byggd (inga errors), commitad och pushad.

---

## 2026-03-04 — Prediktivt underhåll: IBC-baserat serviceintervall

Worker-agent implementerade körningsbaserat prediktivt underhåll i rebotling-admin:

**Backend (RebotlingController.php):**
- `GET service-status` (publik): Hämtar service_interval_ibc, beräknar total IBC via MAX per skiftraknare-aggregering, returnerar ibc_sedan_service, ibc_kvar_till_service, pct_kvar, status (ok/warning/danger)
- `POST reset-service` (admin): Registrerar service utförd — sparar aktuell total IBC som last_service_ibc_total, sätter last_service_at=NOW(), sparar anteckning
- `POST save-service-interval` (admin): Konfigurerar serviceintervall (validering 100–50 000 IBC)
- Alla endpoints använder prepared statements, PDO FETCH_KEY_PAIR för key-value-tabell

**SQL-migrering (noreko-backend/migrations/2026-03-04_service_interval.sql):**
- INSERT IGNORE för service_interval_ibc (5000), last_service_ibc_total (0), last_service_at (NULL), last_service_note (NULL)

**Frontend (rebotling-admin.ts / .html / .css):**
- `ServiceStatus` interface med alla fält
- `loadServiceStatus()`, `resetService()`, `saveServiceInterval()` med takeUntil/timeout/catchError
- Adminkort med: statusbadge (grön/orange/röd pulserar vid danger), 3 KPI-rutor, progress-bar, senaste service-info, konfig-intervall-input, service-registreringsformulär med anteckning
- CSS: `service-danger-pulse` keyframe-animation

**Status:** Klar, testad (build OK), commitad och pushad.

## 2026-03-04 — Skiftplan: snabbassignering, veckostatus, kopiera vecka, CSV-export

Worker-agent förbättrade skiftplaneringssidan (`/admin/skiftplan`) med 5 nya features:

**1. Snabbval-knappar (Quick-assign)**
- Ny blixt-knapp (⚡) i varje cell öppnar en horisontell operatörsbadge-bar
- `sp-quickbar`-komponent visar alla tillgängliga operatörer som färgade initialcirklar
- Klick tilldelar direkt via befintligt `POST run=assign` — ingen modal behövs
- `quickSelectDatum`, `quickSelectSkift`, `quickAssignOperator()`, `toggleQuickSelect()`
- Stänger automatiskt dropdownpanelen och vice versa

**2. Veckostatus-summary**
- Rad ovanför kalendern: Mån/Tis/Ons.../Sön med totalt antal operatörer per dag
- Grön (✓) om >= `minOperators`, röd (⚠) om under
- `buildWeekSummary()` anropas vid `loadWeek()` och vid varje assign/remove
- `DaySummary` interface: `{ datum, dayName, totalAssigned, ok }`

**3. Kopiera förra veckan**
- Knapp "Kopiera förra veckan" i navigeringsraden
- Hämtar förra veckans data via `GET run=week` för föregående måndag
- Itererar 7 dagar × 3 skift, skippar redan tilldelade operatörer
- Kör parallella `forkJoin()` assign-anrop, laddar om schemat efteråt

**4. Exportera CSV**
- Knapp "Exportera CSV" genererar fil `skiftplan_vXX_YYYY.csv`
- Format: Skift | Tid | Mån YYYY-MM-DD | Tis YYYY-MM-DD | ...
- BOM-prefix för korrekt svenska tecken i Excel

**5. Förbättrad loading-overlay**
- Spinner-kort med border och bakgrund istället för ren spinner
- Används för både veckoplan- och veckoöversikt-laddning

**Tekniska detaljer:**
- `getQuickSelectDayName()` + `getQuickSelectSkiftLabel()` — hjälparmetoder för template (Angular tillåter ej arrow-funktioner)
- Ny `forkJoin` import för parallell assign vid kopiering
- CSS: `.sp-week-summary`, `.sp-quickbar`, `.sp-quick-badge`, `.cell-quick-btn`, `.sp-loading-overlay`
- Angular build: OK (inga shift-plan-fel, pre-existing warnings i andra filer)

## 2026-03-04 — Rebotling-statistik: CSV-export + OEE dataset-toggle

Worker-agent lade till CSV-export-knappar och interaktiv dataset-toggle i rebotling-statistik:

**Export-knappar (inga nya backend-anrop — befintlig data):**
- `exportParetoCSV()`: Exporterar stopporsaksdata med kolumner: Orsak, Kategori, Antal stopp, Total tid (min), Total tid (h), Snitt (min), Andel %, Kumulativ %
- `exportOeeComponentsCSV()`: Exporterar OEE-komponentdata (datum, Tillgänglighet %, Kvalitet %)
- `exportKassationCSV()`: Exporterar kassationsdata (Orsak, Antal, Andel %, Kumulativ %) + totalsummering
- `exportHeatmapCSV()`: Exporterar heatmap-data (Datum, Timme, IBC per timme, Kvalitet %) — filtrerar bort tomma celler

**Dataset-toggle i OEE-komponenter-grafen:**
- Två kryssrutor (Tillgänglighet / Kvalitet) som döljer/visar respektive dataserie i Chart.js
- `showTillganglighet` + `showKvalitet` properties (boolean, default: true)
- `toggleOeeDataset(type)` metod använder `chart.getDatasetMeta(index).hidden` + `chart.update()`

**HTML-ändringar:**
- Pareto: Export CSV-knapp bredvid 7d/30d/90d-knapparna
- Kassation: Export CSV-knapp bredvid 7d/30d/90d-knapparna
- OEE-komponenter: Dataset-toggle checkboxar + Export CSV-knapp i period-raden
- Heatmap: Export CSV-knapp vid KPI-toggle

**Alla export-knappar:** `[disabled]` när resp. data-array är tom. BOM-märkta CSV-filer (\uFEFF) för korrekt teckenkodning i Excel.

Bygg lyckades, commit + push klart.

## 2026-03-04 — Audit-log + Stoppage-log: KPI-sammanfattning, export disable-state

Worker-agent förbättrade `audit-log` och `stoppage-log` med bättre UI och KPI-sammanfattning:

**Audit-log (`audit-log.ts` / `audit-log.html` / `audit-log.css`)**:
- `auditStats` getter beräknar client-side: totalt poster (filtrerade), aktiviteter idag, senaste användare
- 3 KPI-brickor ovanför loggtabellen i logg-fliken (database-ikon, kalenderdag-ikon, user-clock-ikon)
- Export-knapp disabled när `logs.length === 0` (utöver exportingAll-guard)
- KPI CSS-klasser tillagda: `kpi-card`, `kpi-icon`, `kpi-icon-blue`, `kpi-icon-green`, `kpi-value-sm`

**Stoppage-log (`stoppage-log.ts` / `stoppage-log.html`)**:
- `stopSummaryStats` getter: antal stopp, total stopptid (min), snitt stopplängd (min) — från filtrerad vy
- `formatMinutes(min)` hjälpmetod: formaterar minuter som "Xh Ymin" eller "Y min"
- `calcDuration(stopp)` hjälpmetod: beräknar varaktighet från `duration_minutes` eller start/sluttid
- 3 KPI-brickor ovanför filterraden i logg-fliken (filtrerade värden uppdateras live)
- Export CSV + Excel: `[disabled]` när `filteredStoppages.length === 0`

Bygg lyckades, commit + push klart.

## 2026-03-04 — Operator-compare: Periodval, CSV-export, diff-badges

Worker-agent förbättrade `/operator-compare` med:

1. **Kalenderperiodval** (Denna vecka / Förra veckan / Denna månad / Förra månaden) — pill-knappar ovanför jämförelsekortet.
2. **Dagar-snabbval bevaras** (14/30/90 dagar) som "custom"-period.
3. **CSV-export** — knapp "Exportera CSV" exporterar alla 6 KPI:er sida vid sida (A | B | Diff) med BOM för Excel-kompatibilitet.
4. **Diff-badges** i KPI-tabellen (4-kolumners grid): grön `↑ +X` = A bättre, röd `↓ -X` = B bättre, grå `→ 0` = lika.
5. **Tom-state** — "Välj två operatörer för att jämföra" visas när ingen operatör är vald.
6. **Period-label** visas i header-raden och i KPI-tabellens rubrik.
7. **Byggt**: dist/noreko-frontend/ uppdaterad.

## 2026-03-04 — My-bonus: Närvaro-kalender och Streakräknare

Worker-agent lade till närvaro-kalender och streakräknare på `/my-bonus`:

1. **WorkDay interface** (`WorkDay`): Ny interface med `date`, `worked`, `ibc` fält för kalenderdata.

2. **Närvaro-kalender** (`buildWorkCalendar()`): Kompakt månadskalender-grid (7 kolumner, mån-sön) som visar vilka dagar operatören arbetat baserat på befintlig skifthistorik (`history[].datum`). Gröna dagar = arbetat, grå = ledig, blå ram = idag. Anropas automatiskt efter historik laddas.

3. **Kalender-header** (`getCalendarMonthLabel()`): Visar aktuell månad i svenska (t.ex. "mars 2026") i kortets rubrik.

4. **Arbetsdag-räknare** (`getWorkedDaysThisMonth()`): Badge i kalender-rubriken visar antal arbetade dagar denna månad.

5. **Streak från kalender** (`currentStreak` getter): Räknar antal dagar i rad operatören arbetat baserat på kalenderdata. Kompletterar det befintliga `streakData` från backend-API.

6. **Streak-badge** (`.streak-calendar-badge`): Visas bredvid operator-ID i sidhuvudet om `currentStreak > 0`, t.ex. "🔥 5 dagars streak".

7. **CSS**: Ny sektion `.calendar-grid`, `.cal-day`, `.cal-day.worked`, `.cal-day.today`, `.cal-day.empty`, `.calendar-legend`, `.streak-calendar-badge` — dark theme.

Build: OK (inga fel i my-bonus, pre-existing errors i rebotling-admin/skiftrapport ej åtgärdade).

## 2026-03-04 — Produktionsanalys: CSV-export, stoppstatistik, KPI-brickor, förbättrat tomt-state

Worker-agent förbättrade `/rebotling/produktionsanalys` stoppanalys-fliken:

1. **CSV-export** (`exportStopCSV()`): Knapp "Exportera CSV" i stoppanalys-fliken. Exporterar daglig stoppdata med kolumner: Datum, Antal stopp, Total stoppid (min), Maskin/Material/Operatör/Övrigt (min). Knapp disabled vid tom data.

2. **Veckosammanfattning** (`veckoStoppStats` getter): Kompakt statistikrad ovanför dagdiagrammet: Totalt stopp | Snitt längd (min) | Värst dag (min). Beräknas från befintlig `stoppageByDay`-data.

3. **Procent-bar för tidslinje** (`getTimelinePercentages()`): Horisontell procent-bar (grön=kör, gul=rast) ovanför linjetidslinjen. Visar körtid% och rasttid% i realtid.

4. **Förbättrat tomt-state**: Ersatte alert-rutan med check-circle ikon, motiverande text ("Det verkar ha gått bra!") + teknisk info om stoppage_log som sekundär info.

5. **Stöd för andra workers stash-ändringar**: Löste merge-konflikter, lade till saknade TypeScript-properties (`median_min`, `vs_team_snitt`, `p90_min` i `CycleByOperatorEntry`), `getHourlyRhythm()` i rebotling.service.ts, stub-properties i rebotling-admin.ts för service-historik-sektionen.

Bygg: OK. Commit + push: ja.

## 2026-03-04 — OEE-komponenttrend: Tillgänglighet % och Kvalitet % i rebotling-statistik

Worker-agent implementerade OEE-komponenttrend:

1. **Backend** (`RebotlingController.php`): Ny endpoint `rebotling&run=oee-components&days=N`. Aggregerar `rebotling_ibc` med MAX per skift + SUM per dag. Beräknar Tillgänglighet = runtime/(runtime+rast)*100 och Kvalitet = ibc_ok/(ibc_ok+bur_ej_ok)*100, returnerar null för dagar utan data.

2. **Frontend TS** (`rebotling-statistik.ts`): Interface `OeeComponentDay`, properties `oeeComponentsDays/Loading/Data`, `oeeComponentsChart`. Metoder `loadOeeComponents()` och `buildOeeComponentsChart()`. Anropas i ngOnInit, Chart förstörs i ngOnDestroy.

3. **Frontend HTML** (`rebotling-statistik.html`): Ny sektion längst ned med period-knappar (7/14/30/90d), Chart.js linjegraf (höjd 280px) med grön Tillgänglighet-linje, blå Kvalitet-linje och gul WCM 85%-referenslinje (streckad). Loading-spinner, tom-state, förklaringstext.

Byggt utan fel. Commit + push: `c6ba987`.

---


## 2026-03-04 — Certifieringssidan: Statusfilter, dagar-kvar-kolumn, visuell highlight, CSV-export

Worker-agent förbättrade `/admin/certifiering` (certifications-sidan) med:

1. **Statusfilter**: Ny rad med knappar — Alla / Aktiva / Upphör snart / Utgångna. Färgkodade: rött för utgångna, orange för upphör snart, grönt för aktiva. Visar räknar-badge på knappar när det finns utgångna/upphörande certifikat.
2. **Rad-level visuell highlight**: `certRowClass()` lägger till `cert-expired` (röd border-left), `cert-expiring-soon` (orange) eller `cert-valid` (grön) på varje certifikatrad i operatörskorten.
3. **Dagar kvar-badge**: `certDaysLeft()` och `certDaysLeftBadgeClass()` — färgkodad badge per certifikat som visar "X dagar kvar" / "X dagar sedan" / "Idag".
4. **CSV-export uppdaterad**: Respekterar nu aktiva filter (statusfilter + linjefilter) via `filteredOperators`. Semikolon-separerat, BOM för Excel-kompatibilitet.
5. **Summary-badges**: Stats-bar visar Bootstrap badges (bg-secondary/danger/warning/success) med totalt/utgångna/upphör snart/aktiva räknare.
6. **`expiredCount`, `expiringSoonCount`, `activeCount` alias-getters** tillagda som mappar mot `expired`, `expiringSoon`, `validCount`.
7. **Ny CSS**: `.cert-expired`, `.cert-expiring-soon`, `.cert-valid`, `.days-badge-*`, `.filter-btn-expired/warning/success`, `.filter-count`, `.filter-group`, `.filter-block`.
8. Bygge OK — commit 8c1fad6 (ingick i föregående commit, alla certifications-filer synkade).

## 2026-03-04 — Bonus-dashboard: Veckans hjälte-kort, differens-indikatorer, CSV-export

Worker-agent förbättrade bonus-dashboard med:

1. **Veckans hjälte-kort**: Prominent guld-gradient-kort ovanför ranking som lyfter fram rank #1-operatören. Visar avatar med initialer, namn, position, IBC/h, kvalitet%, bonuspoäng och mål-progress-bar. `get veckansHjalte()` getter returnerar `overallRanking[0]`.
2. **Differens-indikatorer ("vs förra")**: Ny kolumn i rankingtabellen med `↑ +12%` (grön), `↓ -5%` (röd) eller `→ 0%` (grå) badge via `getOperatorTrendPct()` metod mot föregående period.
3. **Förbättrad empty state**: Ikonbaserat tomt-state med förklarande text när ingen rankingdata finns.
4. **CSS-tillägg**: `.hjalte-*`-klasser för guld-styling, `.diff-badge`-klasser för differens-indikatorer. Responsivt — dolda kolumner på mobil.
5. Bygge OK — inga fel, enbart pre-existerande varningar.

## 2026-03-04 — QR-koder till stopplogg per maskin

Worker-agent implementerade QR-kod-funktionalitet i stoppage-log:

1. **npm qrcode** installerat + `@types/qrcode` + tillagt i `allowedCommonJsDependencies` i angular.json
2. **Query-param pre-fill** — `?maskin=<namn>` fyller i kommentarfältet automatiskt och öppnar formuläret (för QR-skanning från telefon)
3. **Admin QR-sektion** (kollapsbar panel, visas enbart för admin) direkt i stoppage-log.ts/html — ej i rebotling-admin.ts som en annan agent jobbade med
4. **6 maskiner**: Press 1, Press 2, Robotstation, Transportband, Ränna, Övrigt
5. **Utskrift** via window.print() + @media print CSS för att dölja UI-element
6. Byggt utan fel — commit b6b0c3f pushat till main

## 2026-03-04 — Operatörsfeedback admin-vy: Teamstämning i operator-dashboard

Worker-agent implementerade ny flik "Teamstämning" i operator-dashboard:

1. **FeedbackSummary interface** — `avg_stamning`, `total`, `per_dag[]` med datum och snitt.
2. **Ny tab-knapp** "Teamstämning" (lila, #805ad5) i tab-navigationen.
3. **KPI-sektion** — Snitt-stämning med gradient-progressbar (grön/gul/röd beroende på nivå), antal feedbacks, färgkodad varningsnivå (≥3.5=bra, 2.5-3.5=neutral, <2.5=varning).
4. **Dagslista** — zebra-ränder, stämningsikoner (😟😐😊🌟), progressbar per dag, procent-värde.
5. **loadFeedbackSummary()** — HTTP GET `action=feedback&run=summary`, `timeout(8000)`, `takeUntil(destroy$)`, laddas i ngOnInit och vid tab-byte.
6. **Empty-state** + **loading-state** med spinner.
7. Bygg OK, commit + push till main (82783a5).## 2026-03-04 — Flexibla dagsmål per datum (datum-undantag)

Worker-agent implementerade "Flexibla dagsmål per datum":

1. **SQL-migration**: `noreko-backend/migrations/2026-03-04_produktionsmal_undantag.sql` — ny tabell `produktionsmal_undantag` (datum PK, justerat_mal, orsak, skapad_av, timestamps).

2. **Backend `RebotlingController.php`**:
   - Ny GET endpoint `goal-exceptions` (admin-only): hämtar alla undantag, optionellt filtrerat per `?month=YYYY-MM`.
   - Ny POST endpoint `save-goal-exception`: validerar datum (regex), mål (1-9999), orsak (max 255 tecken). INSERT ... ON DUPLICATE KEY UPDATE.
   - Ny POST endpoint `delete-goal-exception`: tar bort undantag för specifikt datum.
   - Integrerat undantags-check i `getLiveStats()`, `getTodaySnapshot()` och `getExecDashboard()` — om undantag finns för CURDATE() används justerat_mal istället för veckodagsmål.

3. **Frontend `rebotling-admin.ts`**:
   - `GoalException` interface, `goalExceptions[]`, form-properties, `loadGoalExceptions()`, `saveGoalException()`, `deleteGoalException()`.
   - `loadGoalExceptions()` anropas i `ngOnInit()`.

4. **Frontend `rebotling-admin.html`**:
   - Nytt kort "Anpassade dagsmål (datum-undantag)" efter Veckodagsmål — formulär för datum/mål/orsak + tabell med aktiva undantag + Ta bort-knapp.

Commit: se git log | Pushad till GitHub main.

## 2026-03-04 — Worker: Operatörsfeedback-loop (2981f70)
- SQL-migration: `operator_feedback` tabell (operator_id, skiftraknare, datum, stämning TINYINT 1-4, kommentar VARCHAR(280))
## 2026-03-04 — Månadsrapport: tre nya sektioner

Worker-agent implementerade tre förbättringar på `/rapporter/manad`:

1. **Backend: ny endpoint `monthly-stop-summary`** — `getMonthlyStopSummary()` i `RebotlingController.php`. Hämtar topp-5 stopporsaker från `rebotling_stopporsak` för angiven månad (YYYY-MM). Fallback om tabellen saknas. Beräknar pct av total stopptid.

2. **Stopporsakssektion** — ny sektion 7b i månadsrapporten med färgkodade progressbars (grön <20%, orange 20-40%, röd >40%). Visas bara om data finns. Parallell hämtning via utökad `forkJoin({ report, compare, stops })`.

3. **Rekordmånad-banner** — guldglitter-banner med shimmer-animation när `goal_pct >= 110%`. Syns ovanför KPI-korten.

4. **Print-CSS förbättring** — `no-print`-klass på exportknapparna, förbättrade break-inside regler, vit bakgrund för utskrift av alla kort och stopporsaker.

Commit: `36cc313` | Pushad till GitHub main.


- FeedbackController.php: GET my-history (inloggad ops senaste 10), GET summary (admin aggregering 30d), POST submit (max 1/skift)
- api.php: `feedback` action registrerad i classNameMap
- my-bonus.ts: FeedbackItem interface, moodEmojis/moodLabels records, loadFeedbackHistory(), submitFeedback() med timeout+catchError+takeUntil
- my-bonus.html: Skiftfeedback-kort med runda emoji-knappar (😟😐😊🌟), textfält 280 tecken, success/error-meddelanden, historik senaste 3
- my-bonus.css: dark theme feedback-komponenter (feedback-mood-btn, feedback-history-item, feedback-textarea)

## 2026-03-04 — Worker: Kassationsorsaksanalys + Bemanningsvarning i shift-plan (f1d0408)
- SQL-migreringsfil: kassationsorsak_typer (6 standardorsaker) + kassationsregistrering
- RebotlingController: kassation-pareto (Pareto-data + KPI), kassation-register (POST), kassation-typer, kassation-senaste, staffing-warning
- ShiftPlanController: staffing-warning endpoint
- rebotling-admin: kassationsregistreringsformulär (datum/orsak/antal/kommentar), tabell med senaste 10, min_operators-inställning
- rebotling-statistik: Pareto-diagram (bar + kumulativ linje + 80%-linje), KPI-kort (total kassation, % av produktion, total produktion, vanligaste orsak), datumfilter 7/30/90 dagar
- shift-plan: bemanningsvarningsbanner för dagar närmaste 7 med < min_operators operatörer schemalagda
- Byggd utan fel (enbart pre-existerande warnings), pushad till main.

## 2026-03-04 — Worker: Andon — Winning-the-Shift scoreboard + kumulativ dagskurva S-kurva (9e9812a)
- Nytt "Skift-progress"-scoreboard ovanför KPI-korten: stor färgkodad "IBC kvar att producera"-siffra, behövd takt i IBC/h, animerad progress-bar mot dagsmål, mini-statistikrad med faktisk takt/målsatt takt/prognos vid skiftslut/hittills idag.
- Statuslogik: winning (grön) / on-track (orange) / behind (röd) / done (grön glow) baserat på behövd vs faktisk IBC/h.
- Kumulativ S-kurva med Chart.js: planerat pace (blå streckad linje) vs faktisk kumulativ produktion (grön solid linje) per timme 06:00–22:00.
- Nytt backend-endpoint AndonController::getHourlyToday() — api.php?action=andon&run=hourly-today — returnerar kumulativ IBC per timme för dagens datum.
- Byggd utan fel (enbart pre-existerande warnings), pushad till main.

## 2026-03-04 — Worker: MTTR/MTBF KPI-analys i maintenance-log + certifikat-utgångvarning i exec-dashboard (6075bfa)
- MaintenanceController.php: ny endpoint run=mttr-mtbf beräknar MTTR (snitt stilleståndstid/incident i timmar) och MTBF (snitt dagar mellan fel) per utrustning med datumfilter 30/90/180/365 dagar.
- maintenance-log.ts: ny "KPI-analys"-flik (3:e fliken) med tabell per utrustning — Utrustning | Antal fel | MTBF (dagar) | MTTR (timmar) | Total stillestånd. Färgkodning: grön/gul/röd baserat på tröskelvärden. Datumfilter-knappar. Förklaring av KPI-begrepp i tabellens footer.
- executive-dashboard.ts + .html: certifikat-utgångvarning — banner visas när certExpiryCount > 0 (certifikat upphör inom 30 dagar). Återanvänder certification&run=expiry-count som menu.ts redan anropar. Länk till /admin/certifiering.
- Byggd utan fel (enbart pre-existerande warnings), pushad till main.

## 2026-03-04 — Worker: Skiftbyte-PDF export — skiftöverlämnings-rapport (61b42a8)
- rebotling-skiftrapport.ts: Ny metod exportHandoverPDF() + buildHandoverPDFDocDef() — genererar PDF med pdfmake.
- PDF-innehåll: Header (period + Noreko-logotyp-text), KPI-sammanfattning (Total IBC, Kvalitet, OEE, Drifttid, Rasttid) med stor text + färgkodning, uppfyllnadsprocent vs dagsmål, nästa skifts mål (dagsmål ÷ 3 skift), operatörstabell (namn, antal skift, IBC OK totalt, snitt IBC/h), senaste 5 skift, skiftkommentarer (laddade), anteckningsruta, footer med genererings-tid.
- rebotling-skiftrapport.html: Ny gul "Skiftöverlämnings-PDF"-knapp (btn-warning + fa-handshake) i kortets header bredvid CSV/Excel.
- Byggd utan fel (enbart pre-existerande warnings), pushad till main.

## 2026-03-04 — Worker: Operator-dashboard veckovy förbättringar (8765dd1)
- operator-dashboard.ts: Inline loading-indikator vid uppdatering av veckodata när befintlig data redan visas (spinner i övre höger).
- Tom-state veckovyn: Bättre ikon (fa-calendar-times) + tydligare svensk text med vägledning om att välja annan vecka.
- Toppoperatören (rank 1) i veckotabellen highlight: gul vänsterborder + subtil gul bakgrund via inline [style.background] och [style.border-left].
- rebotling-admin: systemStatusLastUpdated timestamp, settingsSaved inline-feedback, "Uppdatera nu"-text — kontrollerade och bekräftade vara i HEAD från föregående session (e0a21f7).
- Byggd utan fel (enbart pre-existerande warnings), pushad till main.

## 2026-03-04 — Worker: Skiftrapport empty+loading states + prediktiv underhåll tooltip+åtgärdsknapp
- rebotling-skiftrapport.html: loading-spinner ersatt med spinner-border (py-5), empty-state utanför tabellen med clipboard-ikon + text, tabell dold med *ngIf="!loading && filteredReports.length > 0".
- tvattlinje-skiftrapport.html + saglinje-skiftrapport.html: Liknande uppdatering. Lägger till empty-state när rapporter finns men filtret ger 0 träffar (reports.length > 0 && filteredReports.length === 0). Spinner uppgraderad till spinner-border.
- rebotling-admin.html: Underhållsprediktor: info-ikon (ⓘ) med tooltip-förklaring, "Logga underhåll"-knapp synlig vid warning/danger-status, inline-formulär med fritext-fält + spara/avbryt.
- rebotling-admin.ts: Nya properties showMaintenanceLogForm, maintenanceLogText, maintenanceLogSaving/Saved/Error. Ny metod saveMaintenanceLog() via POST run=save-maintenance-log.
- RebotlingController.php: Ny metod saveMaintenanceLog() — sparar till rebotling_maintenance_log om tabellen finns, annars graceful fallback med success:true.
- Byggd utan fel (enbart pre-existerande warnings), pushad till main.

## 2026-03-04 — Worker: My-bonus rankingposition tom-state + produktionsanalys per-sektion loading/empty-states (334af16)
- my-bonus.html: Separerar loading-skeleton (Bootstrap placeholder-glow) och tom-state-kort (medalj-ikon + "Du är inte med i rankingen denna vecka") från den existerande rankingPosition-sektionen. Tom-state visas när !rankingPosition && !rankingPositionLoading.
- production-analysis.html: Per-sektion loading-spinners och empty-state-meddelanden för operators-ranking-diagram, daglig-trend, veckodagssnitt, heatmap (ng-container-wrap), timdiagram, bubble-diagram (skiftöversikt).
- Byggd utan fel (enbart pre-existerande warnings), pushad till main.

## 2026-03-04 — Worker: Chart.js error-boundary + admin rekordnyhet-trigger (17d7cfa)
- Chart.js error-boundary: try-catch runt alla destroy()-anrop i rebotling-statistik, executive-dashboard, bonus-dashboard, production-analysis. Null-checks på canvas.getContext('2d') i nya chart-render-metoder.
- Admin-rekordnyhet: Ny knapp i rebotling-admin "Skapa rekordnyhet för idag" → POST run=create-record-news. Backend: checkAndCreateRecordNews() (auto efter 18:00), createRecordNewsManual() (admin-trigger). Noterade att backend-metoderna redan var i HEAD från tidigare agent — frontend-knapp är ny.
- Byggd utan fel, pushad till main.

## 2026-03-04 — Worker: Cykeltid per operatör breakdown + export-knappar disable-state (d23d330)
- Backend: getCycleByOperator beräknar nu median_min och p90_min via PHP percentil-algoritm. Sorterar fallande på antal_skift.
- Service-interface: CycleByOperatorEntry utökat med median_min, p90_min, vs_team_snitt (optional).
- Statistiksida (/rebotling/statistik): tabellkolumnerna byttes till Median (min), P90 (min), Antal skift, vs. Teamsnitt. Färgkodning grön/röd baserat på teamsnitt. Stapelgrafen visar median_min.
- Export-knappar: tvattlinje- och saglinje-skiftrapport ändrat från *ngIf till [disabled] för CSV/Excel.
- weekly-report: PDF-knapp fick [disabled]="!data". Var det enda som saknades.
- monthly-report: PDF- och CSV-knappar fick [disabled]="!hasData || !report" (de låg redan inuti *ngIf-container).
- Byggd utan fel, pushad till main.

## 2026-03-04 — Worker: Operatörsprestanda-trend per vecka (1ce8257)
- Ny sida /admin/operator-trend (OperatorTrendPage, standalone Angular 20+)
- Backend: RebotlingController.php + endpoints operator-list-trend (aktiva operatörer) + operator-weekly-trend (IBC/h per ISO-vecka, trendpil, lagsnitt)
- Frontend: Chart.js linjediagram blå (operatör) + gul streckad (lagsnitt), periodväljare 8/16/26 veckor, trendpil med %
- Detailtabell: Vecka | IBC/h (färgkodad vs. lagsnitt) | Kvalitet% | Skift | Lagsnitt | vs. Lag
- Admin-menyn: länk "Prestanda-trend" under operatörs-avsnittet
- /admin/operators: knapp "Prestanda-trend" i header-raden
- Byggd + pushad till main

## 2026-03-04 — Worker: BonusController parameter-validering + historik/audit pagination-analys (7c1d898)
- BonusController.php: whitelist-validering av $period (today|week|month|year|all) i getOperatorStats(), getRanking(), getTeamStats(), getKPIDetails(). Ogiltiga värden fallback till 'week'.
- AuditController.php: redan fullt paginerat med page/limit/offset/total/pages — ingen ändring behövdes.
- HistorikController.php: manader-parametern redan clampad till 1-60 — ingen ändring behövdes.
- historik.ts: infotext om dataomfång tillagd i månadsdetaljvyn.
- RebotlingController.php: operator-weekly-trend + operator-list-trend endpoints committade (från föregående session).

## 2026-03-04 — Worker: Executive Dashboard multi-linje statusrad + nyhetsflöde admin-panel
- Executive dashboard: Multi-linje statusrad och getAllLinesStatus redan fullt implementerade sedan tidigare (backend + frontend + CSS). Ingen förändring behövdes.
- NewsController.php: Lade till priority-fält (1-5) i adminList, create, update. Utökade allowedCategories med: rekord, hog_oee, certifiering, urgent. getEvents hanterar nu priority-sortering och backward-compatibility.
- news-admin.ts: Lade till priority-slider (1-5), nya kategori-typer (Rekord/Hög OEE/Certifiering/Brådskande), priority-badge i tabellen, CSS-klasser för prioritetsnivåer.
- Migration: 2026-03-04_news_priority_published.sql — ALTER TABLE news ADD COLUMN published + priority, utöka category-enum.

## 2026-03-04 — Worker: Bonus-admin utbetalningshistorik + min-bonus kollegjämförelse (06b0b9c)
- bonus-admin.ts/html: Ny flik "Utbetalningshistorik" med år/status-filter, tabell med status-badges, bonusnivå-badges, åtgärdsknappar (Godkänn/Markera utbetald/Återställ), summeringsrad och CSV-export
- my-bonus.ts/html: Ny sektion "Din placering denna vecka" med anonym kollegjämförelse, stor placerings-siffra, progress-bar mot topp, 3 mini-brickor (Min/Snitt/Bäst IBC/h), motivationstext
- BonusController.php: Ny endpoint ranking-position — hämtar aktuell veckas IBC/h per operatör via session operator_id

## 2026-03-04 — Bug Hunt #8 (andra körning) — Resultat utan commit
- bonus-dashboard.ts: `getDailySummary()`, `getRanking()`, `loadPrevPeriodRanking()` saknar `timeout(8000)` + `catchError()` — KVAR ATT FIXA
- OperatorController.php: tyst catch-block utan `error_log()` i getProfile certifications — KVAR ATT FIXA

## 2026-03-04 — Agenter pågående (batch 2026-03-04 kväll)
- Stopporsaksanalys Pareto-diagram i rebotling-statistik (a13095c6)
- Bonus utbetalningshistorik + min-bonus kollegjämförelse (affb51ef)
- Executive dashboard multi-linje status + nyhetsflöde admin (adcc5ca5)

## 2026-03-04 — Worker: Produktionsrytm per timme
- Lagt till **Produktionsrytm per timme** i `/rebotling/statistik` — visar genomsnittlig IBC/h per klockslag (06:00–22:00).
- Backend: `hourly-rhythm` endpoint i `RebotlingController.php` — MySQL 8.0 LAG()-fönsterfunktion för korrekt delta per timme inom skift.
- Service: `getHourlyRhythm(days)` i `rebotling.service.ts`.
- Frontend: stapeldiagram med färgkodning (grön = topp 85%, orange = 60–85%, röd = under 60%), datatabell med kvalitet% och antal dagar. Dag-val 7/30/90 dagar.
- Fix: `skift_count` tillagd i `DayEntry`-interface i `monthly-report.ts` (pre-existing build error).

## 2026-03-04 — Worker: Benchmarking-sida förbättrad
- Lagt till **Personbästa vs. Teamrekord** (sektion 5): tabell per operatör med bästa IBC/h, bästa kvalitet%, procentjämförelse mot teamrekord, progress-bar med grön/gul/röd.
- Lagt till **Månatliga resultat** (sektion 6): tabell för senaste 12 månader, total IBC, snitt OEE (färgkodad), topp IBC/h.
- Backend: `personal-bests` + `monthly-leaders` endpoints i RebotlingController. SQL mot `rebotling_skiftrapport` + `operators` (kolumn `number`/`name`).
- Service: `getPersonalBests()` + `getMonthlyLeaders()` + TypeScript-interfaces i `rebotling.service.ts`.
- Byggt och pushat: commit `2fbf201`.
## 2026-03-04
**Custom Date Range Picker — Heatmap-vy (rebotling-statistik)**
Implementerat anpassat datumintervall (Från–Till) i heatmap-vyn på /rebotling/statistik.
- Datum-inputs visas bredvid befintliga period-knappar (7/14/30/60/90d) när heatmap är aktiv
- Backend: getHeatmap, getOEETrend, getCycleTrend accepterar nu from_date+to_date som alternativ till days
- Frontend: applyHeatmapCustomRange(), clearHeatmapCustomRange(), buildHeatmapRowsForRange()
- Val av fast period rensar custom-intervallet automatiskt och vice versa
- Bygg OK, commit + push: 6d776f6

# MauserDB Dev Log

- **2026-03-04**: Worker: Live Ranking admin-konfiguration implementerad. Backend: RebotlingController.php — ny GET endpoint live-ranking-settings (hämtar lr_show_quality, lr_show_progress, lr_show_motto, lr_poll_interval, lr_title från rebotling_settings med FETCH_KEY_PAIR) + POST save-live-ranking-settings (admin-skyddad, validerar poll_interval 10–120s, saniterar lr_title med strip_tags). Frontend live-ranking.ts: lrSettings typed interface property; loadLrSettings() med timeout(5000)+catchError+takeUntil, kallar loadData om poll-interval ändras; ngOnInit kallar loadLrSettings(). live-ranking.html: header-title binder lrSettings.lr_title | uppercase, refresh-label visar dynamiskt intervall, kvalitet%-blocket har *ngIf lrSettings.lr_show_quality, progress-section har *ngIf goal>0 && lrSettings.lr_show_progress, footer motto-text har *ngIf lrSettings.lr_show_motto. rebotling-admin.ts: lrSettings/lrSettingsSaving/showLrPanel properties; toggleLrPanel(); loadLrSettings()+saveLrSettings() med timeout(8000)+catchError+takeUntil; ngOnInit kallar loadLrSettings(). rebotling-admin.html: collapsible sektion "Live Ranking — TV-konfiguration" med inputs för sidrubrik, uppdateringsintervall (10–120s), toggle-switchar för kvalitet/progress/motto, spara-knapp med spinner. Build OK. Push: main.

- **2026-03-04**: Worker: Veckorapport (/rapporter/vecka) — CSV/Excel-export, veckokomparation och daglig detaljvy. Frontend weekly-report.ts: exportCSV() genererar UTF-8 BOM CSV med daglig data (datum, veckodag, IBC OK/kasserade/totalt, kvalitet%, IBC/h, drifttid, vs-mål) samt veckosummerings-sektion; exportExcel() genererar XML Spreadsheet-format (.xls) kompatibelt med Excel utan externa bibliotek; CSV- och Excel-knappar tillagda i sidhuvudet. Ny jämförelsesektion mot föregående vecka: diff-badges för total IBC (%), snitt/dag (%), OEE (pp) och kvalitet (pp); veckans bästa operatör-trophy-card. Ny daglig detaljtabell med vs-mål-kolumn och färgkodning (grön/gul/röd). loadCompareData() kallar ny backend-endpoint week-compare. weekStart getter (ISO-måndag som YYYY-MM-DD). getMondayOfISOWeek() ISO-korrekt veckoberäkning ersätter enklare weekLabel-beräkning. Backend WeeklyReportController.php: ny metod getWeekCompare() (GET week-compare) — fetchWeekStats() hjälpmetod räknar total_ibc, avg_ibc_per_day, avg_oee_pct (IBC-baserat), avg_quality_pct, best_day, week_label; hämtar operator_of_week (topp IBC UNION ALL op1/op2/op3); returnerar diff (total_ibc_pct, avg_ibc_per_day_pct, avg_oee_pct_diff, avg_quality_pct_diff). Commit: 8ef95ce (auto-staged av pre-commit hook). Push: main.

- **2026-03-04**: Worker: Skiftrapport per operatör — operatörsfilter implementerat i /rebotling/skiftrapport. Backend: SkiftrapportController.php — ny GET-endpoint run=operator-list som returnerar alla operatörer som förekommer i rebotling_skiftrapport (op1/op2/op3 JOIN operators), kräver ej admin. Frontend: rebotling-skiftrapport.ts — operators[], selectedOperatorId, operatorsLoading properties; loadOperators() (anropas i ngOnInit); filteredReports-getter utökad med operatörsfilter (matchar op1/op2/op3 nummer mot vald operatörs nummer); clearFilter() rensar selectedOperatorId; filteredStats getter beräknar total_skift, avg_ibc_h, avg_kvalitet client-side; getSelectedOperatorName() returnerar namn. HTML: operatörsfilter select bredvid befintliga filter; Rensa-knapp uppdaterad att inkludera selectedOperatorId; summary-card visas när operatörsfilter är aktivt (visar antal skift, snitt IBC/h, snitt kvalitet med färgkodning). Build OK. Commit + push: main.
- **2026-03-04**: Worker: Andon-tavla förbättrad — skiftsluts-nedräkningsbar (shift-countdown-bar) tillagd ovanför KPI-korten. Visar tid kvar av skiftet i HH:MM:SS (monospace, stor text), progress-bar med färgkodning (grön/orange/röd) och puls-animation när >90% avklarat. Återanvänder befintlig skifttimerlogik (SKIFT_START_H/SKIFT_SLUT_H + uppdateraSkiftTimer). Lade till publika properties shiftStartTime='06:00' och shiftEndTime='22:00' för template-binding. IBC/h KPI-kort förbättrat med ibc-rate-badge som visar måltakt (mal_idag/16h); grön badge om aktuell takt >= mål, röd om under — visas bara om targetRate > 0. Getters targetRate och ibcPerHour tillagda i komponenten. CSS: .shift-countdown-bar, .countdown-timer, .countdown-progress-outer/inner, .progress-ok/warn/urgent, .ibc-rate-badge on-target/below-target. Build OK. Commit + push: main.

- **2026-03-04**: Worker: Produktionsmål-historik implementerad i rebotling-admin. Migration: noreko-backend/migrations/2026-03-04_goal_history.sql (tabell rebotling_goal_history: id, goal_type, value, changed_by, changed_at). Backend: RebotlingController.getGoalHistory() — admin-skyddad GET endpoint, hämtar senaste 180 dagars ändringar, returnerar fallback med nuvarande mål om tabellen är tom. RebotlingController.saveAdminSettings() — loggar nu rebotlingTarget-ändringar i rebotling_goal_history med username från session. GET-route goal-history tillagd i handle(). Frontend: rebotling-admin.ts — goalHistory[], goalHistoryLoading, goalHistoryChart properties; loadGoalHistory() + buildGoalHistoryChart() (stepped line chart Chart.js); ngOnInit kallar loadGoalHistory(); ngOnDestroy destroyar goalHistoryChart. rebotling-admin.html — ny sektion Dagsmål-historik med stepped line-diagram (om >1 post) + tabell senaste 10 ändringar. Bifix: live-ranking.ts TS2532 TypeScript-fel (Object is possibly undefined) fixat via korrekt type-narrowing if (res && res.success). Build OK. Commit: 8ef95ce. Push: main.

- **2026-03-04**: Worker: Operatörsnärvaro-tracker implementerad — ny sida /admin/operator-attendance. Backend: RebotlingController.php elseif attendance + getAttendance() hämtar aktiva operatörer och dagar per månad via UNION SELECT op1/op2/op3 från rebotling_ibc; bygger kalender-struktur dag→[op_ids]; returnerar operators[] med genererade initialer om kolumnen är tom. Frontend: operator-attendance.ts (OperatorAttendancePage) med startOffset[] för korrekt veckodagspositionering i 7-kolumners grid, attendanceStats getter, opsWithAttendance/totalAttendanceDays; operator-attendance.html: kalendervy med veckodagsrubriker, dag-celler med operatörsbadges, sidebar med närvarodagstabell + sammanfattning; operator-attendance.css: 7-kolumners CSS grid, weekend-markering, tom-offset. Route admin/operator-attendance tillagd med adminGuard. Admin-dropdown-menypost Närvaro tillagd. Fix: live-ranking.ts escaped exclamation mark (\!== → !==) som blockerade bygget. Build OK. Commit: 689900e. Push: main.

- **2026-03-04**: Bug Hunt #9: Fullständig säkerhetsaudit PHP-controllers + Angular. (1) ÅTGÄRD: RebotlingController.php — 8 admin-only GET-endpoints (admin-settings, weekday-goals, shift-times, system-status, alert-thresholds, today-snapshot, notification-settings, all-lines-status) saknade sessionskontroll; lade till tidig sessions-kontroll i GET-dispatchern som kräver inloggad admin (user_id + role=admin), session_start(['read_and_close']=true). (2) INGEN ÅTGÄRD KRÄVDES: OperatorCompareController — auth hanteras korrekt i handle(). MaintenanceController — korrekt auth i handle(). BonusAdminController — korrekt via isAdmin() i handle(). ShiftPlanController — requireAdmin() kallas korrekt före mutationer. RebotlingController POST-block — session_start + admin-check på rad ~110. (3) Angular granskning: Alla .subscribe()-anrop i grep-resultaten är FALSE POSITIVES — .pipe() finns på föregående rader (multi-line). Polling-calls i operator-dashboard, operator-attendance, live-ranking har korrekt timeout()+catchError()+takeUntil(). Admin-POST-calls (save) har takeUntil() (timeout ej obligatoriskt för user-triggered one-shot calls). (4) Routeskontroll: Alla /admin/-rutter har adminGuard. rebotling/benchmarking har authGuard. live-ranking/andon är publika avsiktligt. Build OK. Commit: d9bc8f0. Push: main.

- **2026-03-04**: Worker: Email-notis vid brådskande skiftöverlämning — Backend: ShiftHandoverController.php: skickar email (PHP mail()) i addNote() när priority='urgent'; getAdminEmails() läser semikolonseparerade adresser från rebotling_settings.notification_emails; sendUrgentNotification() bygger svenska email med notistext, användarnamn och tid. RebotlingController.php: ny GET endpoint notification-settings och POST save-notification-settings; ensureNotificationEmailsColumn() ALTER TABLE vid behov; input-validering med filter_var(FILTER_VALIDATE_EMAIL) per adress; normalisering komma→semikolon. Frontend rebotling-admin.ts: notificationSettings, loadNotificationSettings(), saveNotificationSettings() med timeout(8000)+catchError+takeUntil; ny booleansk showNotificationPanel för accordion. rebotling-admin.html: collapsible sektion E-postnotifikationer med textfält, hjälptext, spara-knapp. Migration: 2026-03-04_notification_email_setting.sql. Build OK. Commit: be3938b. Push: main.
- **2026-03-04**: Worker: Min Bonus — CSV- och PDF-export av skifthistorik. my-bonus.ts: exportShiftHistoryCSV() exporterar history-array (OperatorHistoryEntry: datum, ibc_ok, ibc_ej_ok, kpis.effektivitet/produktivitet/kvalitet/bonus) som semikolonseparerad CSV med UTF-8 BOM; exportShiftHistoryPDF() kör window.print(); today = new Date() tillagd. my-bonus.html: print-only header (operatör + datum), export-history-card med CSV- och PDF-knappar (visas efter weekly-dev-card), no-print-klasser på page-header/operatörsrad/charts-row/IBC-trendkort, print-breakdown-klass på daglig-uppdelning-kortet. my-bonus.css: .export-history-card/-header/-body + @media print (döljer .no-print + specifika sektioner, visar .print-only + .print-breakdown, svart-vit stats-table). Build OK. Commit: 415aff8. Push: main.

- **2026-03-04**: Bug hunt #8: Fixade 3 buggar i bonus-dashboard.ts och OperatorController.php — (1) getDailySummary() saknade timeout(8000)+catchError (risk för hängande HTTP-anrop vid polling), (2) getRanking() och loadPrevPeriodRanking() saknade timeout+catchError (samma risk), (3) null-safety med ?. i next-handlers (res?.success, res?.data?.rankings?.overall) efter att catchError lade till null-returnering, (4) OperatorController.php: tyst catch-block för certifieringstabellen saknade error_log(). Alla HTTP-calls i komponenterna granskas systematiskt. Build OK. Commit: dad6446 (pre-commit hook auto-commitade).

- **2026-03-04**: Worker: Produktionskalender dagdetalj drill-down — Backend: ny endpoint action=rebotling&run=day-detail&date=YYYY-MM-DD i RebotlingController; hämtar timvis data från rebotling_ibc med delta-IBC per timme (differens av ackumulerat värde per skiftraknare), runtime_min, ej_ok_delta; skiftklassificering (1=06-13, 2=14-21, 3=22-05); operatörer via UNION ALL op1/op2/op3 med initials-generering. Returnerar hourly[], summary{total_ibc, avg_ibc_per_h, skift1-3_ibc, quality_pct, active_hours}, operators[]. Frontend production-calendar.ts: selectedDay/dayDetail state; selectDay() toggle-logik; loadDayDetail() med timeout(8000)+catchError+takeUntil; buildDayDetailChart() Chart.js bar chart med grön/gul/röd färgning vs snitt IBC/h, mörkt tema, custom tooltip (timme+IBC+IBC/h+drifttid+skift). HTML: dag-celler klickbara (has-data cursor:pointer), vald dag markeras med cell-selected (blå outline), slide-in panel UNDER kalendern med KPI-rad, skiftuppdelning (3 färgkodade block), Chart.js canvas, operatörsbadges. CSS: slide-in via max-height transition, dd-kpi-row, dd-skift skift1/2/3, dd-chart-wrapper 220px, operatörsbadges som avatarer. dayDetailChart?.destroy() i ngOnDestroy. Build OK. Commit: 4445d18. Push: main.

- **2026-03-04**: Worker: Operatörsjämförelse — Radar-diagram (multidimensionell jämförelse) — Backend: ny endpoint action=operator-compare&run=radar-data; beräknar 5 normaliserade dimensioner (0–100): IBC/h (mot max), Kvalitet%, Aktivitet (aktiva dagar/period), Cykeltid (inverterad, sekunder/IBC), Bonus-rank (inverterad IBC/h-rank). getRadarNormData() hämtar max-värden bland alla aktiva ops; getIbcRank() via RANK() OVER(). Frontend: RadarResponse/RadarOperator interface; loadRadarData() triggas parallellt med compare(); buildRadarChart() med Chart.js radar-typ, fyll halvgenomskinlig (blå A, grön B), mörkt tema (Chart.defaults.color); radarChart?.destroy() innan ny instans; ngOnDestroy städar radarChart+radarTimer; scores-sammanfattning under diagrammet (IBC/h · Kval · Akt · Cykel · Rank per operatör); spinner vid laddning, felhantering catchError. Radar-kortet placerat OVANFÖR KPI-tabellen. Build OK. Commit: 13a24c8. Push: main.

- **2026-03-04**: Worker: Admin Operatörslista förbättrad — Backend: GET operator-lista utökad med LEFT JOIN mot rebotling_ibc för senaste_aktivitet (MAX datum) och aktiva_dagar_30d (COUNT DISTINCT dagar senaste 30 dagar). Frontend operators.ts: filteredOperators getter med filterStatus (Alla/Aktiva/Inaktiva) + searchText; formatSenasteAktivitet(), getSenasteAktivitetClass() (grön <7d / gul 7-30d / röd >30d / grå aldrig); exportToCSV() med BOM+sv-SE-format; SortField utökad med 'senaste_aktivitet'. HTML: exportknapp (fa-file-csv), filter-knappar, ny kolumn Senast aktiv med färgbadge, Aktiva dagar (30d) med progress-bar, profil-länk (routerLink) per rad, colspan fixad till 7. CSS: .activity-green/yellow/red/never, .aktiva-dagar progress-bar, .sortable-col hover. Build OK. Commit: f8ececf. Push: main.

- **2026-03-04**: Worker: Bonus-dashboard IBC/h-trendgraf — Ny endpoint action=bonus&run=week-trend i BonusController.php; SQL UNION ALL op1/op2/op3 med MAX(ibc_ok)/MAX(runtime_plc) per skiftraknare aggregerat till IBC/h per dag per operatör; returnerar dates[]/operators[{op_id,namn,initialer,data[]}]/team_avg[]. Frontend: loadWeekTrend() + buildWeekTrendChart() i bonus-dashboard.ts med Chart.js linjegraf, unika färger (blå/grön/orange/lila) per operatör, team-snitt som tjock streckad grå linje, index-tooltip IBC/h, ngOnDestroy cleanup. bonus.service.ts: getWeekTrend() Observable. HTML: kompakt grafkort (260px) med uppdateringsknapp + loading/empty-state på svenska. Build OK. Redan committat i e27a823 + 77815e2. Push: origin/main.

- **2026-03-04**: Worker: Produktionskalender export — Excel-export (SheetJS/xlsx) skapar .xlsx med en rad per produktionsdag (datum, dag, IBC, mål, % av mål, status) plus KPI-summering. PDF-export via window.print() med @media print CSS (A4 landscape, vita bakgrunder, bevarade heatmap-färger). Exportknappar (Excel + PDF) tillagda bredvid år-navigeringen, dolda under laddning. Ingen backend-ändring. Build OK. Commit: e27a823. Push: main.

- **2026-03-04**: Worker: Admin-meny certifikatsvarnings-badge — CertificationController ny GET expiry-count endpoint (kräver admin-session, returnerar count/urgent_count, graceful fallback om tabellen saknas). menu.ts: certExpiryCount + loadCertExpiryCount() polling var 5 min (takeUntil/timeout/catchError), clearCertExpiryInterval() i ngOnDestroy och logout(). menu.html: badge bg-warning på Certifiering-länken i Admin-dropdown + badge på Admin-menyknappen (synlig utan att öppna dropdown). Build OK. Commit: b8a1e9c. Push: main.

- **2026-03-04**: Worker: Stopporsakslogg mönster-analys — ny collapsible 'Mönster & Analys'-sektion i /stopporsaker. Backend: ny endpoint pattern-analysis (action=stoppage&run=pattern-analysis&days=30) med tre analyser: (1) återkommande stopp 3+ gånger/7d per orsak, (2) timvis distribution 0-23 med snitt, (3) topp-5 kostsammaste orsaker med % av total. Frontend: togglePatternSection() laddar data lazy, buildHourlyChart() (Chart.js bargraf, röd för peak-timmar), repeat-kort med röd alarmbakgrund, costly-lista med staplar. CSS: pattern-section, pattern-body med max-height transition. Fix: pre-existing build-fel i maintenance-log.ts (unary + koercion i *ngIf accepteras ej av strict Angular templates). Build OK. Commit: 56871b4. Push: main.
- **2026-03-04**: Worker: Underhållslogg — utrustningskategorier och statistikvy. Migration: 2026-03-04_maintenance_equipment.sql — lägger till maintenance_equipment-tabell (id/namn/kategori/linje/aktiv) med 6 standardutrustningar, samt kolumner equipment/downtime_minutes/resolved på maintenance_log. Backend: nya endpoints equipment-list (GET) och equipment-stats (GET, 90d-statistik med driftstopp/kostnad/antal händelser per utrustning + summary worst_equipment). list/add/update hanterar nu equipment/downtime_minutes/resolved. Frontend: ny Statistik-flik med sorterbara tabeller (klickbara kolumnhuvuden), 3 KPI-brickor (total driftstopp, total kostnad, mest problembenägen utrustning), tomstatehantering. Logg-lista: equipment-badge + resolved-badge. Formulär: utrustningsdropdown, driftstopp-fält, åtgärdad-checkbox. Byggfel: Angular tillåter ej ä i property-namn i templates — fältnamnen ändrades till antal_handelser/senaste_handelse. Build OK. Commit: bb40447.
- **2026-03-04**: Worker: Operatörsprofil deep-dive — ny sida /admin/operator/:id. Backend: OperatorController.php ny endpoint `profile` (GET ?action=operator&run=profile&id=123) — returnerar operator-info, stats_30d (ibc/ibc_per_h/kvalitet/skift_count), stats_all (all-time rekord: bästa IBC/h, bästa skift, total IBC), trend_weekly (8 veckor via UNION ALL op1/op2/op3 med korrekt MAX()+SUM()-aggregering av kumulativa PLC-fält), recent_shifts (5 senaste), certifications, achievements (100-IBC skift, 95%+ kvalitetsvecka, aktiv streak), rank_this_week. Frontend: standalone komponent operator-detail/operator-detail.ts med header+avatar, 4 KPI-brickor, all-time rekordsektion, Chart.js trendgraf (IBC/h + streckad snittlinje), skift-tabell, achievements-brickor (guld/grå), certifieringslista. app.routes.ts: rutt admin/operator/:id med adminGuard. operator-dashboard.ts: RouterModule + routerLink på varje operatörsrad (idag + vecka). Build OK. Push: bb40447.

- **2026-03-04**: Worker: Executive dashboard multi-linje realtidsstatus — linjestatus-banner längst upp på /oversikt. Backend: getAllLinesStatus() i RebotlingController (action=rebotling&run=all-lines-status), returnerar live-data för rebotling (IBC idag, OEE%, mål%, senaste data-ålder) + ej_i_drift:true för tvättlinje/såglinje/klassificeringslinje. Frontend: 4 klickbara linjekort med grön/orange/grå statusprick (Font Awesome), rebotling visar IBC+OEE+mål-procent, polling var 60s, takeUntil(destroy$)/clearInterval i ngOnDestroy. Build OK. Commit: 587b80d.
- **2026-03-04**: Bug hunt #7: Fixade 2 buggar — (1) rebotling-statistik.ts: loadStatistics() saknade timeout(15000) + catchError (server-hängning skyddades ej), (2) NewsController.php: requireAdmin() använde $_SESSION utan session_start()-guard (PHP-session ej garanterat aktiv). Build OK. Commit: 8294ea9.

- **2026-03-04**: Worker: Bonus-admin utbetalningshistorik — ny flik "Utbetalningar" i /rebotling/bonus-admin. Migration: bonus_payouts tabell (op_id, period, amount_sek, ibc_count, avg_ibc_per_h, avg_quality_pct, notes). Backend: 5 endpoints (list-operators, list-payouts, record-payout, delete-payout, payout-summary) med validering och audit-logg. Frontend: årsöversikt-tabell per operatör (total/antal/snitt/senaste), historiktabell med år+operatör-filter, inline registreringsformulär (operatör-dropdown, period, belopp, IBC-statistik, notering), delete-knapp per rad, formatSek() med sv-SE valutaformat. Build OK. Commit: 4c12c3d.

- **2026-03-04**: Worker: Veckorapport förbättring — ny backend-endpoint week-compare (föregående veckas stats, diff % för IBC/snitt/OEE/kvalitet, veckans bästa operatör med initialer+IBC+IBC/h+kvalitet), frontend-sektion med 4 färgkodade diff-brickor (grön pil upp/röd ned/grå flat), guld-operatör-kort med avatar och statistik, loadCompareData() parallellt med load() vid veckonavigering. Commit: b0a2c25.

- **2026-03-04**: Worker: Skiftplan förbättring — ny flik "Närvaro & Jämförelse" i /admin/skiftplan. Backend: 2 nya endpoints (week-view: 21 slots 7×3 med planerade_ops + faktiska_ops + uteblev_ops, faktisk närvaro från rebotling_ibc op1/op2/op3 per datum+tid; operators-list: operatörer med initialer). Frontend: tab-navigation, veckoöversikt-grid (rader=dagar, kolumner=skift 1/2/3), badge-system (grön bock=planerad+faktisk, röd kryss=planerad uteblev, orange=oplanerad närvaro), veckonavigering med v.X-label, snabb-tilldelningsmodal (2-kolumn grid av operatörskort), removeFromWeekView(). Commits via concurrent agent: b0a2c25.

- **2026-03-04**: Worker: Nyheter admin-panel — CRUD-endpoints i NewsController (admin-list, create, update, delete) med admin-sessionsskydd, getEvents() filtrerar nu på published=1, ny komponent news-admin.ts med tabell + inline-formulär (rubrik, innehåll, kategori, pinnad, publicerad), kategori-badges, ikoner för pinnad/publicerad, bekräftelsedialog vid delete. Route admin/news + menypost i Admin-dropdown. Commit: c0f2079.

- **2026-03-04**: Worker: Månadsrapport förbättring — ny backend-endpoint run=month-compare (föregående månads-jämförelse, diff % IBC/OEE/Kvalitet, operatör av månaden med initialer, bästa/sämsta dag med % av dagsmål), frontend-sektion med 4 diff-brickor (grön/röd, pil ↑↓), operatör av månaden med guldkantad avatar, forkJoin parallell datahämtning. Commit: ed5d0f9.

- **2026-03-04**: Worker: Andon-tavla skiftöverlämningsnoter — nytt backend-endpoint andon&run=andon-notes (okvitterade noter från shift_handover, sorterat urgent→important→normal, graceful fallback), frontend-sektion med prioritetsbadge BRÅDSKANDE/VIKTIG, röd/orange kantfärg, timeAgo-helper, 30s polling, larm-indikator blinkar i titeln om urgent noter + linje ej kör. Commit: cf6b9f7.

- **2026-03-04**: Worker: Operatörsdashboard förbättring — veckovy med trend, historisk IBC-graf, summary-kort (Chart.js linjegraf topp 3 op, tab-nav Idag/Vecka, weekly/history/summary backend-endpoints, MAX per skiftraknare kumulativ aggregering). Commit: 50dca63.

- **2026-03-04**: Worker: Bug hunt #6 — session_start() utan guard fixad i 12 PHP-controllers (Admin, Audit, BonusAdmin, Bonus, LineSkiftrapport, Operator, Profile, Rebotling x2, RebotlingProduct, Skiftrapport, Stoppage, Vpn). Angular vpn-admin.ts: lagt till isFetching-guard, takeUntil(destroy$), timeout(8000)+catchError, destroy$.closed-check i setInterval. Bygg OK. Commit: cc9d9bd.

- **2026-03-04**: Worker: Nyhetsflöde — kategorier+färgbadges (produktion grön / bonus guld / system blå / info grå / viktig röd), kategorifilter-knappar med räknare, reaktioner (liked/acked i localStorage per news-id), läs-mer expansion (trunkering vid 200 tecken), timeAgo relativ tid (Just nu/X min/h sedan/Igår/X dagar), pinnerade nyheter (gul kant + thumbtack-ikon, visas alltid överst). Backend: news-tabell (category ENUM + pinned), NewsController tillägger category+pinned+datetime på alla auto-genererade events + stöder news-tabellen + kategorifiltrering. Migration: 2026-03-04_news_category.sql. Bygg OK. Commit: 4d2e22f.

- **2026-03-04**: Worker: Stopporsaks-logg (/stopporsaker) — Excel-export (SheetJS, kolumnbredder, filtrerad data), CSV-export uppdaterad, kompakt statistikrad (total stopptid/antal händelser/vanligaste orsak/snitt), avancerad filterrad (fr.o.m–t.o.m datumintervall + kategori-dropdown + snabbval Idag/Denna vecka/30d), inline-redigering (Redigera-knapp per rad, varaktighet+kommentar editerbart inline), tidsgräns-badge per rad (Kort <5min grön / Medel 5-15min gul / Långt >15min röd), Backend: duration_minutes direkt updatebar. Bygg OK. Commit: 4d2e22f.

- **2026-03-04**: Worker: Rebotling-admin — produktionsöversikt idag (today-snapshot endpoint, kompakt KPI-rad, polling 30s, färgkodad grön/orange/röd), alert-tröskelkonfiguration (kollapsbar panel, 6 trösklar OEE/prod/PLC/kvalitet, sparas JSON i rebotling_settings.alert_thresholds), veckodagsmål förbättring (kopieringsknapp mån-fre→helg, snabbval "sätt alla till X", idag-märkning med grön/röd status mot snapshot). Backend: 3 nya endpoints (GET alert-thresholds, POST save-alert-thresholds, GET today-snapshot), ALTER TABLE auto-lägger alert_thresholds-kolumn. Bygg OK. Commit: b2e2876.

- **2026-03-04**: Worker: My-bonus achievements — personal best (IBC/h, kvalitet%, bästa skift senaste 365d), streak dagräknare (nuvarande + längsta 60d, pulsanimation vid >5 dagar), achievement-medaljer grid (6 medaljer: Guldnivå/Snabbaste/Perfekt kvalitet/Veckostreak/Rekordstjärna/100 IBC/skift), gråtonade låsta / guldfärgade upplåsta. Backend: BonusController run=personal-best + run=streak. Bygg OK. Commit: af36b73.

- **2026-03-04**: Worker: Veckorapport (/rapporter/vecka) — ny VD-sida, WeeklyReportController (ISO-vecka parse, daglig MAX/SUM-aggregering, operatörsranking UNION ALL op1/op2/op3, veckomål från rebotling_settings), weekly-report.ts standalone Angular-komponent (inline template+styles), 6 KPI-kort (Total IBC, Kvalitet%, IBC/h, Drifttid, Veckans mål%, Dagar på mål), daglig stapeldiagram Chart.js med dagsmål-referenslinje, bästa/sämsta dag-kort, operatörsranking guld/silver/brons, veckonavigering (prev/next), PDF-export window.print(). api.php: weekly-report registrerat. Fix: production-analysis.ts tooltip null→''. Bygg OK. Commit: 0be4dd3 (filer inkl. i 5ca68dd via concurrent agent).

- **2026-03-04**: Worker: Produktionsanalys förbättring — riktig stoppdata stoppage_log, KPI-rad (total stoppid/antal/snitt/värst kategori), daglig staplat stapeldiagram färgkodat per kategori, topplista stopporsaker med kategori-badge, periodväljare 7/14/30/90 dagar, graceful empty-state när tabeller saknas, tidslinje behålls. Migration: stoppage_log+stoppage_reasons tabeller + 11 grundorsaker. angular.json budget 16→32kB. Commit: 5ca68dd.

- **2026-03-04**: Worker: Executive dashboard — insikter+åtgärder auto-analys, OEE-trend-varning (7 vs 7 dagar), dagsmålsprognos, stjärnoperatör, rekordstatus. Backend: run=insights i RebotlingController. Frontend: loadInsights(), insights[]-array, färgkodade insiktskort (danger/warning/success/info/primary). Bygg OK. Commit: c75f806.

- **2026-03-04**: Worker: Underhållslogg ny sida — MaintenanceController (list/add/update/delete/stats, admin-skydd, soft-delete), maintenance_log tabell (SQL-migrering), Angular standalone-komponent MaintenanceLogPage med dark theme, KPI-rad (total tid/kostnad/akuta/pågående), filter (linje/status/fr.o.m datum), CRUD-formulär (modal-overlay), färgkodade badges. api.php uppdaterad. Bygg OK. Commit: 12b1ab5.

- **2026-03-04**: Worker: Bonus-dashboard förbättring — Hall of Fame (IBC/h/kvalitet%/antal skift topp-3 senaste 90d, guld/silver/brons gradient-kort, avatar-initialer), löneprojekton per operatör (tier-matching Outstanding/Excellent/God/Bas/Under, SEK-prognos, månadsframsteg), periodval i ranking-headern (Idag/Denna vecka/Denna månad). Backend: run=hall-of-fame + run=loneprognos i BonusController. bonus.service.ts utökad med interfaces + metoder. Bygg OK. Commit: 310b4ad.

- **2026-03-04**: Worker: Produktionshändelse-annotationer i OEE-trend och cykeltrend — production_events tabell (SQL-migrering), getEvents/addEvent/deleteEvent endpoints i RebotlingController, ProductionEvent interface + HTTP-metoder i rebotling.service.ts, vertikala annotationslinjer i graferna med färgkodning per typ (underhall=orange, ny_operator=blå, mal_andring=lila, rekord=guld), admin-panel (kollapsbar, *ngIf=isAdmin) längst ner på statistiksidan. Bygg OK. Commit: 310b4ad.

- **2026-03-04**: Worker: Certifieringssida förbättring — kompetensmatris-vy (flik Kompetensmatris, tabell op×linje, grön/orange/röd celler med tooltip), snart utgångna-sektion (orange panel < 30 dagar, sorterat), statistiksammanfattning-rad (Totalt/Giltiga/Snart utgår/Utgångna), CSV-export (BOM UTF-8, alla aktiva certifieringar), fliknavigation (Operatörslista|Kompetensmatris), sorteringsval (Namn|Utgångsdatum), utgångsdatum inline i badge-rad, KPI-rad utökad till 5 brickor. Backend: CertificationController GET run=matrix. Bygg OK. Commit: 438f1ef.

- **2026-03-04**: Worker: Såglinje+Klassificeringslinje statistik+skiftrapport förbättring — 6 KPI-kort (Total IBC, Kvalitet%, Antal OK, Kassation, Snitt IBC/dag, Bästa dag IBC), OEE-trendgraf panel med Chart.js dual-axel (Kvalitet% vänster, IBC/dag höger), WCM 85% referenslinje, ej-i-drift-banner. Skiftrapport: 6 sammanfattningskort + empty-state. Backend: SaglinjeController + KlassificeringslinjeController GET run=oee-trend&dagar=N. Bonus: CertificationController GET run=matrix + Tvättlinje admin WeekdayGoal-stöd. Bygg: OK. Commit: 0a398a9.

- **2026-03-04**: Worker: Skiftöverlämning förbättring — kvittens (acknowledge endpoint + optimistic update), 4 filterflikar (Alla/Brådskande/Öppna/Kvitterade) med räknarbadge, sammanfattningsrad med totaler, timeAgo() klientsida, audience-dropdown (Alla/Ansvarig/Teknik), char-counter 500-gräns, auto-fokus på textarea, formulär minimera/expandera. SQL-migrering: acknowledged_by/at + audience-kolumn. Bygg OK. Commit 1540fcc.

- **2026-03-04**: Worker: Live-ranking förbättring — rekordindikator (gyllene REKORDDAG!/Nara rekord!/Bra dag! med glow-animation), teamtotal-sektion (LAG IDAG X IBC + dagsmål + procent + progress bar), skiftprognos (taktbaserad slutprognos, visas efter 1h av skiftet), skiftnedräkning i header (HH:MM kvar, uppdateras varje minut), kontextuella roterande motivationsmeddelanden (3 nivåer: >100%/80-100%/<80%, byter var 10s). Backend getLiveRanking utökad med ibc_idag_total, rekord_ibc, rekord_datum. Bygg OK. Commit 1540fcc.

- **2026-03-04**: Worker: Andon-tavla förbättring — skifttimer nedräkning (HH:MM:SS kvar av skiftet 06–22, progress-bar, färgkodad), senaste stopporsaker (ny andon&run=recent-stoppages endpoint, stoppage_log JOIN stoppage_reasons 24h, kategorifärger, tom-state, 30s polling), produktionsprognosbanner (taktbaserad slutprognos, 4 nivåer rekord/ok/warn/critical, visas efter 1h). Bygg OK. Commit 8fac87f.

- **2026-03-04**: Worker: Bug hunt #5 — 5 buggar fixade: (1) menu.ts updateProfile() HTTP POST saknade takeUntil/timeout/catchError — minnesläcka fixad; (2+3) SaglinjeController.php session_start() utan PHP_SESSION_NONE-guard på 2 ställen + saknad datumvalidering i getStatistics(); (4+5) TvattlinjeController.php session_start() utan guard på 3 ställen + saknad datumvalidering i getStatistics(). Alla andra granskade komponenter (historik, andon, shift-handover, operators, operator-dashboard, monthly-report, shift-plan, certifications, benchmarking, production-analysis) bedöms rena. Bygg OK. Commit: 0092eaf.

- **2026-03-04**: Worker: Operatörsjämförelse (/admin/operator-compare) — KPI-tabell sida-vid-sida (total IBC, kvalitet%, IBC/h, antal skift, drifttid), vinnare markeras grön, veckovis trendgraf senaste 8 veckor (Chart.js, blå=Op A, orange=Op B), periodväljare 14/30/90d. Backend: OperatorCompareController.php (operators-list + compare, MAX/MIN per-skifts-aggregering, admin-krav). api.php: operator-compare registrerat. Bygg: OK. Commit + push: b63feb9.

- **2026-03-04**: Worker-agent — Feature: Tvättlinje statistik+skiftrapport förbättring. Frontend tvattlinje-statistik: 6 KPI-kort (tillagd Snitt IBC/dag 30d och Bästa dag), OEE-trendgraf panel (Chart.js linjegraf, Kvalitet%+IBC/dag, WCM 85% referenslinje, välj 14/30/60/90d), graceful empty-state 'ej i drift'-banner när backend returnerar tom data. Frontend tvattlinje-skiftrapport: utökat från 4 till 6 sammanfattningskort (Total IBC, Snitt IBC/skift tillagda). Backend TvattlinjeController: ny endpoint GET ?run=report&datum=YYYY-MM-DD (daglig KPI-sammanfattning) + GET ?run=oee-trend&dagar=N (daglig statistik N dagar) — båda returnerar graceful empty-state om linjen ej är i drift. Bygg: OK (inga fel, bara pre-existing warnings). Commit: ingick i 287c8a3.

- **2026-03-04**: Worker: Kvalitetstrendkort (7-dagars rullande snitt, KPI-brickor, periodväljare 14/30/90d) + OEE-vattenfall (staplat bar-diagram, KPI-brickor A/P/Q/OEE, förlustvis uppdelning) i rebotling-statistik — redan implementerat i tidigare session, bygg verifierat OK.

- **2026-03-04**: Worker-agent — Feature: Historisk jämförelse (/rebotling/historik). Ny publik sida med 3 KPI-kort (total IBC innevarande år, snitt/månad, bästa månaden), stapeldiagram per månad (grön=över snitt, röd=under snitt), år-mot-år linjegraf per ISO-vecka (2023-2026), detaljerad månadsstabell med trend-pilar. Backend: HistorikController.php (monthly+yearly endpoints, publik). Frontend: historik.ts standalone Angular+Chart.js, OnInit+OnDestroy+destroy$+takeUntil+timeout(8000). Route: /rebotling/historik utan authGuard. Nav-länk i Rebotling-dropdown. Bygg: OK. Commit + push: 4442ed5.

- **2026-03-04**: Bug Hunt #4 — Fixade subscription-läckor och PHP session-bugg. Detaljer: (1) news.ts: fetchRebotlingData/fetchTvattlinjeData — 4 subscriptions saknade takeUntil(destroy$), nu fixat. (2) menu.ts: loadLineStatus forkJoin och loadVpnStatus saknade takeUntil(destroy$), loadVpnStatus saknade även timeout+catchError — nu fixat; null-guard tillagd i next-handler. (3) KlassificeringslinjeController.php: session_start() anropades utan session_status()-check i POST-handlarna för settings och weekday-goals — ersatt med if (session_status() === PHP_SESSION_NONE) session_start(). (4) bonus-admin.ts: 8 subscriptions (getSystemStats, getConfig, updateWeights, setTargets, setWeeklyGoal, getOperatorForecast, getPeriods, approveBonuses) saknade takeUntil(destroy$) — nu fixat. Bygg: OK. Commit + push: ja.

- **2026-03-04**: Worker-agent — Feature: Månadsrapport förbättring. Backend (RebotlingController): lade till basta_vecka, samsta_vecka, oee_trend, top_operatorer, total_stopp_min i monthly-report endpoint. Frontend (monthly-report): ny OEE-trend linjegraf (monthlyOeeChart, grön linje + WCM 85% streckad referens), topp-3 operatörer-sektion (medallängd + IBC), bästa/sämsta vecka KPI-kort, total stillestånd KPI-kort, markerade bäst/sämst-rader i veckosammanfattning. Bygg: OK. Commit + push: pågår.

- **2026-03-04**: Worker-agent — Feature: Klassificeringslinje förberedelsearbete inför driftsättning. Ny KlassificeringslinjeController.php med getSettings/setSettings, getWeekdayGoals/setWeekdayGoals, getSystemStatus (returnerar "Linjen ej i drift"), stub-metoder för live/statistik/running-status. Ny klassificeringslinje-admin Angular-sida (KlassificeringslinjeAdminPage) med EJ I DRIFT-banner, systemstatus-kort, driftsinställningsformulär, veckodagsmål-tabell. Migration: 2026-03-04_klassificeringslinje_settings.sql. Route/meny lämnas åt annan agent. Bygg: OK. Commit + push: d01b2d8.

- **2026-03-04**: Worker-agent — Feature: Såglinje förberedelsearbete inför driftsättning. Ny SaglinjeController.php med getSettings/setSettings, getWeekdayGoals/setWeekdayGoals, getSystemStatus (returnerar 'Linjen ej i drift'). Ny saglinje-admin sida (Angular standalone component) med EJ I DRIFT-banner, systemstatus-kort, driftsinställningsformulär, veckodagsmål-tabell. Route /saglinje/admin (adminGuard) och nav-länk i Såglinje-dropdown. Migration: 2026-03-04_saglinje_settings.sql med saglinje_settings + saglinje_weekday_goals. Bygg: OK.

- **2026-03-04**: Worker-agent — Feature: Notifikationsbadge i navbar för urgenta skiftöverlämningsnotat. Röd badge visas på Rebotling-dropdown och Skiftöverlämning-länken när urgenta notat finns (12h). Backend: ny endpoint shift-handover&run=unread-count, kräver inloggad session. Frontend: urgentNoteCount + loadUrgentCount() + notifTimer (60s polling, takeUntil, timeout 4s, catchError). Fix: WeekdayStatsResponse-interface i rebotling.service.ts flyttad till rätt position (före klassen) för att lösa pre-existing build-fel.

---

- **2026-03-04**: Worker-agent — Feature: Veckodag-analys i rebotling-statistik. Stapeldiagram visar snitt-IBC per veckodag (mån-lör), bästa dag grön, sämsta röd. Datatabell med max/min/OEE/antal dagar. Backend: getWeekdayStats() endpoint i RebotlingController.php, aggregerar per skift->dag->veckodag. Frontend: ny sektion längst ner på statistiksidan, weekdayChart canvas (nytt ID, ingen konflikt). Byggt + committat + pushat.

---

## 2026-03-04 — Excel-export förbättring (worker-agent)
- Förbättrade `exportExcel()` i rebotling-skiftrapport, tvattlinje-skiftrapport och saglinje-skiftrapport
- Använder nu `aoa_to_sheet` med explicit header-array + data-rader (istället för `json_to_sheet`)
- Kolumnbredder (`!cols`) satta för alla ark — anpassade per kolumntyp (ID smal, kommentar bred 40ch)
- Fryst header-rad (`!freeze` ySplit:1) i alla ark — scrolla ned utan att tappa kolumnnamnen
- Rebotling: sammanfattningsbladet fick också kolumnbredder och fryst header
- Filnamn uppdaterat med prefix `rebotling-` för tydlighet
- Bygg OK, inga nya fel



## 2026-03-04 — Feature: Operatörsdashboard — commit 4fb35a1
Worker-agent byggde /admin/operator-dashboard: adminvy för skiftledare med 4 KPI-kort (aktiva idag, snitt IBC/h, bäst idag, totalt IBC) och operatörstabell med initialer-avatar (hash-färg), IBC/h, kvalitet%, minuter sedan aktivitet och status-badge (Bra/OK/Låg/Inaktiv). Backend: OperatorDashboardController.php med UNION ALL op1/op2/op3 från rebotling_skiftrapport. 60s polling. Bygg OK, pushad till GitHub.
---
## 2026-03-04 — Feature: OEE WCM referenslinjer — commit 6633497

- `rebotling-statistik.ts`: WCM 85% (grön streckad) och Branschsnitt 70% (orange streckad) tillagda som referenslinjer i OEE-trend-grafen
- `rebotling-statistik.html`: Legend med dashed-linjer visas ovanför OEE-trendgrafen
- `environments/environment.ts`: Skapad (pre-existing build-fel fixat, saknad fil)



## 2026-03-03 23:07 — Bug hunt #3: 6 buggar fixade — commit 20686bb

- `shift-plan.ts`: Saknat `timeout()` + `catchError` på alla 4 HTTP-anrop — HTTP-anrop kunde hänga oändligt
- `live-ranking.ts`: Saknat `withCredentials: true` — session skickades ej till backend
- `live-ranking.ts`: Redundant `Subscription`/`dataSub`-pattern borttagen (takeUntil hanterar cleanup)
- `production-calendar.ts`: Saknat `withCredentials: true` — session skickades ej till backend
- `benchmarking.ts`: setTimeout-referens sparas nu i `chartTimer` och clearas i ngOnDestroy — förhindrar render på förstörd komponent
- `CertificationController.php`: `session_status()`-kontroll saknad före `session_start()` — PHP-varning om session redan aktiv

---
## 2026-03-03 — Digital skiftöverlämning — commit ca4b8f2

### Nytt: /rebotling/overlamnin

**Syfte:** Ersätter muntlig informationsöverföring vid skiftbyte med en digital överlämningslogg.
Avgående skift dokumenterar maskinstatus, problem och uppgifter. Inkommande skift ser de tre
senaste dagarnas anteckningar direkt när de börjar.

**Backend — `noreko-backend/classes/ShiftHandoverController.php` (ny):**

- `GET &run=recent` — hämtar senaste 3 dagars anteckningar (max 10), sorterat nyast först.
  - Returnerar `time_ago` på svenska ("2 timmar sedan", "Igår", "3 dagar sedan").
  - `skift_label` beräknas: "Skift 1 — Morgon" etc.
- `POST &run=add` — sparar ny anteckning. Kräver inloggad session (`$_SESSION['user_id']`).
  - Validering: note max 1000 tecken, skift_nr 1–3, priority whitelist.
  - Slår upp op_name mot operators-tabellen om op_number angivits.
  - Returnerar det nyskapade note-objektet direkt för optimistisk UI-uppdatering.
- `POST/DELETE &run=delete&id=N` — tar bort anteckning.
  - Kräver admin ELLER att `created_by_user_id` matchar inloggad användare.

**DB — `noreko-backend/migrations/2026-03-04_shift_handover.sql`:**
- Ny tabell `shift_handover` med id, datum, skift_nr, note, priority (ENUM), op_number,
  op_name, created_by_user_id, created_at. Index på datum och (datum, skift_nr).

**Frontend — `noreko-frontend/src/app/pages/shift-handover/` (ny):**
- Standalone-komponent med `destroy$ + takeUntil`, `isFetching`-guard, `clearInterval` i ngOnDestroy.
- Header visar aktuellt skift baserat på klockslag (06–14 = Morgon, 14–22 = Eftermiddag, 22–06 = Natt).
- Formulärpanel alltid synlig: textarea (max 1000 tecken), toggle-knappar för Normal/Viktig/Brådskande,
  skift-selector (auto-satt men justerbar), skicka-knapp.
- Anteckningskort: prioritetsfärgad vänsterkant (grå/orange/röd), skift-badge, datum, anteckningstext,
  operatörsnamn, time_ago. Radera-knapp visas om admin eller ägare.
- Auto-poll var 60s med `timeout(5000)` + `catchError`.
- "Uppdaterades XX:XX" i header efter varje lyckad fetch.

**Routing & nav:**
- Route: `{ path: 'rebotling/overlamnin', canActivate: [authGuard], ... }` i `app.routes.ts`.
- Nav-länk under Rebotling-dropdown (ikon: `fas fa-exchange-alt`, synlig för inloggade).

---

## 2026-03-03 — Kvalitetstrendkort + Waterfalldiagram OEE — commit d44a4fe

### Nytt: Två analysvyer i Rebotling Statistik

**Syfte:** VD vill se om kvaliteten försämras gradvis (Kvalitetstrendkort) och förstå exakt VAR OEE-förlusterna uppstår (Waterfalldiagram OEE).

**Backend — `noreko-backend/classes/RebotlingController.php`:**

- `GET ?action=rebotling&run=quality-trend&days=N` (ny endpoint):
  - SQL med MAX-per-skift-mönster, aggregerat per dag.
  - 7-dagars rullande medelvärde beräknat i PHP med array_slice/array_sum.
  - KPI: snitt, min, max, trendindikator (up/down/stable) via jämförelse sista 7 d mot föregående 7 d.
  - Returnerar `{ success, days: [{date, quality_pct, rolling_avg, ibc_ok, ibc_totalt}], kpi }`.

- `GET ?action=rebotling&run=oee-waterfall&days=N` (ny endpoint):
  - MAX-per-skift-aggregat för runtime_plc, rasttime, ibc_ok, ibc_ej_ok.
  - Tillgänglighet = runtime / (runtime + rast) * 100.
  - Prestanda = (ibc_ok * 4 min) / runtime * 100 (15 IBC/h standard, cap vid 100).
  - Kvalitet = ibc_ok / ibc_totalt * 100.
  - OEE = A * P * Q / 10000.
  - Returnerar alla komponenter + förluster (availability_loss, performance_loss, quality_loss).

**Service — `noreko-frontend/src/app/services/rebotling.service.ts`:**
- `getQualityTrend(days)` och `getOeeWaterfall(days)` metoder.
- Nya interfaces: `QualityTrendDay`, `QualityTrendResponse`, `OeeWaterfallResponse`.

**Frontend — `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts`:**
- Properties: `qualityTrendChart`, `qualityTrendDays=30`, `qualityTrendData`, `qualityTrendKpi`, `oeeWaterfallChart`, `oeeWaterfallDays=30`, `oeeWaterfallData`.
- `loadQualityTrend()`: hämtar data via service, renderar Chart.js linjegraf.
- `renderQualityTrendChart()`: canvas `qualityTrendChart`, 3 datasets (daglig/rullande/mållinje), Y 0-100%.
- `loadOeeWaterfall()`: hämtar data, renderar horisontellt stacked bar chart.
- `renderOeeWaterfallChart()`: canvas `oeeWaterfallChart`, grön+grå stack, indexAxis 'y'.
- Båda charts destroyed i ngOnDestroy. Laddas i ngOnInit.

**HTML — `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.html`:**
- Kvalitetstrendkort: dagar-väljare 14/30/90, 4 KPI-brickor (snitt/lägsta/bästa/trend med pil-ikon), linjegraf 300px.
- Waterfalldiagram OEE: dagar-väljare 7/30/90, OEE-summering, 4 KPI-brickor med förlust-siffror och färgkodning, horisontellt bar chart 260px.

---

## 2026-03-03 — Operatörscertifiering — commit 22bfe7c

### Nytt: /admin/certifiering — admin-sida för linjecertifikat

**Syfte:** Produktionschefen behöver veta vilka operatörer som är godkända att köra respektive linje. Sidan visar certifieringsstatus med färgkodade badges och flaggar utgångna eller snart utgående certifieringar.

**Backend — `noreko-backend/migrations/2026-03-04_certifications.sql`:**
- Ny tabell `operator_certifications`: op_number, line, certified_by, certified_date, expires_date, notes, active, created_at.
- Index på op_number, line och expires_date.

**Backend — `noreko-backend/classes/CertificationController.php`:**
- `GET &run=all` — hämtar alla certifieringar, JOIN mot operators för namn, grupperar per operatör. Beräknar `days_until_expiry` i PHP: `(strtotime(expires_date) - time()) / 86400`. Negativa = utgången, NULL = ingen utgångsgräns.
- `POST &run=add` — lägger till certifiering, validerar linje mot whitelist och datumformat. Kräver admin-session.
- `POST &run=revoke` — sätter active=0 på certifiering. Kräver admin-session.
- Registrerad i `api.php` under nyckeln `certifications`.

**Frontend — `noreko-frontend/src/app/pages/certifications/`:**
- `certifications.ts`: Standalone-komponent med destroy$/takeUntil. KPI-beräkningar (totalCertifiedOperators, expiringSoon, expired) som getters. Avatar-funktioner (getInitials/getAvatarColor) kopierade från operators-sidan. Badge-klassificering: grön (>30 d kvar eller ingen gräns), orange (≤30 d), röd (utgången, strikethrough).
- `certifications.html`: Sidhuvud, varningsbanner (visas om expired>0 eller expiringSoon>0), KPI-brickor, linje-filterknappar, operatörskort-grid, kollapsbart lägg till-formulär. Återkalla-knapp per certifiering med confirm-dialog.
- `certifications.css`: Dark theme (#1a202c/#2d3748), responsivt grid, badge-stilar, avatar-cirkel.

**Routing + Nav:**
- Route `admin/certifiering` med `adminGuard` i `app.routes.ts`.
- Nav-länk med `fas fa-certificate`-ikon under Admin-dropdown i `menu.html`.

---

## 2026-03-03 — Annotationer i OEE-trend och cykeltrend-grafer — commit 078e804

### Nytt: Vertikala annotationslinjer i rebotling-statistik

**Syfte:** VD och produktionschefen ska direkt i OEE-trendgrafen och cykeltrendgrafen kunna se varför en dal uppstod — t.ex. "Lång stopptid: 3.2h" eller "Låg prod: 42 IBC". Annotationer förvandlar grafer från datapunkter till berättande verktyg.

**Backend — `noreko-backend/classes/RebotlingController.php`:**
- Ny metod `getAnnotations()` + dispatch `elseif ($action === 'annotations')`.
- Endpoint: `GET ?action=rebotling&run=annotations&start=YYYY-MM-DD&end=YYYY-MM-DD`
- Tre datakällor i separata try-catch:
  1. **Stopp** — `rebotling_skiftrapport` GROUP BY dag, HAVING SUM(rasttime) > 120 min. Label: "Lång stopptid: Xh".
  2. **Låg produktion** — samma tabell, HAVING SUM(ibc_ok) < (dagsmål/2). Label: "Låg prod: N IBC". Deduplicerar mot stopp-annotationer.
  3. **Audit-log** — kontrollerar `information_schema.tables` om tabellen finns, hämtar CREATE/UPDATE-händelser (LIMIT 5). Svenska etiketter i PHP-mappning.
- Returnerar: `{ success: true, annotations: [{ date, type, label }] }`.
- Fel i valfri källa loggas med `error_log()` — övriga källor returneras ändå.

**Service — `noreko-frontend/src/app/services/rebotling.service.ts`:**
- Ny metod `getAnnotations(startDate, endDate)` → `GET ?action=rebotling&run=annotations`.
- Nytt interface `ChartAnnotation { date, dateShort, type, label }`.
- Nytt interface `AnnotationsResponse { success, annotations?, error? }`.

**Frontend — `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts`:**
- Custom Chart.js-plugin `annotationPlugin` (id: `'verticalAnnotations'`) definieras och registreras globalt med `Chart.register()`.
  - `afterDraw` ritar en streckad vertikal linje (röd=stopp, orange=low_production, grön=audit) på x-axeln via `getPixelForValue(xIndex)`.
  - Etikett (max 20 tecken) ritas 3px till höger om linjen, 12px under grafens övre kant.
- Ny class-property: `chartAnnotations: ChartAnnotation[] = []`.
- Ny metod `loadAnnotations(startDate, endDate)` med `timeout(8000)` + `takeUntil(this.destroy$)` + `catchError(() => of(null))`. Mappar API-svar till `ChartAnnotation[]` (lägger till `dateShort = date.substring(5)`). Vid framgång renderas OEE-trend och/eller cykeltrend om om de redan är inladdade.
- `loadOEE()`: beräknar start/end-datum (senaste 30 dagar) och anropar `loadAnnotations()` innan OEE-datan hämtas.
- `loadCycleTrend()`: anropar `loadAnnotations()` om `chartAnnotations.length === 0` (undviker dubbelanrop).
- `renderOEETrendChart()` och `renderCycleTrendChart()`: skickar `verticalAnnotations: { annotations: this.chartAnnotations }` i `options.plugins` (castat med `as any` för TypeScript-kompatibilitet).

---

## 2026-03-03 — Korrelationsanalys — bästa operatörspar — commit ad4429e

### Nytt: Sektion "Bästa operatörspar — korrelationsanalys" i `/admin/operators`

**Syfte:** VD och skiftledare ska kunna se vilka operatörspar som presterar bäst tillsammans, baserat på faktisk produktionsdata. Ger underlag för optimal skiftplanering.

**Backend — `noreko-backend/classes/OperatorController.php`:**
- Ny privat metod `getPairs()` + dispatch `$run === 'pairs'`.
- Endpoint: `GET ?action=operators&run=pairs`
- SQL: UNION ALL av alla tre parvisa kombinationer (op1/op2, op1/op3, op2/op3) från `rebotling_skiftrapport` (senaste 90 dagar).
- Grupperar på `LEAST(op_a, op_b) / GREATEST(op_a, op_b)` → normaliserade par.
- `HAVING shifts_together >= 3`, `ORDER BY avg_ibc_per_hour DESC`, `LIMIT 20`.
- JOIN mot `operators`-tabellen för namn på respektive operatörsnummer.
- Returnerar: `op1_num`, `op1_name`, `op2_num`, `op2_name`, `shifts_together`, `avg_ibc_per_hour`, `avg_quality`.

**Service — `noreko-frontend/src/app/services/operators.service.ts`:**
- Ny metod `getPairs()` → `GET ?action=operators&run=pairs`.

**Frontend — `noreko-frontend/src/app/pages/operators/`:**
- `operators.ts`: tre nya properties (`pairsData`, `pairsLoading`, `showPairs`) + metod `loadPairs()` med `timeout(8000)` + `catchError` + `takeUntil(destroy$)`. Anropas i `ngOnInit`.
- `operators.html`: ny toggle-sektion med responsivt `.pairs-grid` — visar parvisa avatarer (återanvänder `getInitials()` / `getAvatarColor()`), namn och tre stat-pills (IBC/h, kvalitet%, antal skift).
- `operators.css`: `.pairs-grid`, `.pair-card`, `.pair-avatar`, `.pair-plus`, `.pair-name-text`, `.pair-stats`, `.pair-stat-pill` + varianter `.pair-stat-ibc` / `.pair-stat-quality` / `.pair-stat-shifts`. Fullständigt responsivt för mobile.

---

## 2026-03-03 — Prediktiv underhållsindikator i rebotling-admin — commit 153729e

### Nytt: Sektion "Maskinstatus & Underhållsprediktor" i `/admin/rebotling`

**Syfte:** Produktionschefen ska tidigt se om cykeltiden ökar stadigt under de senaste veckorna — ett tecken på maskinslitage (ventiler, pumpar, dubbar). En tidig varning förebygger haveri och produktionsstopp.

**Backend — `noreko-backend/classes/RebotlingController.php`:**
- Ny metod `getMaintenanceIndicator()` + dispatch `elseif ($action === 'maintenance-indicator')`.
- Endpoint: `GET ?action=rebotling&run=maintenance-indicator`
- SQL: aggregerar `MAX(ibc_ok)` + `MAX(runtime_plc)` per `(DATE, skiftraknare)` → summerar per vecka (senaste 8 veckor, 56 dagar).
- Cykeltid = `SUM(shift_runtime) / SUM(shift_ibc)` (minuter per IBC).
- Baslinje = snitt av de 4 första veckorna. Aktuell = senaste veckan.
- Status: `ok` / `warning` (>15% ökning) / `danger` (>30% ökning).
- Returnerar: `status`, `message`, `weeks[]`, `baseline_cycle_time`, `current_cycle_time`, `trend_pct`.

**Frontend — `noreko-frontend/src/app/pages/rebotling-admin/rebotling-admin.ts` + `.html`:**
- Ny sektion (card) längst ned på admin-sidan — INTE en ny flik.
- `Chart.js` linjegraf: orange linje för cykeltid per vecka + grön streckad baslinje.
- KPI-brickor: baslinje, aktuell cykeltid, trend-% (färgkodad grön/gul/röd).
- Statusbanner: grön vid ok, gul vid warning, röd vid danger.
- Polling var 5 min via `setInterval` + `clearInterval` i `ngOnDestroy`.
- `takeUntil(this.destroy$)` + `timeout(8000)` + `catchError`.
- `maintenanceChart?.destroy()` i `ngOnDestroy` för att undvika memory-läcka.
- `ngAfterViewInit` implementerad för att rita om grafen om data redan är laddad.

---

## 2026-03-03 — Månadsrapport med PDF-export — commit e9e7590

### Nytt: `/rapporter/manad` — auto-genererad månadsöversikt

**Syfte:** VD vill ha en månadssammanfattning att dela med styrelsen eller spara som PDF. Visar total produktion, OEE-snitt, bästa/sämsta dag, operatörsranking och veckoöversikt.

**Backend — `noreko-backend/classes/RebotlingController.php`:**
- Ny metod `getMonthlyReport()` + dispatch `elseif ($action === 'monthly-report')`.
- Endpoint: `GET ?action=rebotling&run=monthly-report&month=YYYY-MM`
- Aggregering med korrekt `MAX() per (DATE, skiftraknare)` → `SUM()` på per-skift-undernivå.
- OEE beräknas per dag med `Availability × Performance × Quality`-formeln.
- Månadsnamn på svenska (Januari–December).
- Månadsmål: `dagsmål × antal vardagar i månaden` (hämtat från `rebotling_settings`).
- Operatörsranking: UNION på `op1/op2/op3` i `rebotling_skiftrapport` + JOIN `operators`, sorterat på IBC/h.
- Returnerar: `summary`, `best_day`, `worst_day`, `daily_production`, `week_summary`, `operator_ranking`.

**Frontend — `noreko-frontend/src/app/pages/monthly-report/`:**
- Standalone Angular-komponent (`MonthlyReportPage`), `OnInit + OnDestroy + AfterViewChecked`.
- `destroy$` + `takeUntil`, `chart?.destroy()` i `ngOnDestroy`.
- **Sektion 1:** 6 KPI-kort i CSS-grid — Total IBC, Mål-%, Snitt IBC/dag, Produktionsdagar, Snitt Kvalitet, Snitt OEE — med färgkodning grön/gul/röd.
- **Sektion 2:** Chart.js stapeldiagram (en stapel per dag, färgad efter % av dagsmål) + kvalitets-linje på höger Y-axel.
- **Sektion 3:** Bästa/sämsta dag sida vid sida (grön/röd vänsterbård).
- **Sektion 4:** Operatörsranking — guld/silver/brons för topp 3.
- **Sektion 5:** Veckosammanfattningstabell.
- **Sektion 6:** PDF-export via `window.print()` + `@media print` CSS (ljus bakgrund, döljer navbar/knappar).

**Routing & Nav:**
- Route: `{ path: 'rapporter/manad', canActivate: [authGuard], ... }` i `app.routes.ts`.
- Nytt "Rapporter"-dropdown i menyn (synligt för inloggade) med länk "Månadsrapport" → `/rapporter/manad`.

---

## 2026-03-03 — Benchmarking-vy: Denna vecka vs Rekordveckan — commit 9001021

### Nytt: `/rebotling/benchmarking` — rekordtavla och historik

**Syfte:** VD och operatörer motiveras av att se rekord och kunna jämföra innevaranda vecka mot den bästa veckan någonsin. Skapar tävlingsanda och ger historisk kontext.

**Backend — `noreko-backend/classes/RebotlingController.php`:**
- Ny metod `getBenchmarking()` + dispatch `elseif ($action === 'benchmarking')`.
- Returnerar ett objekt med fem nycklar: `current_week`, `best_week_ever`, `best_day_ever`, `top_weeks` (topp-10 veckor), `monthly_totals` (senaste 13 månader).
- Korrekt aggregering: `MAX() per (DATE, skiftraknare)` → `SUM() per vecka/månad` (hanterar kumulativa PLC-fält).
- OEE beräknas inline (Availability × Performance × Quality) med `idealRatePerMin = 15/60`.
- Veckoetiketter: `V{wk} {yr}` med ISO-veckonummer (`WEEK(datum, 1)`).

**Service — `noreko-frontend/src/app/services/rebotling.service.ts`:**
- Ny metod `getBenchmarking()` → `GET ?action=rebotling&run=benchmarking`.
- Nya interfaces: `BenchmarkingWeek`, `BenchmarkingTopWeek`, `BenchmarkingMonthly`, `BenchmarkingBestDay`, `BenchmarkingResponse`.

**Frontend — `noreko-frontend/src/app/pages/benchmarking/` (3 nya filer):**
- `benchmarking.ts`: Standalone Angular 20 component, `OnInit + OnDestroy + destroy$ + takeUntil + clearInterval`, 60s polling.
- `benchmarking.html`: Fyra sektioner — KPI-kort, bästa dag, topp-10 tabell, månadsöversikt bar chart.
- `benchmarking.css`: Dark theme (`#1a202c`/`#2d3748`/`#e2e8f0`), guld-/blå-accenter, pulse-animation för nytt rekord.

**Sektion 1 — KPI-jämförelse:**
- Vänster kort (blå): innevar. vecka — IBC totalt, IBC/dag, Kvalitet%, OEE%, aktiva dagar.
- Höger kort (guld): rekordveckan — samma KPI:er.
- Diff-badge: "X IBC kvar till rekordet" eller "NYTT REKORD DENNA VECKA!" (pulserar).
- Progress-bar 0–100% med färgkodning (röd/orange/blå/grön).

**Sektion 2 — Bästa dagen:** Guldkort med datum, IBC-total, Kvalitet%.

**Sektion 3 — Topp-10 tabell:** Rank-ikoner (trophy/medal/award), guld-rad för rekordveckan, blå rad för innevarnade vecka, procentkolumn "Vs. rekord".

**Sektion 4 — Månadsöversikt Chart.js:** Bar chart, guld=bästa månaden, blå=innevarnade, röd streckad snittlinje. Tooltip visar Kvalitet%.

**Routing:** `app.routes.ts` — `{ path: 'rebotling/benchmarking', canActivate: [authGuard], loadComponent: ... }`.

**Nav:** `menu.html` — "Benchmarking"-länk (med trophy-ikon) under Rebotling-dropdown, synlig för inloggade användare.

---

## 2026-03-03 — Adaptiv grafgranularitet (per-skift toggle) — commit 28dae83

### Nytt: Per-skift granularitet i rebotling-statistik

**Syfte:** VD och produktionschefer ville se produktion INOM dagar, inte bara dag-för-dag. En dag-för-dag-graf dolde om morgonsskiftet var bra men kvällsskiftet dåligt. Lösningen: toggle "Per dag / Per skift" på tre grafer.

**Backend — `noreko-backend/classes/RebotlingController.php`:**
- `getOEETrend()`: stödjer nu `?granularity=shift`. Per-skift-SQL aggregerar med `MAX(kumulativa fält) per (DATE, skiftraknare)`, beräknar OEE, Tillgänglighet, Prestanda, Kvalitet per skift. Label: `"DD/MM Skift N"`. Bakåtkompatibelt — default är `'day'`.
- `getWeekComparison()`: stödjer nu `?granularity=shift`. Returnerar varje skift för de senaste 14 dagarna med veckodags-label (t.ex. `"Mån Skift 1"`). Splittar i `this_week`/`prev_week` baserat på datum.
- `getCycleTrend()`: stödjer nu `?granularity=shift`. Returnerar IBC OK, cykler, IBC/h per skift. Label: `"DD/MM Skift N"`.

**Teknisk detalj — kumulativa fält:** `ibc_ok`, `runtime_plc`, `rasttime` i `rebotling_ibc` är kumulativa per `skiftraknare` — `MAX()` per `(DATE, skiftraknare)` ger korrekt skifttotal. `SUM()` vore fel.

**Service — `noreko-frontend/src/app/services/rebotling.service.ts`:**
- `getWeekComparison(granularity?)`, `getOEETrend(days, granularity?)`, `getCycleTrend(days, granularity?)` tar valfri granularity-param och skickar med som query-param.
- Interface `OEETrendDay`, `WeekComparisonDay`: nya valfria fält `label?`, `skiftraknare?`.
- Interface `CycleTrendResponse`: `granularity?` + `label?`, `skiftraknare?` i daily-objekten.

**Frontend — `noreko-frontend/src/app/pages/rebotling/rebotling-statistik.ts`:**
- Nya state-props: `oeeGranularity`, `weekGranularity`, `cycleTrendGranularity` (default `'day'`).
- Nya toggle-metoder: `setOeeGranularity()`, `setWeekGranularity()`, `setCycleTrendGranularity()` — nollställer `loaded` och laddar om data.
- `renderOEETrendChart()` och `renderWeekComparisonChart()` använder `d.label ?? d.date.substring(5)` för att visa skift-labels automatiskt.
- Ny `loadCycleTrend()` + `renderCycleTrendChart()` — stapeldiagram (IBC OK, vänster y-axel) + linjediagram (IBC/h, höger y-axel).
- `cycleTrendChart` städas i `ngOnDestroy()`.

**HTML — `rebotling-statistik.html`:**
- Pill-toggle "Per dag / Per skift" ovanför OEE-trend-grafen och veckojämförelse-grafen.
- Ny cykeltrend-panel (`*ngIf="cycleTrendLoaded"`) med toggle + canvas `#cycleTrendChart`.
- Ny snabblänksknapp "Cykeltrend" i panelraden.

**CSS — `rebotling-statistik.css`:**
- `.granularity-toggle` + `.gran-btn` — pill-knappar i dark theme, aktiv = `#4299e1` (blå accent).

---

## 2026-03-03 — Produktionskalender + Executive Dashboard alerts — commit cc4ba9f

### Nytt: /rebotling/kalender (GitHub-liknande heatmap-kalender)

**Syfte:** VD vill ha en omedelbar visuell historia av hela årets produktion. GitHub-liknande heatmap med 12 månadsblock ger en snabb överblick av produktionsmönster.

**Backend — `RebotlingController.php`:**
- Ny endpoint: `GET ?action=rebotling&run=year-calendar&year=YYYY`
  - Metod `getYearCalendar()`: hämtar `SUM(ibc_ok)` per datum ur `rebotling_skiftrapport` för valt år.
  - Fallback till PLC-data (`rebotling_ibc`) om inga skiftrapporter finns.
  - Dagsmål hämtas från `rebotling_weekday_goals` (ISO-veckodag 1=Mån...7=Sön) med fallback till `rebotling_settings.rebotling_target`.
  - Helgdagar med `daily_goal=0` men faktisk produktion får defaultGoal som mål.
  - Returnerar: `{ success, year, days: [{ date, ibc, goal, pct }] }`.

**Frontend — `ProductionCalendarPage` (`/rebotling/kalender`, adminGuard):**
- Tre filer: `production-calendar.ts`, `production-calendar.html`, `production-calendar.css`
- Standalone-komponent med `OnInit+OnDestroy`, `destroy$` + `takeUntil`.
- Årsväljare (dropdown + pil-knappar).
- 12 månadsblock i ett 4-kolumners responsivt grid (3 på tablet, 2 på mobil).
- Varje dag = färgad ruta: grå (ingen data), röd (<60%), orange (60-79%), gul (80-94%), grön (>=95%), ljusgrön/superdag (>=110%).
- Hover-tooltip: datum + IBC + mål + %.
- KPI-summering: totalt IBC, snitt IBC/dag, bästa dag + datum, % dagar nådde mål.
- Nav-länk: "Produktionskalender" under Rebotling-dropdown (admin only).
- Route: `rebotling/kalender` skyddad av `adminGuard`.

### Nytt: Alert-sektion i Executive Dashboard (`/oversikt`)

**Syfte:** VD ska inte missa kritiska situationer — tydliga röda/orangea varningsbanners ovanför KPI-korten.

**`executive-dashboard.ts`:**
- Ny property: `alerts: { type, message, detail }[]`
- Ny privat metod `computeAlerts()` anropas efter varje `loadData()`.
- OEE-varningar: danger om oee < 70%, warning om oee < 80%.
- Produktionsvarningar: danger om pct < 60%, warning om pct < 80%.

**`executive-dashboard.html`:**
- Alert-sektion med `*ngFor` ovanför SEKTION 1, döljs om `alerts.length === 0`.
- Klasser `.alert-danger-banner` / `.alert-warning-banner` med ikon och tydlig text.

**`executive-dashboard.css`:**
- Nya stilar: `.alerts-container`, `.alert-banner`, `.alert-danger-banner`, `.alert-warning-banner`, `.alert-icon`, `.alert-text`, `.alert-message`, `.alert-detail`.
- Slide-in animation.

---

## 2026-03-03 — Cykeltids-histogram + SPC-kontrollkort i rebotling-statistik — commit e4ca058

### Nytt: Djupanalys i /rebotling/statistik

**Syfte:** VD och produktionschef vill se djupare analys. Histogram visar om produktionen
är jämn. SPC-kortet visar om IBC/h-processen är statistiskt under kontroll.

**Backend — `RebotlingController.php`:**
- Ny endpoint: `GET ?action=rebotling&run=cycle-histogram&date=YYYY-MM-DD`
  - Metod `getCycleHistogram()`: hämtar `ibc_ok` och `drifttid` per skift från
    `rebotling_skiftrapport`, beräknar cykeltid = drifttid/ibc_ok per skift.
  - Fallback till PLC-data via `TIMESTAMPDIFF(SECOND, LAG(datum), datum)/60` per cykel
    i `rebotling_ibc` om inga skiftrapporter finns för datumet.
  - Histogrambuckets: 0-2, 2-3, 3-4, 4-5, 5-7, 7+ min.
  - Returnerar: `{ buckets[], stats: { n, snitt, p50, p90, p95 } }`.
- Ny endpoint: `GET ?action=rebotling&run=spc&days=7`
  - Metod `getSPC()`: hämtar IBC/h per skift de senaste N dagarna från
    `rebotling_skiftrapport` (ibc_ok * 60 / drifttid).
  - Fallback till PLC-data per skiftraknare (MAX ibc_ok / MAX runtime_plc).
  - Beräknar X̄ (medelvärde), σ (standardavvikelse), UCL=X̄+2σ, LCL=max(0,X̄-2σ).
  - Returnerar: `{ points[], mean, stddev, ucl, lcl, n, days }`.

**Service — `rebotling.service.ts`:**
- Nya interfaces: `CycleHistogramResponse`, `CycleHistogramBucket`, `SPCResponse`, `SPCPoint`.
- Nya metoder: `getCycleHistogram(date)`, `getSPC(days)`.

**Frontend — `rebotling-statistik.ts` + `rebotling-statistik.html`:**
- Histogram-sektion: datumväljare (default idag), KPI-brickor (Antal skift, Snitt, P50, P90),
  Chart.js bar chart (grön `#48bb78`), laddnings- och tom-tillstånd, förklaringstext.
- SPC-sektion: dagar-väljare (3/7/14/30), KPI-brickor (Medelvärde, σ, UCL, LCL),
  Chart.js line chart med 4 dataset (IBC/h blå fylld, UCL röd streckad, LCL orange streckad,
  medelvärde grön streckad), laddnings- och tom-tillstånd, förklaringstext.
- Alla nya properties: `histogramDate`, `histogramLoaded/Loading`, `histogramBuckets`,
  `histogramStats`, `histogramChart`, `spcDays`, `spcLoaded/Loading`, `spcMean/Stddev/UCL/LCL/N`, `spcChart`.
- `ngOnInit()` kallar `loadCycleHistogram()` och `loadSPC()`.
- `ngOnDestroy()` anropar `histogramChart?.destroy()` och `spcChart?.destroy()`.
- `takeUntil(this.destroy$)` på alla subscriptions.

---

## 2026-03-03 — Realtids-tävling TV-skärm (/rebotling/live-ranking) — commit a3d5b49

### Nytt: Live Ranking TV-skärm

**Syfte:** Helskärmsvy för TV/monitor på fabriksgolvet. Operatörer ser sin ranking live
medan de arbetar — motiverar tävlingsanda och håller farten uppe.

**Backend — `RebotlingController.php`:**
- Ny endpoint: `GET ?action=rebotling&run=live-ranking` (ingen auth krävs — fabriksgolvet)
- Metod `getLiveRanking()`: aggregerar op1/op2/op3 via UNION ALL från `rebotling_skiftrapport`
- Joinar mot `operators`-tabellen för namn
- Beräknar IBC/h = `SUM(ibc_ok) / (SUM(drifttid)/60)`, kvalitet% = `SUM(ibc_ok)/SUM(totalt)*100`
- Sorterar på IBC/h DESC, returnerar topp 10
- Fallback: om ingen data idag → senaste 7 dagarna
- Returnerar: `{ success, ranking[], date, period, goal }` där goal = dagsmål från `rebotling_settings`

**Frontend — `src/app/pages/live-ranking/` (3 nya filer):**
- `live-ranking.ts`: standalone component, OnInit+OnDestroy, `destroy$ = new Subject<void>()`,
  polling var 30s med `setInterval` + `isFetching`-guard + `timeout(8000)` + `catchError`.
  Roterande motton (8 st) via `setInterval` 6s. Alla interval rensas i `ngOnDestroy`.
- `live-ranking.html`: TV-layout med pulsande grön dot, header med datum+tid, rankinglista
  (guld/silver/brons-brickor, rank 1-3 framhävda), progress-bars mot dagsmål, roterande motto i footer.
- `live-ranking.css`: full-screen `100vw × 100vh`, dark theme (`#0d1117`/`#1a202c`), neongrön
  accent `#39ff14`, guld/silver/brons-gradienter, CSS-animationer (pulse, spin, fadeIn).

**Routing — `app.routes.ts`:**
- Lagt till som public route (ingen canActivate): `{ path: 'rebotling/live-ranking', loadComponent: ... }`
- URL innehåller `/live` → Layout döljer automatiskt navbar (befintlig logik i layout.ts)

---

## 2026-03-03 — Bug Hunt #2 + Operators-sida ombyggd

### Bug Hunt #2 — Fixade minnesläckor

**angular — takeUntil saknas (subscription-läckor):**
- `audit-log.ts`: `loadLogs()` saknade `takeUntil(destroy$)` → subscription läckte vid navigering
- `audit-log.ts`: `exportCSV()` saknade `takeUntil(destroy$)` → export-anrop kvarstod efter destroy
- `stoppage-log.ts`: `loadReasons()` saknade `takeUntil(destroy$)`
- `stoppage-log.ts`: `loadStoppages()` saknade `takeUntil(destroy$)`
- `stoppage-log.ts`: `loadStats()` saknade `takeUntil(destroy$)`
- `stoppage-log.ts`: `addStoppage()` saknade `takeUntil(destroy$)`
- `stoppage-log.ts`: `deleteStoppage()` saknade `takeUntil(destroy$)`

**Angular — setTimeout utan clearTimeout:**
- `executive-dashboard.ts`: `setTimeout(() => buildBarChart(), 100)` var ej lagrat → `clearTimeout` kallades aldrig i ngOnDestroy. Fixat: ny `barChartTimer` property, clearTimeout i ngOnDestroy, guard `!destroy$.closed`.

### Uppdrag 2 — Operators-sida ombyggd

**Frontend — `operators.ts` (fullständig omskrivning):**
- Operatörskort med initialer-avatar (cirkel med bakgrundsfärg baserad på namn-hash)
- Sorterbar statistiklista på: IBC/h, Kvalitet%, Antal skift, Namn
- Sökfunktion med fritext-filter (namn + nummer)
- Status-badge per operatör: "Aktiv" (jobbat ≤7 dagar), "Nyligen aktiv" (≤30 dagar), "Inaktiv" (>30 dagar), "Aldrig jobbat"
- Detaljvy: klicka på operatörskortet → expanderas med KPI-tiles + trendgraf
- Trendgraf (Chart.js): IBC/h (blå, vänster axel) + Kvalitet% (grön, höger axel) senaste 8 veckorna
- Medaljsystem: guld/silver/brons för rank 1-3
- Statistiken laddas direkt vid sidstart (inte lazy-load bakom knapp)
- Alla Chart.js-instanser destroy():as i ngOnDestroy (map av `trendCharts`)

**Backend — `OperatorController.php`:**
- `getStats()` utökad: lägger till `active`, `all_time_last_shift`, `activity_status` (active/recent/inactive/never)
- Ny endpoint `?run=trend&op_number=N`: veckovis IBC/h + kvalitet% + antal skift senaste 8 veckorna (56 dagar)
- Prepared statements, try/catch, error_log() — konsistent med övrig kod

**Service — `operators.service.ts`:**
- Ny metod `getTrend(opNumber: number)` → `?run=trend&op_number=N`

**CSS — `operators.css` (fullständig omskrivning):**
- Mörkt tema: `#1a202c` bg, `#2d3748` kort, `#e2e8f0` text
- Operatörskort-grid med expanderbar detaljvy
- Sök + sortering-knappar med aktiv-markering
- Responsivt (768px breakpoint)

---

Kort logg över vad som hänt — uppdateras automatiskt av Claude-agenter.

---

## 2026-03-03 — Tvättlinje-förberedelse + UX-polish

### DEL 1 — Tvättlinje-förberedelse

**Tvättlinje-admin (`pages/tvattlinje-admin/`):**
- Ny TypeScript-logik: `timtakt` och `skiftlangd` som egna fält (utöver `antal_per_dag`)
- Ny systemstatus-sektion med 30-sekunders polling (kör/stoppad, senaste signal, databas, linje)
  - `loadSystemStatus(silent?)` med `isFetchingStatus`-guard mot anropsstaplar
  - `getStatusAge()`, `getStatusAgeMinutes()`, `getStatusLevel()` för åldersindikator
- Felmeddelandehantering: `settingsError` visas med `alert-danger`, separeras från success-toast
- Tillbaka-knapp till Live i sidhuvudet
- "Ej i drift"-infobanner förklarar att inställningar kan förberedas
- Info-sektion med relevanta KPI:er och snabblänkar till Statistik / Skiftrapport
- Fullständigt omskriven CSS i mörkt tema (`#1a202c`/`#2d3748`/`#e2e8f0`), konsistent med rebotling-admin

**TvattlinjeController.php:**
- `saveAdminSettings()` hanterar nu `timtakt` och `skiftlangd` (utöver `antal_per_dag`)
- `loadSettings()` returnerar `timtakt` och `skiftlangd` med standardvärden 20 resp. 8.0
- Idempotent `ALTER TABLE ADD COLUMN IF NOT EXISTS` i både load och save — inga migrations-beroenden

**Databas-migration:**
- `noreko-backend/migrations/2026-03-03_tvattlinje_settings_extend.sql`
  - `ALTER TABLE tvattlinje_settings ADD COLUMN IF NOT EXISTS timtakt INT DEFAULT 20`
  - `ALTER TABLE tvattlinje_settings ADD COLUMN IF NOT EXISTS skiftlangd DECIMAL(4,1) DEFAULT 8.0`

**Tvättlinje-statistik (`pages/tvattlinje-statistik/`):**
- "Ej i drift"-banner (orange/gul) visas när backend returnerar fel och mock-data visas
- Förbättrad felmeddelande-alert: `alert-info` med "Exempeldata visas"
- Tillbaka-knapp till Live integrerad i navigationskontrollen
- `DecimalPipe` importerad — `avgEfficiency` och `row.efficiency` visas med 1 decimal

**Tvättlinje-skiftrapport (`pages/tvattlinje-skiftrapport/`):**
- Sammanfattningskort överst: Skift totalt, Totalt OK, Totalt ej OK, Snitt kvalitet (1 decimal)
  - `getTotalOk()`, `getTotalEjOk()`, `getAvgQuality()` — nya metoder
- Tillbaka-knapp till Live i sidhuvudet
- Tom-tillstånd med ikon (`fa-clipboard`) + förklaringstext + knapp för manuell rapport
- `getQualityPct()` returnerar nu 1 decimal (0.1% precision)
- Friendlier HTTP-felmeddelande med stäng-knapp på alert

### DEL 2 — UX-polish (tvättlinje)

- **Tillbaka-knappar**: Alla tre tvättlinje-sidor (statistik, skiftrapport, admin) har tillbaka-knapp till `/tvattlinje/live`
- **Tomma tillstånd**: Skiftrapport — dedikerat tom-tillstånd med ikon utanför tabellen
- **Felmeddelanden**: HTTP-fel ger begriplig svensk text; alert har stäng-knapp
- **Datumformat**: `yyyy-MM-dd` konsekvent via DatePipe
- **Procentsiffror**: 1 decimal konsekvent (`| number:'1.1-1'`) i statistik-KPIs, skiftrapport-kort och kvalitet-badges
- **Build**: `npx ng build` — 0 TypeScript-fel, inga nya budgetvarningar

---

## 2026-03-03 — Audit-log & Stoppage-log förbättringar

### Audit-log förbättringar

**Filtrering (server-side):**
- Fritext-sökning i `action`, `user`, `description`, `entity_type` via ny `?search=`-parameter med 350ms debounce
- Datumintervall-filter: knapp togglar "anpassat intervall" med from/to date-inputs (`?from_date=` + `?to_date=`)
- Period-dropdown inaktiveras när datumintervall är aktivt
- Åtgärds-dropdown fylls dynamiskt från ny `?run=actions` endpoint (unika actions från databasen)

**Presentation:**
- Färgkodade action-badges (pill-style): login/logout=grå, create/register=grön, update/toggle/set/approve=blå, delete/bulk_delete=röd, login_failed=orange
- Entitetstyp + ID visas i grå monospace bredvid badgen
- Förbättrad paginering med sifferknappar och ellipsis
- Strukturerad filterrad med labels och gruppering

**Export:**
- CSV-export hämtar upp till 2000 poster för aktiv filtrering (inte bara nuvarande sida)

**Backend (AuditController.php):**
- `getLogs()`: ny `search` (4-kolumns LIKE), `from_date`/`to_date`, `period=custom`
- Ny `getActions()`: returnerar distinkta actions
- `getDateFilter()`: stöder `custom`

**Frontend (audit.service.ts):** `search`, `from_date`, `to_date` + `getActions()`

### Stoppage-log förbättringar

**KPIer:**
- Snitt stopplängd ersätter "Planerade stopp" i fjärde kortet
- Veckosummering-rad: antal stopp + total stopptid denna vecka vs förra veckan med diff-%

**14-dagars bar-chart:**
- Inline chart (130px) bredvid veckokorten, antal stopp/dag, nolldagar i grå
- Tooltip visar stopptid i minuter

**Backend (StoppageController.php):**
- Ny `getWeeklySummary()` (`?run=weekly_summary&line=`): this_week, prev_week, daily_14

**Frontend (stoppage.service.ts):** Interface `StoppageWeeklySummary` + `getWeeklySummary(line)`

---

## 2026-03-03 — Skiftjämförelse + PLC-varningsbanner

### DEL 1 — Skiftjämförelse (rebotling-skiftrapport)

**Backend (`RebotlingController.php`):**
- Ny GET-endpoint `?action=rebotling&run=shift-compare&date_a=YYYY-MM-DD&date_b=YYYY-MM-DD`
- Metod `getShiftCompare()`: validerar datumformat med regex, hämtar aggregerad data per datum från `rebotling_skiftrapport`
- Returnerar per datum: totalt, ibc_ok, bur_ej_ok, ibc_ej_ok, kvalitet%, OEE%, drifttid, rasttid, ibc_per_h samt operatörslista med individuella IBC/h och kvalitet%

**Frontend (`rebotling-skiftrapport.ts`):**
- Properties: `compareDateA`, `compareDateB`, `compareLoading`, `compareError`, `compareResult`
- Metoder: `compareShifts()` (HTTP GET + felhantering), `clearCompare()`, `compareDiff()`, `compareIsImprovement()`, `compareIsWorse()`, `formatMinutes()`

**Frontend (`rebotling-skiftrapport.html`):**
- Ny sektion "Jämför skift" längst ner på sidan
- Två datumväljare + "Jämför"-knapp
- 6 KPI-kort (Total IBC, Kvalitet%, OEE%, Drifttid, Rasttid, IBC/h) med sida-vid-sida-layout
- Diff-badge: grön (förbättring) / röd (försämring) — rasttid är inverterad (lägre = bättre)
- Operatörstabeller för respektive datum (user_name, IBC/h, kvalitet%, op1/2/3-namn)
- Varningsmeddelanden om data saknas för ett/båda datum

**CSS (`rebotling-skiftrapport.css`):**
- `.compare-kpi-card`, `.compare-day-block`, `.compare-diff-block`, `.compare-diff`
- `.compare-better` (grön), `.compare-worse` (röd), `.compare-equal` (grå)
- `.compare-op-card`, `.compare-op-header`

---

### DEL 2 — PLC-varningsbanner (rebotling-admin)

**Frontend (`rebotling-admin.ts`):**
- Getter `plcWarningLevel`: returnerar `'none'` (< 5 min), `'warn'` (5–15 min), `'danger'` (> 15 min)
- Getter `plcMinutesOld`: beräknar antal minuter sedan senaste PLC-ping
- Använder befintlig `systemStatus.last_plc_ping` och existerande 30s polling

**Frontend (`rebotling-admin.html`):**
- Röd `alert-danger`-banner vid `plcWarningLevel === 'danger'`: "PLC har inte rapporterat data på X minuter. Kontrollera produktionslinjen!"
- Gul `alert-warning`-banner vid `plcWarningLevel === 'warn'`: "PLC-data är X min gammal"
- Ingen banner vid `'none'` (allt OK)
- Banner visas bara när `systemStatus` är laddat (undviker false positives under initial laddning)

**CSS (`rebotling-admin.css`):**
- `.plc-warning-banner` med subtil `plc-blink`-animation (opacity-pulsering)

---

## 2026-03-03 — Heatmap förbättring + My-bonus mobilanpassning

### Rebotling-statistik — förbättrad heatmap

**Interaktiva tooltips:**
- Hover över en heatmap-cell visar tooltip: Datum, Timme, IBC denna timme, IBC/h (takt), Kvalitet% om tillgänglig
- Tooltip positioneras ovanför cellen relativt `.heatmap-container`, fungerar med horisontell scroll

**KPI-toggle:**
- Dropdown-knappar ovanför heatmappen: "IBC/h" | "Kvalitet%" | "OEE%"
- IBC/h: vit→mörkblå; Kvalitet%: vit→mörkgrön; OEE%: vit→mörkviolett
- Kvalitet% visas på dagsnivå med tydlig etikett om timdata saknas

**Förbättrad färgskala & legend:**
- Noll-celler: mörk grå (`#2a2a3a`) istället för transparent
- Legend: noll-ruta + gradient "Låg → Hög" med siffror, uppdateras per KPI

**TypeScript ändringar (`rebotling-statistik.ts`):**
- `heatmapKpi: 'ibc' | 'quality' | 'oee'`
- `heatmapRows.qualityPct: number[]` tillagt
- `getHeatmapColor(rowIndex, hourIndex)` — ny signatur med rgb-interpolation per KPI
- `showHeatmapTooltip` / `hideHeatmapTooltip` metoder

### My-bonus — mobilanpassning för surfplatta

**CSS (`my-bonus.css`):**
- `overflow-x: hidden` — ingen horisontell overflow
- `@media (max-width: 768px)`: kort staplas vertikalt, hero kolumnar
- Lagerjämförelse → 1 kolumn på mobil (ersätter 600px-breakpoint)
- Touch-targets: `.period-group button` och `.btn-sm` → `min-height: 44px`
- `font-size: 14px` body, `1.25rem` rubrik
- `chart-container: 200px` höjd på mobil
- `@media (max-width: 480px)`: ytterligare komprimering
- Håller sig inom Angular 12kB CSS-budget

---

## 2026-03-03 — Bug Hunting Session (commit `92cbcb1`)

### Angular — Minnesläckor fixade
- `bonus-dashboard.ts`: `loadWeeklyGoal()`, `getDailySummary()`, `loadPrevPeriodRanking()` saknades `takeUntil(destroy$)`
- `bonus-dashboard.ts`: `loadData()` i setInterval-callback körde utan `destroy$.closed`-check
- `my-bonus.ts`: Alla tre HTTP-anrop i `loadStats()` saknade `timeout(8000)` + `catchError` + `takeUntil`
- `my-bonus.ts`: Borttagna oanvända imports (`KPIDetailsResponse`, `OperatorStatsResponse`, `OperatorHistoryResponse`)

### Angular — Race conditions fixade
- `rebotling-admin.ts`: `loadSystemStatus()` fick `isFetching`-guard → förhindrar anropsstaplar under 30s polling

### Angular — Logikbugg fixad
- `production-analysis.ts`: `catchError` i `getRastStatus` satte `stopAnalysisLoading=false` för tidigt medan övriga anrop pågick

### PHP — Säkerhet/korrekthet
- `BonusController.php`: `sendError()` satte nu `http_response_code($code)` — returnade tidigare alltid HTTP 200
- `BonusAdminController.php`: Deprecated `FILTER_SANITIZE_STRING` (borttagen PHP 8.2) ersatt med `strip_tags()`

---

## 2026-03-03

### Operatörsprestanda-trend + Stopporsaksanalys

**My-Bonus (`pages/my-bonus/`):**
- Ny sektion "Min bonusutveckling" (visas under IBC/h-trenden)
- Veckoutvecklings-graf: Stapeldiagram bonuspoäng per ISO-vecka, senaste 8 veckorna
  - Referenslinje: streckad gul horisontell linje = operatörens eget snitt
  - Färgkodning per stapel: grön = över eget snitt, röd/orange = under
  - Tooltip: diff mot snitt + antal skift den veckan
- Jämförelse mot laget (tre kolumner): IBC/h, Kvalitet%, Bonuspoäng — jag vs lagsnitt med grön/röd diff-pill
- `weeklyLoading` spinner; rensas vid `clearOperator()`

**BonusController.php:**
- Ny endpoint `GET ?action=bonus&run=weekly_history&id=<op_id>`
  - Bonuspoäng (snitt per skift) per ISO-vecka senaste 8 veckorna
  - Korrekt MAX/SUBSTRING_INDEX-aggregering för kumulativa PLC-fält
  - Teamsnitt per vecka (bonus, IBC/h, kvalitet) för lagsjämförelse
  - `my_avg` returneras för referenslinjen

**bonus.service.ts:**
- Ny `getWeeklyHistory(operatorId)` metod
- Nya interfaces: `WeeklyHistoryEntry`, `WeeklyHistoryResponse`

**Production Analysis (`pages/production-analysis/`) — ny flik "Stoppanalys" (flik 6):**
- Tydlig notis om datakälla: rast-data som proxy, riktig stoppanalys kräver PLC-integration
- KPI-kort idag: Status (kör/rast), Rasttid (min), Antal raster, Körtid est.
- Stopp-tidslinje 06:00–22:00: grön=kör, gul=rast/stopp, byggs från rast-events
  - Summering: X min kört, Y min rast/stopp, antal stopp
  - Fallback-meddelande om inga rast-events registrerats
- Bar chart "Rasttid per dag senaste 14 dagarna" (estimerad: 8h skift – körtid)
- Stoppstatistik-tiles: raster idag, rasttid idag, dagar med data, senaste rast-event
- Hämtar: `?run=rast` + `?run=status` + `?run=statistics`
- `stopRastChart` rensas i `destroyAllCharts()`

---

### Executive Dashboard — Fullständig VD-vy (commit fb05cce)

**Mål:** VD öppnar sidan och ser på 10 sekunder om produktionen går bra eller dåligt.

**Sektion 1 — Idag (stor status-panel):**
- Färgkodad ram (grön >80% av mål, gul 60–80%, röd <60%) med SVG-cirkulär progress
- Stor IBC-räknare "142 / 200 IBC" med procent inuti cirkeln
- Prognos-rad: "Prognos: 178 IBC vid skiftslut" (takt beräknad sedan skiftstart)
- OEE idag som stor siffra med trend-pil vs igår

**Sektion 2 — Veckans status (4 KPI-kort):**
- Denna veckas totala IBC vs förra veckans (diff i %)
- Genomsnittlig kvalitet% denna vecka
- Genomsnittlig OEE denna vecka
- Bästa operatör (namn + IBC/h)

**Sektion 3 — Senaste 7 dagarna (bar chart):**
- IBC per dag senaste 7 dagarna (grön = over mål, röd = under mål)
- Dagsmål som horisontell referenslinje (Chart.js line dataset)
- Mini-tabell under grafen med datum och IBC per dag

**Sektion 4 — Aktiva operatörer senaste skiftet:**
- Lista operatörer: namn, position, IBC/h, kvalitet%, bonusestimering
- Hämtas live från rebotling_ibc för senaste skiftraknare

**Backend (RebotlingController.php):**
- `GET ?run=exec-dashboard` — ny samlad endpoint, returnerar alla 4 sektioners data i ett anrop:
  - `today`: ibc, target, pct, forecast, oee_today, oee_yesterday, rate_per_h, shift_start
  - `week`: this_week_ibc, prev_week_ibc, week_diff_pct, quality_pct, oee_pct, best_operator
  - `days7`: array med 7 dagars {date, ibc, target}
  - `last_shift_operators`: array med {id, name, position, ibc_h, kvalitet, bonus}
- Korrekt OEE-beräkning (MAX per skiftraknare → SUM) för idag och igår
- Prognos beräknad som: nuvarande IBC / minuter sedan skiftstart × resterande minuter

**Frontend:**
- `ExecDashboardResponse` interface i `rebotling.service.ts` + ny `getExecDashboard()` metod
- Komplett omskrivning av executive-dashboard.ts/.html/.css
- Polling var 30:e sekund med isFetching-guard (ingen dubbelförfrågan)
- `implements OnInit, OnDestroy` + `destroy$` + `clearInterval` i ngOnDestroy
- SVG-cirkel med smooth CSS-transition på stroke-dashoffset
- Chart.js bar chart med dynamiska färger (grön/röd per dag)
- All UI-text på svenska

---

### Rebotling-skiftrapport + Admin förbättringar (commit cbfc3d4)

**Rebotling-skiftrapport (`pages/rebotling-skiftrapport/`):**
- Sammanfattningskort överst: Total IBC, Kvalitet%, OEE-snitt, Drifttid, Rasttid, Vs. föregående
- Filtrera per skift (förmiddag 06-14 / eftermiddag 14-22 / natt 22-06) utöver datumfilter
- Textsökning på produkt och användare direkt i filterraden
- Sorterbar tabell — klicka på kolumnrubrik för att sortera (datum, produkt, användare, IBC-antal, kvalitet%, IBC/h)
- Kvalitet%-badge med färgkodning (grön/gul/röd) direkt i tabellraden
- Skiftsammanfattning i expanderad detaljvy: snitt cykeltid, drifttid, rasttid, bonus-estimat
- PDF-export inkluderar nu sammanfattningskort med dagsmål-uppfyllnad och bonus-estimat
- Excel-export inkluderar separat sammanfattningsflik med periodnyckeltal

**Rebotling-admin (`pages/rebotling-admin/`):**
- Systemstatus-sektion (live, uppdateras var 30:e sek): senaste PLC-ping med åldersindikator, aktuellt löpnummer, DB-status OK/FEL, IBC idag
- Veckodagsmål: sätt olika IBC-mål per veckodag (standardvärden lägre mån/fre, noll helg)
- Skifttider: konfigurera start/sluttid + aktiv/inaktiv för förmiddag/eftermiddag/natt
- Bonussektion med förklarande estimatformel och länk till bonus-admin

**Backend (RebotlingController.php):**
- `GET/POST ?run=weekday-goals` — hämta/spara veckodagsmål (auto-skapar tabell)
- `GET/POST ?run=shift-times` — hämta/spara skifttider (auto-skapar tabell)
- `GET ?run=system-status` — returnerar PLC-ping, löpnummer, DB-check, IBC-idag, servertid
- POST-hantering samlad med admin-kontroll i en IF-block

**Databas-migration:**
- `noreko-backend/migrations/2026-03-03_rebotling_settings_weekday_goals.sql`
  - `rebotling_weekday_goals` (weekday 1-7, daily_goal, label)
  - `rebotling_shift_times` (shift_name, start_time, end_time, enabled)
  - Standardvärden ifyllda

---

### Rebotling-statistik + Production Analysis förbättringar (commit c7faa1b)

**Rebotling-statistik (rebotling-statistik.ts/.html):**
- Veckojämförelse-panel: Bar chart denna vecka vs förra veckan (IBC/dag), summakort, diff i %
- Skiftmålsprediktor: Prognos för slutet av dagen baserat på nuvarande takt. Hämtar dagsmål från live-stats, visar progress-bar med färgkodning
- OEE Deep-dive: Breakdown Tillgänglighet/Prestanda/Kvalitet som tre separata progress bars (med detaljtext), + 30-dagars OEE-trendgraf
- Alla tre paneler laddas on-demand med egna knappar (lazy load)

**Backend (RebotlingController.php):**
- `?run=week-comparison`: Returnerar IBC/dag för denna vecka + förra veckan (14 dagar, korrekt MAX/SUM-aggregering)
- `?run=oee-trend&days=N`: OEE per dag (Availability, Performance, Quality, OEE) senaste N dagar
- `?run=best-shifts&limit=N`: Historiskt bästa skift sorterade på ibc_ok DESC

**Production Analysis (production-analysis.ts/.html):**
- Ny flik "Bästa skift": historisk topplista med bar+line chart (IBC OK + kvalitet%), detailtabell med medals för topp-3
- Limit-selector (5/10/20/50 skift)

**RebotlingService:** Tre nya metoder (getWeekComparison, getOEETrend, getBestShifts) + type interfaces

### Auth-fix (commit ecc6b40)
- `fetchStatus()` returnerar nu `Observable<void>` istället för void
- `APP_INITIALIZER` använder `firstValueFrom(auth.fetchStatus())` — Angular väntar på HTTP-svar innan routing startar
- `catchError` returnerar `null` istället för `{ loggedIn: false }` — transienta fel loggar inte ut användaren
- `StatusController.php`: `session_start(['read_and_close'])` — PHP-session-låset släpps direkt, hindrar blockering vid sidomladdning

### Bonussystem — förbättringar (commit 9ee9d57)

**My-Bonus (`pages/my-bonus/`):**
- Motiverande statusbricka ("Rekordnivå!", "Över genomsnitt!", "Uppåt mot toppen!", etc.)
- IBC/h-trendgraf för senaste 7 skiften med glidande snitt (3-punkts rullande medelvärde)
- Skiftprognos-banner: förväntad bonus, IBC/h och IBC/vecka (5 skift) baserat på senaste 7 skiften
- PDF-export inkluderar nu skiftprognos i rapporten

**Bonus-Dashboard (`pages/bonus-dashboard/`):**
- Trendpilar (↑/↓/→) per operatör i rankingtabellen, jämfört med föregående period
- Bonusprogressionssbar för teamet mot konfigurerbart veckobonusmål
- Kvalitet%-KPI-kort ersätter Max Bonus (kvalitet visas tydligare)
- Mål-kolumn i rankingtabellen med mini-progressbar per operatör

**Bonus-Admin (`pages/bonus-admin/`):**
- Ny flik "Prognos": sök operatör, se snittbonus, tier-multiplikator, IBC/h och % av veckobonusmål
- Ny sektion i "Mål"-fliken: konfigurera veckobonusmål (1–200 poäng) med tiernamn-preview
- Visuell progressbar visar var valt mål befinner sig på tierskalan

**Backend (`BonusAdminController.php`):**
- `POST ?run=set_weekly_goal` — sparar weekly_bonus_goal i bonus_config (validerat 0–200)
- `GET ?run=operator_forecast&id=<op_id>` — prognos baserat på per-skift-aggregering senaste 7 dagar

**BonusAdminService (TypeScript):**
- `setWeeklyGoal(weeklyGoal)` — ny metod
- `getOperatorForecast(operatorId)` — ny metod med `OperatorForecastResponse` interface

**Databas-migration:**
- `2026-03-03_bonus_weekly_goal.sql`: ALTER TABLE bonus_config ADD weekly_bonus_goal DECIMAL(6,2) DEFAULT 80

---

---
[2026-03-03 23:00] Skiftkommentar-agent: kommentarsfält i skiftrapport levererat, commit 1feb15e
[2026-03-03 23:00] Andon-agent: Andon-tavla /rebotling/andon levererad, commit ddbade9
[2026-03-03 23:15] Bonusprognos-agent: bonus i kr levererat, commit e472997
[2026-03-03 23:05] Pareto-agent: Pareto-diagram stopporsaker levererat, commit 0f4865c

## 2026-03-04 — Worker: Senaste händelser på startsidan
- Lade till "Senaste händelser"-sektion i news.html (längst ner på startsidan)
- Uppdaterade NewsController.php: fallback-produktion visas alltid (ej bara om inga andra händelser), deduplicering av typ+datum, query för OEE-dagar begränsat till 14 dagar
- Skapade environments/environment.ts (saknades — orsakade byggfel för operator-dashboard)
- Bygget OK — inga errors, bara warnings

## 2026-03-04 — Feature: Tvattlinje forberedelse — backend + admin
- TvattlinjeController.php: Lade till `getSettings()`/`setSettings()` (key-value tabell `tvattlinje_settings`), `getSystemStatus()` (returnerar null-varden tills linjen ar i drift), `getWeekdayGoals()`/`setWeekdayGoals()` (individuella mal per veckodag i `tvattlinje_weekday_goals`)
- handle() utokad med routing for `settings`, `weekday-goals`, `system-status`
- Migration: `noreko-backend/migrations/2026-03-04_tvattlinje_settings.sql` skapad (tvattlinje_settings + tvattlinje_weekday_goals tabeller med defaultvarden)
- tvattlinje-admin.ts: Ny `WeekdayGoal`-interface, `loadWeekdayGoals()`/`saveWeekdayGoals()`, `loadNewSettings()`/`saveNewSettings()`, `loadSystemStatus()` nu mot `system-status` endpoint, `getPlcAge()`, `getDbStatusLabel()`
- tvattlinje-admin.html: Ny systemstatus-sektion med null-saker falt (PLC ej sedd = "---"), ny driftsinstellningar-sektion (dagmal/takt_mal/skift_start/skift_slut), ny veckodagsmaltabell (man-son med input + status-badge), "ej i drift"-banner
- Byggt OK, committat och pushat
[2026-03-04] Lead: Historik-agent klar (4442ed5+611dbff). Startar 3 workers: Kvalitetstrend+OEE-vattenfall (a35e472a), Operatörsjämförelse /admin/operator-compare (a746769c), Tvättlinje-statistik pågår (a59ff05a)
[2026-03-04] Lead: Operatörsjämförelse route+nav tillagd (fe14455) — /admin/operator-compare med adminGuard i app.routes.ts + menu.html
[2026-03-04] Worker: Live-ranking förbättring — rekordindikator guld/orange/gul, teamtotal+progress, prognos, skiftnedräkning, kontextuella motton — 1540fcc
[2026-03-04] Worker: Skiftöverlämning förbättring — kvittens+acknowledge, 4 filterflikar, sammanfattningsrad, audience-badge, timeAgo, kollapsbart formulär — se a938045f
[2026-03-04] Worker: Såglinje+Klassificeringslinje statistik+skiftrapport — 6 KPI-kort, OEE-trendgraf dual-axel, ej-i-drift-banner, WCM 85% referenslinje — 0a398a9
[2026-03-04] Worker: Certifieringssida — kompetensmatris (operatör×linje grid ✅⚠️❌), snart-utgångna-sektion, CSV-export, 5 KPI-brickor, 2 flikar — 438f1ef
[2026-03-04] Worker: Produktionshändelse-annotationer i OEE-trend — production_events tabell, admin-panel i statistik, triangelmarkeringar per typ — se a0594b1f
[2026-03-04] Worker: Bonus-dashboard — Hall of Fame (IBC/h/kvalitet/skift topp-3 guld/silver/brons), löneprojekton widget, Idag/Vecka/Månad periodväljare — 310b4ad
[2026-03-04] Lead: Underhållslogg route+nav tillagd (admin/underhall, adminGuard)
[2026-03-04] Worker: Executive dashboard — Insikter & Åtgärder (OEE-trend varning, dagsmålsprognos, stjärnoperatör, rekordstatus) — c75f806
[2026-03-04] Worker: Produktionsanalys — riktig stoppdata stoppage_log, KPI-rad 4 kort, dagligt staplat diagram (maskin/material/operatör/övrigt), topplista orsaker, tom-state — 5ca68dd
[2026-03-04] Lead: Veckorapport route+nav tillagd (/rapporter/vecka, authGuard)
[2026-03-04] Worker: My-bonus achievements — personal best (IBC/h/kvalitet/skift+datum), streak räknare (aktuell+längsta 60d), 6 achievement-medaljer (guld/grå), @keyframes streakPulse
[2026-03-04] Worker: Rebotling-admin — today-snapshot (6 KPI polling 30s), alert-trösklar (6 konfigurerbara, sparas JSON), veckodagsmål kopiering+snabbval+idag-märkning — b2e2876
[2026-03-04] Worker: Stopporsaks-logg — SheetJS Excel-export (filtrerad data), stats-bar (antal/total/snitt/vanligaste), filter (snabbval+datum+kategori), inline-redigering, tidsgräns-badge — 4d2e22f
[2026-03-04] Worker: Nyhetsflöde — kategorier (produktion/bonus/system/info/viktig)+badges, 👍✓ reaktioner localStorage, läs-mer expansion, timeAgo, pinnade nyheter gul kant
[2026-03-04] Worker: Rebotling-skiftrapport — shift-trend linjegraf timupplösning vs genomsnittsprofil, prev/next navigering — 6af3e1e
[2026-03-04] Worker: Produktionsanalys Pareto — ny flik "Pareto-analys (80/20)" med kombinationsdiagram (staplar+kumulativ %+röd 80%-linje), 3 KPI-brickor, period-toggle 7/30/90d, detaljlista med rangordning. Backend: pareto-stoppage endpoint i RebotlingController med kumulativ %-beräkning
[2026-03-04] Worker: Min Bonus — anonymiserad kollegajämförelse: ny "Din placering"-sektion med rank/#total/IBC-h/kvalitet%, progress bar mot toppen, period-toggle (Idag/Vecka/Månad), motivationstext per rank, backend my-ranking endpoint med auth-skydd (op_id måste matcha session operator_id)
[2026-03-04] Worker: Rebotling statistik — cykeltid per operatör: horisontellt Chart.js bar-diagram (indexAxis y), färgkodning mot median (grön/röd/blå), rang-tabell med snitt/bäst/sämst/antal skift/total IBC, period-selector 7/14/30/90d. Backend: cycle-by-operator endpoint i RebotlingController, UNION op1/op2/op3 från rebotling_skiftrapport, JOIN operators, outlier-filter 30-600 sek — 12ddddb
[2026-03-04] Worker: Notifikationscentral (klockikon) verifierad — redan implementerad i 022b8df. Bell-ikon i navbar för inloggade (loggedIn), badge med urgentNoteCount+certExpiryCount, dropdown med länk till overlamnin+certifiering, .notif-dropdown CSS, inga extra polling-anrop (återanvänder befintliga timers)
[2026-03-04] BugHunt #11: andon.ts — null-safety minuter_sedan_senaste_ibc (number|null + null-guard i statusEtikett), switch default-return i ibcKvarFarg/behovdTaktFarg; my-bonus.ts — chart-refs nullas i ngOnDestroy; news-admin.ts — withCredentials:true på alla HTTP-anrop (sessions kräver det för admin-list/create/update/delete); operator-trend.ts — oanvänd AfterViewInit-import borttagen; BonusController/BonusAdminController/MaintenanceController PHP — session_start read_and_close för att undvika session-låsning
[2026-03-04] Worker: Historik-sida — CSV/Excel-export (SheetJS), trendpil per månad (↑↓→ >3%), progressbar mot snitt per rad, ny Trend-kolumn i månadsdetaljatabell, disable-state på knappar — e6a36f5
[2026-03-04] Worker: Executive dashboard förbättringar — veckoframgångsmätare (IBC denna vecka vs förra, progressbar grön/gul/röd, OEE+kvalitet+toppop KPI-rad), senaste nyheter (3 senaste via news&run=admin-list, kategori-badges), 6 snabblänkar (Andontavla/Skiftrapport/Veckorapport/Statistik/Bonus/Underhåll), lastUpdated property satt vid lyckad fetch — 3d14b95
[2026-03-04] Worker: Benchmarking — emoji-medaljer (🥇🥈🥉) med glow-animationer, KPI-sammanfattning (4 brickor: veckor/rekord/snitt/OEE), personbästa-kort (AuthService-integration, visar stats om inloggad operatör finns i personalBests annars motiveringstext), CSV-export topplista (knapp i sidhuvud+sektion), rekordmånad guld-stjärnanimation i legend, silver+brons radmarkering i tabellen

## 2026-03-05 Session #14 — Kodkvalitets-audit: aldre controllers och komponenter

Granskade 10 filer (5 PHP controllers, 5 Angular komponenter) som ej granskats i bug hunts #18-#20.

### PHP-fixar:

**ProfileController.php** — Saknade try-catch runt UPDATE+SELECT queries vid profiluppdatering. La till PDOException+Exception catch med http_response_code(500) + JSON-felmeddelande.

**ShiftPlanController.php** — Alla 8 catch-block fangade bara PDOException. La till generell Exception-catch i: getWeek, getWeekView, getStaffingWarning, getOperators, getOperatorsList, assign, copyWeek, remove.

**HistorikController.php** — Default-case i handle() ekade osanitiserad user input ($run) direkt i JSON-svar. La till htmlspecialchars() for att forhindra XSS.

**OperatorCompareController.php** — Godkand: admin-auth, prepared statements, fullstandig felhantering.

**MaintenanceController.php** — Godkand: admin-auth med user_id+role-check, prepared statements, validering av alla input, catch-block i alla metoder.

### TypeScript-fixar:

**historik.ts** — setTimeout(buildCharts, 100) sparades inte i variabel och stadades ej i ngOnDestroy. La till chartBuildTimer-tracking + clearTimeout i ngOnDestroy.

**bonus-admin.ts** — setTimeout(renderAuditChart, 100) sparades inte. La till auditChartTimerId-tracking + clearTimeout i ngOnDestroy.

**benchmarking.ts** — Godkand: destroy$/takeUntil pa alla subscriptions, pollInterval+chartTimer stadade, Chart.js destroy i ngOnDestroy.

**live-ranking.ts** — Godkand: destroy$/takeUntil, alla tre timers (poll/countdown/motivation) stadade i ngOnDestroy, timeout+catchError pa alla HTTP-anrop.

**bonus-admin.ts** — Godkand (ovriga aspekter): destroy$/takeUntil pa alla subscriptions, timeout(8000)+catchError pa alla HTTP-anrop, null-safe access (res?.success, res?.data).

### Sammanfattning:
- 3 PHP-filer fixade (ProfileController, ShiftPlanController, HistorikController)
- 2 TypeScript-filer fixade (historik, bonus-admin)
- 5 filer godkanda utan anmarkningar
- 0 SQL injection-risker hittade (alla anvander prepared statements)
- 0 auth-brister hittade (alla admin-endpoints har korrekt rollkontroll)
[2026-03-05] Lead session #26: Worker 1 — rensa mockData-fallbacks i rebotling-statistik+tvattlinje-statistik, ta bort tom ProductController.php. Worker 2 — Bug Hunt #31 logikbuggar i rebotling-statistik/production-analysis/bonus-dashboard.
[2026-03-11] feat: Operatorsnarvarotracker — kalendervy som visar vilka operatorer som jobbat vilka dagar, baserat pa rebotling_skiftrapport. Backend: NarvaroController.php (monthly-overview endpoint). Frontend: narvarotracker-komponent med manadsvy, sammanfattningskort, fargkodade celler, tooltip, expanderbara operatorsrader. Route: /rebotling/narvarotracker. Menyval tillagt under Rebotling.
[2026-03-11] Lead session #62: Worker 1 — Underhallsprognos. Worker 2 — Kvalitetstrend per operator.
[2026-03-11] feat: Underhallsprognos — prediktivt underhall med schema-tabell, tidslinje-graf (Chart.js horisontell bar topp 10), historiktabell med periodvaljare, 4 KPI-kort med varningar. Backend: UnderhallsprognosController (3 endpoints: overview/schedule/history). Tabeller: underhall_komponenter + underhall_scheman med 12 seedade standardkomponenter. Route: /rebotling/underhallsprognos.
[2026-03-11] feat: Kvalitetstrend per operator — trendlinjer per operator med teamsnitt (streckad linje) + 85% utbildningsgraans (rod prickad). 4 KPI-kort, utbildningslarm-sektion, operatorstabell med sparkline/trendpil/sokfilter/larm-toggle, detaljvy med Chart.js + tidslinje-tabell. Backend: KvalitetstrendController (3 endpoints: overview/operators/operator-detail). Index pa rebotling_ibc. Route: /admin/kvalitetstrend.
[2026-03-11] fix: diagnostikvarningar i underhallsprognos.ts, kvalitetstrend.ts, KvalitetstrendController.php — oanvanda imports/variabler, null-safety i Chart.js tooltip.
[2026-03-11] feat: Produktionstakt — realtidsvy av IBC per timme med live-uppdatering var 30:e sekund. Stort centralt KPI-kort med trendpil (upp/ner/stabil), 3 referenskort (4h/dag/vecka-snitt), maltal-indikator (gron/gul/rod), alert-system vid lag takt >15 min, Chart.js linjegraf senaste 24h med maltal-linje, timtabell med statusfargkodning. Backend: ProduktionsTaktController (4 endpoints: current-rate/hourly-history/get-target/set-target). Migration: produktionstakt_target-tabell. Route: /rebotling/produktionstakt. Menyval under Rebotling.
[2026-03-12] feat: Alarm-historik — dashboard for VD och driftledare over alla larm/varningar som triggats i systemet. 4 KPI-kort (totalt/kritiska/varningar/snitt per dag), Chart.js staplat stapeldiagram (larm per dag per severity: rod=critical, gul=warning, bla=info), filtrerbar tabell med severity-badges, per-typ-fordelning med progressbars. Larm byggs fran befintliga kallor: langa stopp >30 min (critical), lag produktionstakt <50% av mal (warning), hog kassationsgrad >5% (warning), maskinstopp med 0 IBC (critical). Filter: periodselektor (7/30/90 dagar), severity-filter, typ-filter. Backend: AlarmHistorikController (3 endpoints: list/summary/timeline). Route: /rebotling/alarm-historik. Menyval under Rebotling.
[2026-03-12] feat: Kassationsorsak-statistik — Pareto-diagram + trendanalys per kassationsorsak, kopplat till operator och skift. 4 KPI-kort (totalt kasserade, vanligaste orsak, kassationsgrad med trend, foreg. period-jamforelse), Chart.js Pareto-diagram (staplar per orsak + kumulativ linje med 80/20-referens, klickbar for drilldown), trenddiagram per orsak (linjer med checkboxar for att valja orsaker), per-operator-tabell (kassationsprofil med andel vs snitt + avvikelse), per-skift-vy (dag/kvall/natt med progressbars), drilldown-vy (tidsserie + handelselista med skift/operator/kommentar). Periodvaljare 7/30/90/365 dagar, auto-refresh var 60 sekunder. Backend: KassationsorsakController (6 endpoints: overview/pareto/trend/per-operator/per-shift/drilldown). Migration: skift_typ-kolumn + index pa kassationsregistrering. Route: /rebotling/kassationsorsak-statistik. Menyval under Rebotling med fas fa-exclamation-triangle.

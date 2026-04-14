# Mauserdb — Design Guide
> Gäller för alla statistik- och skiftrapport-sidor. Läs detta innan du gör UI-förändringar.

---

## Kärnprincip: Enkel överblick först

Operatören ska förstå "hur går det?" på **3 sekunder** utan att scrolla eller klicka.  
Detaljer finns — men de ska vara *dolda tills de behövs*.

**Tänk så här:**
- Primärvyn = trafikljus (grön/gul/röd) + 3–4 nyckeltal
- Flikar = för den som vill förstå mer
- "Visa avancerat"-knapp = för den som verkligen gräver djupt

---

## Layout-hierarki (top → bottom)

```
┌─────────────────────────────────────────────────────────┐
│  1. STATUSRAD (sticky, alltid synlig)                   │
│     Linjens namn · Skift · Tid · Statusdot 🟢/🟡/🔴     │
├─────────────────────────────────────────────────────────┤
│  2. KPI-KORT (4 st, alltid synliga)                     │
│     [IBC/skift] [Effektivitet%] [Körtid] [Stopp]        │
├─────────────────────────────────────────────────────────┤
│  3. PRIMÄRGRAF (1 st, alltid synlig)                    │
│     Produktionstakt — IBC per timme, senaste 8h          │
│     [Graf-väljare: Dag / Vecka / Månad] [Linje / Stapel]│
├─────────────────────────────────────────────────────────┤
│  4. FLIKAR (sekundär info)                              │
│     [Skiftrapporter] [OEE-trend] [Stopptider]           │
├─────────────────────────────────────────────────────────┤
│  5. AVANCERAT-KNAPP (dold som standard)                 │
│     ▼ Visa avancerad statistik                           │
│     (rådata, cykeltidsgraf, detaljtabell, etc.)          │
└─────────────────────────────────────────────────────────┘
```

---

## Vad som ALLTID syns (ingen scroll)

| Element | Innehåll | Storlek |
|---------|----------|---------|
| KPI: IBC producerade | Antal IBC detta skift | `2.5rem bold` |
| KPI: Effektivitet | X% med badge Bra/Godkänt/Låg | `2.5rem bold` |
| KPI: Körtid | Timmar i drift | `2.5rem bold` |
| KPI: Längsta stopp | Minuter (färgkodat) | `2.5rem bold` |
| Primärgraf | IBC/timme — senaste 8h | Full bredd |

## Vad som ALDRIG syns direkt (dolt under flik/knapp)

- Detaljerade tabeller med varje enskild IBC
- Rådata / CSV-export (under "Avancerat")
- Cykeltidshistogram
- Skiftjämförelser dag för dag
- OEE-komponenter (Availability/Performance/Quality uppdelat)
- Stopporsak-analys (Pareto) — under "Stopptider"-fliken

---

## Grafer — regler

### Graf-väljare (ska finnas på primärgraf)
Tre knappar i övre högra hörnet av grafen:
```
[Dag] [Vecka] [Månad]     [≡ Linje] [▦ Stapel]
```
- Spara vald vy i `localStorage` så den sitter kvar
- Default: Dag + Linje

### Chart.js inställningar (dark theme)
```typescript
const CHART_DEFAULTS = {
  plugins: {
    legend: { labels: { color: '#e2e8f0', font: { size: 12 } } },
    tooltip: { backgroundColor: '#2d3748', titleColor: '#90cdf4', bodyColor: '#e2e8f0' }
  },
  scales: {
    x: { grid: { color: 'rgba(255,255,255,0.06)' }, ticks: { color: '#a0aec0' } },
    y: { grid: { color: 'rgba(255,255,255,0.06)' }, ticks: { color: '#a0aec0' } }
  }
};
```

### Rätt graf per metric
| Metric | Graf | Motivering |
|--------|------|------------|
| IBC/timme (trend) | Line + area fill | Visar rörelse och riktning |
| Produktion vs mål | Bar + target-linje | Direkt jämförelse |
| OEE (nu) | Doughnut (3 segment) | A × P × Q direkt visuellt |
| Stopptider | Horizontal bar (Pareto) | Störst problem överst |
| Skift vs skift | Grouped bar | Sida vid sida |
| Körning/stopp tidslinje | Gantt/stacked timeline | När körde vi? |

---

## Färger

```
Bakgrund:     #1a202c (sida)
Kort:         #2d3748 (cards)
Text primär:  #e2e8f0
Text sekundär:#a0aec0

Grön (bra):   #48bb78   (OEE ≥85%)
Gul (okej):   #ecc94b   (OEE 60–85%)
Röd (dålig):  #fc8181   (OEE <60%)
Blå (neutral):#4299e1   (info, accent)
Guld (rekord):#f6ad55   (bästa dag, rekord)
```

---

## KPI-kort — exakt HTML-mönster

```html
<div class="kpi-card" [class.kpi-green]="val >= 85" [class.kpi-yellow]="val >= 60 && val < 85" [class.kpi-red]="val < 60">
  <div class="kpi-icon"><i class="fas fa-tachometer-alt"></i></div>
  <div class="kpi-value">{{ val | number:'1.0-1' }}<span class="kpi-unit">%</span></div>
  <div class="kpi-label">Effektivitet</div>
  <div class="kpi-badge">
    <span class="badge" [class.bg-success]="val >= 85" ...>Bra</span>
  </div>
</div>
```

KPI-kort ska INTE ha `onClick` — de är informativa, inte klickbara.  
Undantag: en diskret `→` som leder till relevant flik.

---

## Typografi

```css
.kpi-value    { font-size: 2.5rem; font-weight: 700; line-height: 1; }
.kpi-unit     { font-size: 1.2rem; font-weight: 400; margin-left: 2px; }
.kpi-label    { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; margin-top: 4px; }
.section-title{ font-size: 1rem; font-weight: 600; color: #a0aec0; text-transform: uppercase; letter-spacing: 0.08em; }
```

---

## "Visa avancerat"-mönster

```html
<!-- Avancerat-sektion (dold som standard) -->
<div class="advanced-toggle mt-4">
  <button class="btn btn-outline-secondary btn-sm" (click)="showAdvanced = !showAdvanced">
    <i class="fas" [class.fa-chevron-down]="!showAdvanced" [class.fa-chevron-up]="showAdvanced"></i>
    {{ showAdvanced ? 'Dölj avancerad statistik' : 'Visa avancerad statistik' }}
  </button>
</div>
<div *ngIf="showAdvanced" class="advanced-section mt-3">
  <!-- Rådata, detaljerade tabeller, etc. -->
</div>
```

---

## Animation och rörelse

- **Minimalt** — operatören ska inte bli distraherad
- KPI-värden: ingen animation vid uppdatering (ersätt direkt)
- Graf-uppdatering: smooth transition max 300ms
- Inga pulsande/blinkande element (undantag: röd statusdot vid aktivt larm)
- Ingen auto-scroll eller auto-navigering

---

## Responsivitet

- **Desktop-first** (men ska fungera på surfplatta)
- KPI-grid: `col-6 col-md-3` (2×2 på mobil, 1×4 på desktop)
- Graf: full bredd alltid
- Flikar: horisontell scroll på liten skärm (ej dropdown)

---

## Vad agenter INTE ska göra

- Lägga till fler KPI-kort utan att ta bort lika många
- Skapa nya flikar utan att det finns tydligt innehåll till dem
- Lägga animationer på saker som uppdateras ofta
- Göra tabeller som standard-vyn — tabeller hör hemma under flikar/avancerat
- Ändra live-sidor (**tvattlinje-live**, **rebotling-live**, etc.)

---

## Checklista innan commit

- [ ] Primärvyn ryms ovan folden på 1080p utan scroll
- [ ] Exakt 4 KPI-kort (inte fler, inte färre)
- [ ] Primärgrafen har graf-väljare (dag/vecka/månad + linje/stapel)
- [ ] Detaljerad data dold under flik eller "Visa avancerat"
- [ ] `ng build` utan TypeScript-fel
- [ ] Färger följer paletten ovan
- [ ] All text på svenska

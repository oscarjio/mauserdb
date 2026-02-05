# 🌙 Rebotling Statistik V2 - Dark Mode Edition

## ✨ Helt Omgjord med Dina Önskemål!

### 🎯 Nya Features

#### 1. **🌙 Mörkt Läge (Dark Mode)**
- Gradient bakgrund (mörk blå/lila)
- Glaskort med blur-effekt
- Neon accenter (cyan/blå)
- Smooth animationer
- Professionell gaming-känsla

#### 2. **📅 Interaktiv Kalender med Drill-Down**

**3 Visningslägen:**
- **År → Månader**: 12 rutor (Jan-Dec), klicka för att se månadsvy
- **Månad → Dagar**: 30-31 rutor, klicka för att se dagsvy
- **Dag → Timmar**: 24 rutor (0-23)

**I varje ruta ser du:**
- Antal cykler (stor siffra)
- Snitt cykeltid (liten text)
- Grön bakgrund = Produktion finns
- Blå glow = Vald period

**Interaktion:**
- **Enkelklick**: Markera/avmarkera period
- **Dubbelklick**: Öppna detaljvy (drill-down)
- **Markera flera**: Håll in och klicka på fler rutor

#### 3. **📈 Avancerad Graf**

**Linjediagram med:**
- **Cyan linje**: Faktisk cykeltid per period
- **Gul streckad linje**: Genomsnittlig cykeltid (medelcykeltid)
- **Färgad bakgrund**:
  - 🟢 Grön = Linjen körde
  - 🔴 Röd = Linjen stoppad
- **Hover tooltip**: Visa exakt data + status

**Anpassar sig automatiskt:**
- Årvy: Visa per månad
- Månadsvy: Visa per dag
- Dagsvy: Visa per timme

#### 4. **📊 Klickbar Tabell**

**Visar:**
- Period (månad/dag/timme)
- Antal cykler
- Snitt cykeltid
- Effektivitet (färgkodad)
- Total körtid

**Drill-Down:**
- Klicka på månad → Se dagar i månaden
- Klicka på dag → Se timmar på dagen
- Automatisk scroll till vald rad

**Footer:**
- Totalsumma för alla perioder
- Genomsnitt och totaler

#### 5. **🧭 Smart Navigation**

**Breadcrumb:**
- `2024` → `Januari` → `15`
- Klicka på nivå för att gå tillbaka
- Visuell feedback på aktiv nivå

**Pilar:**
- ← Föregående period
- → Nästa period
- Fungerar på alla nivåer

---

## 🎨 Design-features

### Färgschema (Mörkt Tema)
```
Bakgrund:     #1a1a2e → #16213e (gradient)
Kort:         rgba(30, 30, 30, 0.95) (glassmorfism)
Accent:       #00d4ff (cyan/neon blå)
Text:         #e0e0e0 (ljusgrå)
Success:      #22dd22 (neon grön)
Warning:      #ffaa00 (orange)
Danger:       #ff4444 (röd)
```

### Animationer
- Fade-in när sidan laddas
- Hover-effekter på alla kort
- Smooth transitions (0.3s ease)
- Scale-transform på kalenderrutor
- Glow-effekt på valda element

### Responsiv Design
- **Desktop**: 4 KPI-kort, 4-kolumns kalender
- **Tablet**: 2 KPI-kort, 3-kolumns kalender
- **Mobil**: 1 KPI-kort, 2-kolumns kalender

---

## 🚀 Användning

### Navigera mellan vyer

#### 1. Årvy (Standard)
```
┌─────┬─────┬─────┬─────┐
│ Jan │ Feb │ Mar │ Apr │
│ 145 │ 132 │ 150 │ 140 │
│ 4.2 │ 4.5 │ 4.1 │ 4.3 │
├─────┼─────┼─────┼─────┤
│ Maj │ Jun │ Jul │ Aug │
│  98 │ 120 │ 110 │ 125 │
└─────┴─────┴─────┴─────┘
```
- Se alla 12 månader
- Antal cykler + snitt cykeltid i varje ruta
- **Dubbelklicka** på månad för att öppna månadsvy

#### 2. Månadsvy
```
┌───┬───┬───┬───┬───┬───┬───┐
│ 1 │ 2 │ 3 │ 4 │ 5 │ 6 │ 7 │
│ 45│ 42│ 48│ 40│   │   │ 38│
├───┼───┼───┼───┼───┼───┼───┤
│ 8 │ 9 │10 │11 │12 │13 │14 │
│ 44│   │ 46│ 43│ 41│   │ 47│
└───┴───┴───┴───┴───┴───┴───┘
```
- Se alla dagar i månaden
- Tomma rutor = Ingen produktion
- **Dubbelklicka** på dag för att öppna dagsvy

#### 3. Dagsvy
```
┌─────┬─────┬─────┬─────┬─────┬─────┐
│ 6:00│ 7:00│ 8:00│ 9:00│10:00│11:00│
│  5  │  8  │  7  │  6  │  9  │  7  │
├─────┼─────┼─────┼─────┼─────┼─────┤
│12:00│13:00│14:00│15:00│16:00│17:00│
│  4  │  2  │  8  │  9  │  7  │  6  │
└─────┴─────┴─────┴─────┴─────┴─────┘
```
- Se timme för timme
- Antal cykler per timme

### Markera flera perioder

1. **Årvy**: Markera flera månader
   - Klicka på Jan, Mars, Juni
   - Grafen visar kombinerad data
   - Tabellen visar alla valda månader

2. **Månadsvy**: Markera flera dagar
   - Klicka på dag 5, 10, 15
   - Jämför specifika dagar

3. **Tabell-drill**:
   - Klicka på rad i tabellen
   - Öppnar automatiskt detaljvyn

---

## 📊 Graf-förklaring

### Linjediagram
```
Cykeltid (min)
     ↑
  6  |     ●●●╲
     |    ●    ╲●●●
  5  |   ●       ╲  ●
     |  ●         ╲  ╲●
  4  | ●           ●───●  ← Cyan linje
     |--------●●●●●-------- ← Gul streckad (snitt)
  3  |
     └─────────────────────→
        🟢🟢🟢🔴🔴🟢🟢🟢    Tid
```

**Vad du ser:**
- **Cyan linje**: Faktisk cykeltid varierar över tiden
- **Gul streckad**: Snittvärdet ligger runt 4.2 min
- **Grön bakgrund**: Timmar/dagar då linjen körde
- **Röd bakgrund**: Timmar/dagar då linjen var stoppad

**Hover för detaljer:**
```
Klockan 14:00
Cykeltid: 4.5 min
Snitt: 4.2 min
🟢 Linjen körde
```

---

## 🎮 Tangentbordsgenvägar

```
Ctrl+Shift+B  →  Build
Ctrl+Shift+D  →  Deploy
```

---

## 💾 Mock Data

Om backend inte svarar visas automatiskt testdata!

**Genererar:**
- 100 cykler över 30 dagar
- Realistiska cykeltider (3-5 min)
- On/off events
- Variation i effektivitet

Du kan testa sidan direkt utan backend!

---

## 🔮 Tekniska Detaljer

### Komponenter

**TypeScript Logic:**
- `viewMode`: year | month | day
- `periodCells[]`: Array med kalenderrutor
- `tableData[]`: Array med tabellrader
- `breadcrumb[]`: Navigation-trail
- Drill-down navigation
- Multi-select logic

**Chart.js Custom Plugin:**
```typescript
beforeDatasetsDraw: (chart) => {
  // Rita grön/röd bakgrund
  // Baserat på running-status
}
```

**Responsive Breakpoints:**
- Desktop: 992px+
- Tablet: 768px - 991px
- Mobile: < 768px

---

## 📱 Mobilanpassning

### Desktop
```
┌──────────────────────────────────────┐
│ [KPI] [KPI] [KPI] [KPI]             │
├──────────────┬───────────────────────┤
│  Kalender    │      Graf             │
│  (4x3)       │                       │
└──────────────┴───────────────────────┘
│          Tabell (scrollbar)          │
└──────────────────────────────────────┘
```

### Mobil
```
┌────────────────┐
│  [KPI]         │
│  [KPI]         │
│  [KPI]         │
│  [KPI]         │
├────────────────┤
│  Kalender      │
│  (2x16)        │
├────────────────┤
│  Graf          │
│  (300px hög)   │
├────────────────┤
│  Tabell        │
│  (scroll)      │
└────────────────┘
```

---

## 🐛 Felsökning

### Graf syns inte
```typescript
// Kontrollera att Canvas finns
if (!this.productionChartRef?.nativeElement) return;
```

### Kalender tom
```typescript
// Kolla mock data
this.loadMockData();
```

### Fel färger
```css
/* CSS variabler i rebotling-statistik.css */
--primary: #00d4ff;
--success: #22dd22;
--danger: #ff4444;
```

---

## 🎯 Nästa Steg (Framtida Features)

- [ ] Export till Excel
- [ ] Jämför operatörer
- [ ] Filtrera på produkt
- [ ] Real-time auto-refresh
- [ ] Dela statistik-länk
- [ ] Spara favorit-vyer
- [ ] Notifikationer vid avvikelser

---

## ✅ Checklista

- [x] Mörkt tema implementerat
- [x] Kalender med 3 nivåer
- [x] Drill-down navigation
- [x] Multi-select
- [x] Graf med bakgrundsfärger
- [x] Medelcykeltid-linje
- [x] Klickbar tabell
- [x] Breadcrumb navigation
- [x] Responsiv design
- [x] Mock data för testning
- [ ] Backend-endpoints klara
- [ ] Testa med riktig data
- [ ] Deploya till produktion

---

## 🚀 Deploy Nu!

```bash
# Build
Ctrl+Shift+B

# Deploy
Ctrl+Shift+D
```

Gå till: **Rebotling → Statistik**

---

**🌙 Statistiksidan är nu i dark mode och helt interaktiv!**

**Features:**
✅ Mörkt läge med glassmorfism
✅ År → Månad → Dag drill-down
✅ Klickbar kalender med cykler
✅ Graf med grön/röd bakgrund
✅ Streckad medelcykeltid-linje
✅ Interaktiv tabell
✅ Breadcrumb navigation
✅ Mobilanpassad
✅ Smooth animationer

**Lycka till! 🎉**

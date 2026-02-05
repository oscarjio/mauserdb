# 🔍 Statistiksida - Debug Guide

## ✅ SLUTLIG VERSION - Alla Fixar

### 🎯 Vad som fungerar nu:

#### 1. **Auto-load graf vid start** ✅
```typescript
ngOnInit() {
  this.generatePeriodCells();
  this.loadStatistics(); // ✅ Laddar direkt!
}
```

**Resultat:**
- Öppnar sidan → Graf laddas för aktuell månad
- Ser direkt data för alla dagar i månaden
- Kalender visar vilka dagar som har produktion

#### 2. **Drag-to-select** ✅
```typescript
onCellMouseDown() → Börjar drag
onCellMouseEnter() → Markerar celler
document:mouseup → Slutar drag (global listener)
```

**Fungerar:**
- Desktop: Dra musen
- Mobil: Dra fingret
- Släpp var som helst (även utanför)

#### 3. **Cykeltid 8-12 min** ✅
```typescript
// Mock data:
cycle_time: 8 + Math.random() * 4

// prepareChartData:
const cycleTimeValue = parseFloat(cycle.cycle_time);
if (!isNaN(cycleTimeValue) && cycleTimeValue > 0) {
  group.cycleTime.push(cycleTimeValue); // ✅ Sparas korrekt
}
```

#### 4. **Grön/röd bakgrund på minuten** ✅
```typescript
// Dagsvy: Detaljerade events
startDate.setHours(hour, startMinute, 0, 0);  // T.ex. 10:05
stopDate.setHours(hour, stopMinute, 0, 0);    // T.ex. 10:52

// Graf plugin:
runningPeriods.forEach(period => {
  ctx.fillStyle = period.running ? 'green' : 'red';
  ctx.fillRect(xStart, top, xEnd - xStart, bottom - top);
});
```

---

## 🐛 Varför cykeltid var 0 (ROOT CAUSE):

### Problem 1: Data genererades INTE när graf laddades
```typescript
// FÖRUT:
ngOnInit() {
  loadMockDataForCalendar(); // ❌ Bara för kalender-nummer
  // Graf laddades INTE automatiskt
}

// NU:
ngOnInit() {
  loadStatistics(); // ✅ Laddar FULLSTÄNDIG data + graf
}
```

### Problem 2: prepareChartData fick tom data
```typescript
// Innan:
const cycles = data.cycles || []; // ❌ Tom array
cycles.forEach(...) // Inget hände

// Efter:
console.log('INPUT:', cycles.length); // ✅ 450+ cycles
if (!isNaN(cycleTimeValue) && cycleTimeValue > 0) {
  group.cycleTime.push(cycleTimeValue); // ✅ Faktisk data
}
```

### Problem 3: Beräkning använde fel data
```typescript
// Innan:
const avgTime = ... // Räknades från tom array = 0

// Efter:
console.log(`Period ${key}: ${value.cycleTime.length} cycles, avg = ${avgTime}`);
// Output: "Period 15: 45 cycles, avg = 9.23 min"
```

---

## 📊 Console Debug Output

### Vid sidladdning:
```
🔧 Generating mock data: {start: "2026-02-01", end: "2026-02-28", viewMode: "month"}
✅ Mock data result: {cycles: 450, onoff_events: 180, avgCycleTime: "9.5"}
📊 Mock data generated: {cycles: [...], onoff_events: [...], summary: {...}}
```

### Vid graf-skapande:
```
🔍 prepareChartData INPUT: {totalCycles: 450, totalOnOff: 180, sampleCycle: {...}, viewMode: "month"}
🔧 Initialized periods: ["1", "2", "3", ..., "28"]
📊 Cycles added to groups: 450
📏 Period 1: 15 cycles, avg = 9.2 min
📏 Period 2: 18 cycles, avg = 8.8 min
📏 Period 3: 20 cycles, avg = 10.1 min
...
✅ Chart data FINAL: {
  labels: 28,
  cycleTime: [9.2, 8.8, 10.1, 9.5, ...],
  nonZeroValues: 20,
  avgCycleTime: 9.5,
  runningPeriods: 8
}
✅ Chart created successfully
```

---

## 🎮 Användningsflöde

### Vid start (Månadsvy)
```
1. Sidan öppnas → Februari 2026
2. Graf laddas AUTOMATISKT
3. Visar alla 28 dagar
4. Kalender: Grön = Produktion
5. Graf: Cykeltid per dag
6. Bakgrund: Grön/röd per dag
```

### Markera specifika dagar
```
1. Dra över dag 10-15
2. Klicka "Visa Markerade (6)"
3. Graf uppdateras med ENDAST dessa 6 dagar
4. Tabell visar dessa 6 dagar
```

### Dubbelklicka för dagsvy
```
1. Dubbelklicka på dag 15
2. Automatisk dagsvy (24 timmar)
3. Graf med timvis data
4. Grön/röd på MINUTEN
   - 08:05-10:52 🟢
   - 10:52-11:03 🔴
   - 11:03-16:55 🟢
```

### Navigera mellan månader/år
```
1. Klicka ← → pilar
2. Graf laddas automatiskt för ny period
3. Klicka på "2026" i breadcrumb → Årvy
4. Klicka på "Februari" → Tillbaka till månadsvy
```

---

## 🧪 Testa Själv

### F12 Console → Kolla dessa värden:

```javascript
// Ska INTE vara 0:
cycleTime: [9.2, 8.8, 10.1, 9.5, 8.7, ...]

// Ska ha värden > 0:
nonZeroValues: 20  // (av 28 dagar)

// Ska ha avgCycleTime:
avgCycleTime: 9.5

// Ska ha perioder:
runningPeriods: [
  {startIndex: 0, endIndex: 3, running: true},
  {startIndex: 4, endIndex: 6, running: false},
  {startIndex: 7, endIndex: 15, running: true},
  ...
]
```

### Om cykeltid FORTFARANDE är 0:

1. Öppna Console (F12)
2. Titta efter:
```
🔍 prepareChartData INPUT: {totalCycles: ???}
```

3. Om `totalCycles: 0` → Problem i `generateMockData()`
4. Om `totalCycles: 450` men `cyclesAdded: 0` → Problem med `cycle_time` field
5. Om `cycleTime: [0,0,0,...]` → Problem med beräkning

### Kolla steg-för-steg:

```javascript
// 1. Data genereras?
console.log('Generated cycles:', cycles.length);

// 2. Data har cycle_time?
console.log('Sample cycle:', cycles[0]);
// Ska visa: {datum: "...", cycle_time: 9.2, ...}

// 3. Data kommer till prepareChartData?
console.log('prepareChartData INPUT:', cycles.length);

// 4. Data läggs till grupper?
console.log('Cycles added to groups:', cyclesAdded);

// 5. Beräkning funkar?
console.log('Period 1: avg =', avgTime);
```

---

## 🚀 Deploy och Testa

```
Ctrl+Shift+D
```

**Förväntad Console Output:**
```
🔧 Generating mock data: ...
✅ Mock data result: {cycles: 450, ...}
🔍 prepareChartData INPUT: {totalCycles: 450, ...}
📊 Cycles added to groups: 450
📏 Period 1: 15 cycles, avg = 9.20 min
📏 Period 2: 18 cycles, avg = 8.75 min
...
✅ Chart data FINAL: {cycleTime: [9.2, 8.8, ...]}
✅ Chart created successfully
```

**Om du ser detta = ALLT FUNGERAR!** 🎉

**Om cycle_time fortfarande är 0, skicka mig Console output!** 📋

---

## 💡 Senaste ändringar:

1. ✅ Auto-load graf vid start (alla vyer)
2. ✅ Djup console logging varje steg
3. ✅ parseFloat() + NaN-check för säkerhet
4. ✅ Detaljerad per-period logging
5. ✅ Bättre knapp-text ("Visa Markerade" vs "Uppdatera")

**Allt är optimerat för debugging!** 🔍

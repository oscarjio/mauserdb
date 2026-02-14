# 🎯 Bonus System API Documentation

**Base URL:** `/noreko-backend/api.php?action=bonus`

---

## 📋 Endpoints

### 1. Operatörsprestanda
**GET** `/api.php?action=bonus&run=operator&id=<op_id>&period=week`

Hämtar prestanda för en specifik operatör.

**Parameters:**
- `id` (required): Operatör-ID (anställningsnummer)
- `period` (optional): `today`, `week`, `month`, `year`, `all` (default: `week`)
- `start` (optional): Start-datum (YYYY-MM-DD)
- `end` (optional): Slut-datum (YYYY-MM-DD)

**Response:**
```json
{
  "success": true,
  "data": {
    "operator_id": 12345,
    "position": "Tvättplats",
    "period": "week",
    "date_range": {
      "from": "2026-02-06",
      "to": "2026-02-13"
    },
    "summary": {
      "total_cycles": 150,
      "total_ibc_ok": 1425,
      "total_ibc_ej_ok": 75,
      "total_bur_ej_ok": 12,
      "total_hours": 38.5,
      "total_rast_hours": 4.2
    },
    "kpis": {
      "effektivitet": 95.00,
      "produktivitet": 47.25,
      "kvalitet": 97.89,
      "bonus_avg": 76.58,
      "bonus_max": 85.20,
      "bonus_min": 65.30
    },
    "daily_breakdown": [
      {
        "date": "2026-02-13",
        "cycles": 22,
        "ibc_ok": 210,
        "ibc_ej_ok": 10,
        "effektivitet": 95.45,
        "produktivitet": 48.20,
        "kvalitet": 98.10,
        "bonus_poang": 77.85
      }
    ]
  }
}
```

---

### 2. Bonus Ranking (Topplista)
**GET** `/api.php?action=bonus&run=ranking&period=week&limit=10`

Hämtar Top N operatörer baserat på bonuspoäng.

**Parameters:**
- `period` (optional): `today`, `week`, `month`, `year` (default: `week`)
- `limit` (optional): Antal operatörer (default: 10, max: 100)
- `start` / `end` (optional): Custom datumintervall

**Response:**
```json
{
  "success": true,
  "data": {
    "period": "week",
    "limit": 10,
    "rankings": {
      "overall": [
        {
          "rank": 1,
          "operator_id": 12345,
          "total_cycles": 150,
          "bonus_avg": 82.50,
          "effektivitet": 96.20,
          "produktivitet": 49.80,
          "kvalitet": 98.50,
          "total_ibc_ok": 1450,
          "total_hours": 38.5
        }
      ],
      "position_1": [...],  // Tvättplats
      "position_2": [...],  // Kontrollstation
      "position_3": [...]   // Truckförare
    }
  }
}
```

---

### 3. Team-statistik
**GET** `/api.php?action=bonus&run=team&period=week`

Hämtar team-översikt per skift.

**Parameters:**
- `period` (optional): `today`, `week`, `month`, `year` (default: `week`)
- `start` / `end` (optional): Custom datumintervall

**Response:**
```json
{
  "success": true,
  "data": {
    "period": "week",
    "aggregate": {
      "total_shifts": 14,
      "total_cycles": 2100,
      "total_ibc_ok": 19500,
      "avg_bonus": 75.80,
      "unique_operators": 18
    },
    "shifts": [
      {
        "shift_number": 145,
        "shift_start": "2026-02-13",
        "shift_end": "2026-02-13",
        "operators": [12345, 67890, 11223],
        "operator_count": 3,
        "cycles": 150,
        "total_ibc_ok": 1425,
        "total_ibc_ej_ok": 75,
        "total_bur_ej_ok": 12,
        "total_hours": 8.0,
        "kpis": {
          "effektivitet": 95.00,
          "produktivitet": 47.25,
          "kvalitet": 97.89,
          "bonus_avg": 76.58
        }
      }
    ]
  }
}
```

---

### 4. KPI-detaljer (Trenddata)
**GET** `/api.php?action=bonus&run=kpis&id=<op_id>&period=week`

Hämtar KPI-breakdown för visualisering (Chart.js-format).

**Parameters:**
- `id` (required): Operatör-ID
- `period` (optional): `today`, `week`, `month`, `year` (default: `week`)

**Response:**
```json
{
  "success": true,
  "data": {
    "operator_id": 12345,
    "period": "week",
    "chart_data": {
      "labels": ["2026-02-06", "2026-02-07", "2026-02-08"],
      "datasets": [
        {
          "label": "Effektivitet (%)",
          "data": [95.2, 94.8, 96.1],
          "borderColor": "rgb(75, 192, 192)",
          "backgroundColor": "rgba(75, 192, 192, 0.2)"
        },
        {
          "label": "Produktivitet (IBC/h)",
          "data": [48.5, 47.2, 49.8],
          "borderColor": "rgb(54, 162, 235)",
          "backgroundColor": "rgba(54, 162, 235, 0.2)"
        },
        {
          "label": "Kvalitet (%)",
          "data": [98.1, 97.5, 98.9],
          "borderColor": "rgb(255, 206, 86)",
          "backgroundColor": "rgba(255, 206, 86, 0.2)"
        }
      ]
    },
    "raw_data": [...]
  }
}
```

---

### 5. Operatörs-historik
**GET** `/api.php?action=bonus&run=history&id=<op_id>&limit=50`

Hämtar senaste cyklerna för en operatör.

**Parameters:**
- `id` (required): Operatör-ID
- `limit` (optional): Antal cykler (default: 50, max: 500)

**Response:**
```json
{
  "success": true,
  "data": {
    "operator_id": 12345,
    "count": 50,
    "history": [
      {
        "datum": "2026-02-13 14:35:22",
        "lopnummer": 1234,
        "shift": 145,
        "position": "Tvättplats",
        "produkt": 1,
        "ibc_ok": 1,
        "ibc_ej_ok": 0,
        "bur_ej_ok": 0,
        "runtime": 120,
        "kpis": {
          "effektivitet": 100.00,
          "produktivitet": 50.00,
          "kvalitet": 100.00,
          "bonus": 80.00
        }
      }
    ]
  }
}
```

---

### 6. Dagens sammanfattning
**GET** `/api.php?action=bonus&run=summary`

Hämtar översikt för dagens produktion.

**Response:**
```json
{
  "success": true,
  "data": {
    "date": "2026-02-13",
    "total_cycles": 220,
    "shifts_today": 3,
    "total_ibc_ok": 2050,
    "total_ibc_ej_ok": 110,
    "avg_bonus": 76.25,
    "max_bonus": 88.50,
    "unique_operators": {
      "tvattplats": 5,
      "kontroll": 5,
      "truck": 4
    }
  }
}
```

---

## 🔧 Felhantering

**Fel-response:**
```json
{
  "success": false,
  "error": "Felmeddelande här",
  "timestamp": "2026-02-13 18:30:45"
}
```

**Vanliga felkoder:**
- `Operatör-ID saknas` - Glömt `id` parameter
- `Ingen data hittades för operatör X` - Operatören har ingen data i perioden
- `Databasfel: ...` - Databas-anslutningsproblem

---

## 📊 Frontend Integration

**Exempel med fetch:**
```javascript
// Hämta operatörsprestanda
fetch('/noreko-backend/api.php?action=bonus&run=operator&id=12345&period=week')
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      console.log('KPIs:', data.data.kpis);
      console.log('Daily breakdown:', data.data.daily_breakdown);
    } else {
      console.error('Error:', data.error);
    }
  });

// Hämta topplista
fetch('/noreko-backend/api.php?action=bonus&run=ranking&period=month&limit=10')
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      const topOperators = data.data.rankings.overall;
      console.log('Top 10:', topOperators);
    }
  });
```

---

## ✅ Status

**Implementerat:** ✅ Alla endpoints funktionella
**Testat:** ⏳ Behöver testas med faktisk PLC-data efter migration
**Dokumentation:** ✅ Denna fil

---

## 📝 Nästa Steg

1. Kör databas-migration: `migrations/002_add_fx5_d4000_fields.sql`
2. Deploy uppdaterad `Rebotling.php` till produktion
3. Testa endpoints med faktisk data
4. Bygg frontend-komponenter som använder API:et

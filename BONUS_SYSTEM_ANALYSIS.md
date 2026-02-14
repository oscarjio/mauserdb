# 📊 Bonussystem - Analys & Optimering

**Datum:** 2026-02-13
**Baserat på:** Industristandard och best practices 2026

---

## 🔍 Nuvarande Implementation

### Formel
```
Bonus Poäng = (Effektivitet × 0.4) + (Produktivitet × 0.4) + (Kvalitet × 0.2)
```

### KPI-definitioner
1. **Effektivitet (40%):** `(IBC_OK / (IBC_OK + IBC_EJ_OK)) × 100`
   - Mäter andel godkända av total produktion

2. **Produktivitet (40%):** `(IBC_OK × 60) / runtime_minuter` (IBC/h)
   - Mäter output per timme (cappas vid 100)

3. **Kvalitet (20%):** `((IBC_OK - BUR_EJ_OK) / IBC_OK) × 100`
   - Mäter andel godkända utan burfel

---

## 📚 Industry Best Practices

Baserat på research från ledande källor ([Talentnet](https://www.talentnetgroup.com/vn/featured-insights/rewards/role-kpi-bonus-structure), [ExecViva](https://execviva.com/executive-hub/bonus-kpis), [VKS](https://vksapp.com/blog/top-9-manufacturing-kpis), [Cascade](https://www.cascade.app/blog/manufacturing-kpis)):

### Rekommenderade Viktningar

**Tier 1: Kvalitet först (Defect-sensitive)**
```
Kvalitet:       50%
Effektivitet:   30%
Produktivitet:  20%
```
*Används när kvalitetsproblem är kostsamma (t.ex. livsmedel, medicin)*

**Tier 2: Balanserad (Nuvarande)**
```
Effektivitet:   40%
Produktivitet:  40%
Kvalitet:       20%
```
*Bra för generell produktion med balanserade mål*

**Tier 3: Output-fokuserad**
```
Produktivitet:  50%
Effektivitet:   30%
Kvalitet:       20%
```
*När volym är kritisk och kvalitet är mindre problematisk*

### Multi-Tier Bonussystem (Rekommenderat)

Istället för linjär viktning, använd **stegade bonusnivåer**:

```
Nivå 1: Basbonus (70-79 poäng)    = 100% av bonus
Nivå 2: God prestanda (80-89)     = 125% av bonus
Nivå 3: Excellent (90-94)         = 150% av bonus
Nivå 4: Outstanding (95-100)      = 200% av bonus
```

---

## 🎯 Förbättringsförslag

### 1. Dynamisk Viktning per Produkt

Olika produkter har olika prioriteringar:

**FoodGrade (produkt=1):**
```php
'effektivitet' => 0.3,
'produktivitet' => 0.3,
'kvalitet' => 0.4  // Högre kvalitetskrav!
```

**NonUN (produkt=4):**
```php
'effektivitet' => 0.35,
'produktivitet' => 0.45,
'kvalitet' => 0.2  // Volym viktigare
```

**Tvättade IBC (produkt=5):**
```php
'effektivitet' => 0.4,
'produktivitet' => 0.35,
'kvalitet' => 0.25
```

### 2. Normalisering av Produktivitet

**Problem:** Nuvarande cappning vid 100 är arbiträr.

**Lösning:** Använd målbaserad normalisering:
```php
// Sätt mål per produkt (från databas)
$produktivitet_mal = [
    1 => 12.0,  // FoodGrade: 12 IBC/h
    4 => 20.0,  // NonUN: 20 IBC/h
    5 => 15.0   // Tvättade: 15 IBC/h
];

$norm_produktivitet = min(($faktisk_produktivitet / $mal) * 100, 120);
// Cap vid 120% för att belöna överprestation
```

### 3. Team Multiplier

Lägg till team-bonus baserat på helhetsprestation:

```php
$team_multiplier = 1.0;

// Om ALLA i teamet når >80 poäng
if ($all_team_members_above_80) {
    $team_multiplier = 1.1;  // +10% team bonus
}

// Om teamet når produktionsmål för dagen
if ($team_goal_achieved) {
    $team_multiplier += 0.05;  // +5% extra
}

$final_bonus = $bonus_poang * $team_multiplier;
```

### 4. Säkerhetspoäng (Safety KPI)

Lägg till säkerhetsincidenter som faktor:

```php
$safety_factor = 1.0;

// Penalty för säkerhetsincidenter
if ($safety_incidents_this_period > 0) {
    $safety_factor = 0.9;  // -10%
}

// Bonus för perioder utan incidenter (>30 dagar)
if ($days_without_incident > 30) {
    $safety_factor = 1.05;  // +5%
}
```

### 5. Erfarna Operatörer Bonus

Belöna mentorskap och träning:

```php
$mentorship_bonus = 0;

// Om operatör tränar nya (från HR-data)
if ($is_mentor && $trainees_count > 0) {
    $mentorship_bonus = 2.5 * $trainees_count;  // +2.5 poäng per trainee
}
```

---

## 🔢 Förbättrad Bonusformel

### Implementation

```php
private function calculateAdvancedKPIs(array $data, int $produkt = 1): array {
    $ibc_ok = $data['ibc_ok'] ?? 0;
    $ibc_ej_ok = $data['ibc_ej_ok'] ?? 0;
    $bur_ej_ok = $data['bur_ej_ok'] ?? 0;
    $runtime = max($data['runtime_plc'] ?? 1, 1);

    // Total produktion
    $total_produced = $ibc_ok + $ibc_ej_ok;

    // 1. Effektivitet
    $effektivitet = $total_produced > 0
        ? round(($ibc_ok / $total_produced) * 100, 2)
        : 0;

    // 2. Produktivitet (målbaserad)
    $produktivitet_actual = round(($ibc_ok * 60) / $runtime, 2);

    // Hämta mål från databas (fallback till defaults)
    $produktivitet_targets = [
        1 => 12.0,  // FoodGrade
        4 => 20.0,  // NonUN
        5 => 15.0   // Tvättade
    ];
    $target = $produktivitet_targets[$produkt] ?? 15.0;

    // Normalisera mot mål (max 120%)
    $produktivitet_norm = min(($produktivitet_actual / $target) * 100, 120);

    // 3. Kvalitet
    $kvalitet = $ibc_ok > 0
        ? round((($ibc_ok - $bur_ej_ok) / $ibc_ok) * 100, 2)
        : 0;

    // 4. Dynamisk viktning per produkt
    $weights = [
        1 => ['eff' => 0.30, 'prod' => 0.30, 'qual' => 0.40],  // FoodGrade
        4 => ['eff' => 0.35, 'prod' => 0.45, 'qual' => 0.20],  // NonUN
        5 => ['eff' => 0.40, 'prod' => 0.35, 'qual' => 0.25]   // Tvättade
    ];
    $weight = $weights[$produkt] ?? ['eff' => 0.4, 'prod' => 0.4, 'qual' => 0.2];

    // 5. Basbonus
    $base_bonus = round(
        ($effektivitet * $weight['eff']) +
        ($produktivitet_norm * $weight['prod']) +
        ($kvalitet * $weight['qual']),
        2
    );

    // 6. Tier Multiplier
    $tier_multiplier = 1.0;
    if ($base_bonus >= 95) {
        $tier_multiplier = 2.0;      // Outstanding
    } elseif ($base_bonus >= 90) {
        $tier_multiplier = 1.5;      // Excellent
    } elseif ($base_bonus >= 80) {
        $tier_multiplier = 1.25;     // God prestanda
    }

    // 7. Final bonus
    $final_bonus = round($base_bonus * $tier_multiplier, 2);

    return [
        'effektivitet' => $effektivitet,
        'produktivitet' => $produktivitet_actual,
        'produktivitet_normalized' => $produktivitet_norm,
        'produktivitet_target' => $target,
        'kvalitet' => $kvalitet,
        'bonus_base' => $base_bonus,
        'bonus_tier_multiplier' => $tier_multiplier,
        'bonus_poang' => $final_bonus,
        'weights_used' => $weight
    ];
}
```

---

## 📈 Exempel-scenarion

### Scenario 1: FoodGrade Production
```
Input:
  IBC_OK: 95
  IBC_EJ_OK: 5
  BUR_EJ_OK: 1
  Runtime: 120 min
  Produkt: 1 (FoodGrade)

Beräkning:
  Effektivitet:  95.00% (95/100)
  Produktivitet: 47.50 IBC/h (95*60/120)
  Prod (norm):   395.83% (47.5/12*100, capped at 120%)
  Kvalitet:      98.95% ((95-1)/95*100)

Viktning (FoodGrade): 30% / 30% / 40%
  Base = (95*0.3) + (120*0.3) + (98.95*0.4) = 28.5 + 36 + 39.58 = 104.08

Tier: 95+ = Outstanding (×2.0)
  Final Bonus = 104.08 × 2.0 = 208.16 (capped at 200)
```

### Scenario 2: NonUN High Volume
```
Input:
  IBC_OK: 160
  IBC_EJ_OK: 15
  BUR_EJ_OK: 3
  Runtime: 120 min
  Produkt: 4 (NonUN)

Beräkning:
  Effektivitet:  91.43% (160/175)
  Produktivitet: 80.00 IBC/h
  Prod (norm):   400% → 120% (capped)
  Kvalitet:      98.13%

Viktning (NonUN): 35% / 45% / 20%
  Base = (91.43*0.35) + (120*0.45) + (98.13*0.2) = 32.0 + 54.0 + 19.63 = 105.63

Tier: 95+ = Outstanding (×2.0)
  Final Bonus = 105.63 × 2.0 = 211.26 (capped at 200)
```

---

## 🎨 Dashboard Förbättringar

### 1. Bonus Breakdown Visualization
```
┌─────────────────────────────────────┐
│ Bonuspoäng: 87.5 / 100             │
│ ████████████████████░░░░ 87.5%     │
├─────────────────────────────────────┤
│ Effektivitet:    95.0 × 0.4 = 38.0 │
│ Produktivitet:   82.5 × 0.4 = 33.0 │
│ Kvalitet:        82.5 × 0.2 = 16.5 │
├─────────────────────────────────────┤
│ Tier: God Prestanda (80-89)        │
│ Multiplier: ×1.25                  │
│ FINAL: 87.5 × 1.25 = 109.4         │
└─────────────────────────────────────┘
```

### 2. Målvisualisering
```
Produktivitet: 47.5 IBC/h
Target: 12 IBC/h
████████████████████████████████████████ 395% av mål! 🎯
```

### 3. Jämförelse med Team
```
Din bonus:        87.5
Team genomsnitt:  79.2
Du ligger:        +8.3 poäng över snittet! ⬆️
```

---

## ⚠️ Validering & Edge Cases

### Implementera checks:
```php
// 1. Sanity checks
if ($ibc_ok < 0 || $ibc_ej_ok < 0 || $bur_ej_ok < 0) {
    throw new InvalidArgumentException('Negativa värden inte tillåtna');
}

// 2. Bur kan inte vara fler än godkända
if ($bur_ej_ok > $ibc_ok) {
    error_log("WARNING: bur_ej_ok ($bur_ej_ok) > ibc_ok ($ibc_ok)");
    $bur_ej_ok = $ibc_ok;  // Cap
}

// 3. Runtime måste vara >0
if ($runtime <= 0) {
    throw new InvalidArgumentException('Runtime måste vara > 0');
}

// 4. Produktivitet sanity check (max 200 IBC/h är orimligt)
if ($produktivitet_actual > 200) {
    error_log("ALERT: Extremely high productivity: $produktivitet_actual IBC/h");
}
```

---

## 📊 A/B Testing Rekommendation

### Testa flera formler parallellt i 1 månad:

**Grupp A (Nuvarande):**
- 40/40/20 viktning
- Linear scaling

**Grupp B (Förbättrad):**
- Dynamisk viktning per produkt
- Tier multipliers
- Målbaserad normalisering

**Grupp C (Hybrid):**
- 35/35/30 viktning (mer balanserad)
- Tier multipliers
- Team bonus

**Mät:**
- Operatörstillfredsställelse
- Produktivitetsutveckling
- Kvalitetsutveckling
- Rättviseuppfattning

---

## 🎯 Rekommendation

**Fas 1 (Omedelbart):**
1. ✅ Implementera målbaserad produktivitetsnormalisering
2. ✅ Lägg till tier multipliers
3. ✅ Förbättra dashboard med breakdown-vy

**Fas 2 (Nästa månad):**
1. Dynamisk viktning per produkt
2. Team multiplier
3. A/B testing av formler

**Fas 3 (Q2 2026):**
1. Safety KPI integration
2. Mentorship bonus
3. AI-baserad förutsägelse av bonuspotential

---

## 📚 Källor

Research baserat på industristandarder:
- [Talentnet - Performance-Based Bonus Structure](https://www.talentnetgroup.com/vn/featured-insights/rewards/role-kpi-bonus-structure)
- [ExecViva - Bonus KPIs Executive Guide](https://execviva.com/executive-hub/bonus-kpis)
- [VKS - Top 9 Manufacturing KPIs](https://vksapp.com/blog/top-9-manufacturing-kpis)
- [Cascade - 33 Manufacturing KPIs](https://www.cascade.app/blog/manufacturing-kpis)
- [Method - Manufacturing KPI Dashboard 2026](https://www.method.me/blog/manufacturing-kpi-dashboard/)

---

**Slutsats:** Nuvarande system är bra, men kan förbättras med målbaserad normalisering, tier multipliers och produkt-specifika viktningar för att bli world-class.

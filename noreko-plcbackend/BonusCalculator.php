<?php
/**
 * BonusCalculator.php
 * Avancerad bonusberäkningsmotor med tier multipliers, målbaserad normalisering
 * och produkt-specifika viktningar
 *
 * Version: 2.0 (Enhanced)
 * Datum: 2026-02-13
 */

class BonusCalculator {

    /**
     * Produktivitetsmål per produkt (IBC per timme)
     */
    private const PRODUCTIVITY_TARGETS = [
        1 => 12.0,  // FoodGrade - högre kvalitetskrav, lägre volym
        4 => 20.0,  // NonUN - volymproduktion
        5 => 15.0   // Tvättade IBC - mellannivå
    ];

    /**
     * Viktning per produkt [effektivitet, produktivitet, kvalitet]
     * Summan ska alltid bli 1.0
     */
    private const PRODUCT_WEIGHTS = [
        1 => ['eff' => 0.30, 'prod' => 0.30, 'qual' => 0.40],  // FoodGrade - kvalitet viktigast
        4 => ['eff' => 0.35, 'prod' => 0.45, 'qual' => 0.20],  // NonUN - produktivitet viktigast
        5 => ['eff' => 0.40, 'prod' => 0.35, 'qual' => 0.25],  // Tvättade - balanserat
        'default' => ['eff' => 0.40, 'prod' => 0.40, 'qual' => 0.20]  // Fallback
    ];

    /**
     * Tier Multipliers baserat på bonuspoäng
     * Format: [min_poäng => multiplier]
     */
    private const TIER_MULTIPLIERS = [
        95 => 2.00,   // Outstanding (95-100): ×2.0
        90 => 1.50,   // Excellent (90-94): ×1.5
        80 => 1.25,   // God prestanda (80-89): ×1.25
        70 => 1.00,   // Basbonus (70-79): ×1.0
        0  => 0.75    // Under förväntan (<70): ×0.75
    ];

    /**
     * Beräkna avancerade KPI:er med tier multipliers
     *
     * @param array $data Array med ibc_ok, ibc_ej_ok, bur_ej_ok, runtime_plc
     * @param int $produkt Produkt-ID (1, 4, 5)
     * @param array $options Extra options (team_multiplier, safety_factor, etc.)
     * @return array Komplett KPI-resultat
     */
    public function calculateAdvancedKPIs(
        array $data,
        int $produkt = 1,
        array $options = []
    ): array {
        // Extrahera och validera input
        $ibc_ok = max((int)($data['ibc_ok'] ?? 0), 0);
        $ibc_ej_ok = max((int)($data['ibc_ej_ok'] ?? 0), 0);
        $bur_ej_ok = max((int)($data['bur_ej_ok'] ?? 0), 0);
        $runtime = max((int)($data['runtime_plc'] ?? 1), 1);

        // Sanity checks
        $this->validateInput($ibc_ok, $ibc_ej_ok, $bur_ej_ok, $runtime);

        // 1. EFFEKTIVITET: Andel godkända av total produktion
        $total_produced = $ibc_ok + $ibc_ej_ok;
        $effektivitet = $total_produced > 0
            ? round(($ibc_ok / $total_produced) * 100, 2)
            : 0;

        // 2. PRODUKTIVITET: Målbaserad normalisering
        $produktivitet_actual = round(($ibc_ok * 60) / $runtime, 2);
        $produktivitet_target = self::PRODUCTIVITY_TARGETS[$produkt] ?? 15.0;

        // Normalisera mot mål (tillåt upp till 120% för överprestation)
        $produktivitet_normalized = min(
            round(($produktivitet_actual / $produktivitet_target) * 100, 2),
            120
        );

        // 3. KVALITET: Andel godkända utan burfel
        $kvalitet = $ibc_ok > 0
            ? round((($ibc_ok - min($bur_ej_ok, $ibc_ok)) / $ibc_ok) * 100, 2)
            : 0;

        // 4. VIKTNING: Hämta produktspecifik viktning
        $weights = self::PRODUCT_WEIGHTS[$produkt] ?? self::PRODUCT_WEIGHTS['default'];

        // 5. BASBONUS: Viktad summa av KPI:er
        $bonus_base = round(
            ($effektivitet * $weights['eff']) +
            ($produktivitet_normalized * $weights['prod']) +
            ($kvalitet * $weights['qual']),
            2
        );

        // 6. TIER MULTIPLIER: Bestäm tier baserat på basbonus
        $tier_info = $this->getTierMultiplier($bonus_base);

        // 7. EXTRA MULTIPLIERS (om angivna)
        $team_multiplier = $options['team_multiplier'] ?? 1.0;
        $safety_factor = $options['safety_factor'] ?? 1.0;
        $mentorship_bonus = $options['mentorship_bonus'] ?? 0;

        // 8. FINAL BONUS
        $bonus_after_tier = $bonus_base * $tier_info['multiplier'];
        $bonus_after_team = $bonus_after_tier * $team_multiplier;
        $bonus_final = min(
            round(($bonus_after_team * $safety_factor) + $mentorship_bonus, 2),
            200  // Max cap vid 200 poäng
        );

        // 9. Returnera komplett resultat
        return [
            // Bas KPI:er
            'effektivitet' => $effektivitet,
            'produktivitet' => $produktivitet_actual,
            'produktivitet_normalized' => $produktivitet_normalized,
            'produktivitet_target' => $produktivitet_target,
            'kvalitet' => $kvalitet,

            // Bonus breakdown
            'bonus_base' => $bonus_base,
            'bonus_tier_name' => $tier_info['name'],
            'bonus_tier_multiplier' => $tier_info['multiplier'],
            'bonus_after_tier' => round($bonus_after_tier, 2),
            'bonus_team_multiplier' => $team_multiplier,
            'bonus_safety_factor' => $safety_factor,
            'bonus_mentorship' => $mentorship_bonus,
            'bonus_poang' => $bonus_final,

            // Metadata
            'weights_used' => $weights,
            'produkt' => $produkt,
            'total_produced' => $total_produced,
            'runtime_minutes' => $runtime,

            // Breakdown för display
            'breakdown' => [
                'effektivitet_weighted' => round($effektivitet * $weights['eff'], 2),
                'produktivitet_weighted' => round($produktivitet_normalized * $weights['prod'], 2),
                'kvalitet_weighted' => round($kvalitet * $weights['qual'], 2)
            ]
        ];
    }

    /**
     * Bestäm tier multiplier baserat på bonuspoäng
     */
    private function getTierMultiplier(float $bonus_base): array {
        foreach (self::TIER_MULTIPLIERS as $threshold => $multiplier) {
            if ($bonus_base >= $threshold) {
                $name = $this->getTierName($threshold);
                return [
                    'threshold' => $threshold,
                    'multiplier' => $multiplier,
                    'name' => $name
                ];
            }
        }

        return [
            'threshold' => 0,
            'multiplier' => 0.75,
            'name' => 'Under förväntan'
        ];
    }

    /**
     * Få tier-namn baserat på threshold
     */
    private function getTierName(int $threshold): string {
        $names = [
            95 => 'Outstanding',
            90 => 'Excellent',
            80 => 'God prestanda',
            70 => 'Basbonus',
            0  => 'Under förväntan'
        ];

        return $names[$threshold] ?? 'Okänd';
    }

    /**
     * Validera input-data
     */
    private function validateInput(
        int $ibc_ok,
        int $ibc_ej_ok,
        int $bur_ej_ok,
        int $runtime
    ): void {
        // 1. Negativa värden
        if ($ibc_ok < 0 || $ibc_ej_ok < 0 || $bur_ej_ok < 0 || $runtime < 0) {
            throw new InvalidArgumentException('Negativa värden inte tillåtna');
        }

        // 2. Runtime måste vara > 0
        if ($runtime <= 0) {
            throw new InvalidArgumentException('Runtime måste vara större än 0');
        }

        // 3. Bur kan inte vara fler än godkända (logiskt fel)
        if ($bur_ej_ok > $ibc_ok) {
            error_log("WARNING: bur_ej_ok ($bur_ej_ok) > ibc_ok ($ibc_ok) - något är fel!");
        }

        // 4. Produktivitet sanity check (>200 IBC/h är orimligt)
        $prod_check = ($ibc_ok * 60) / $runtime;
        if ($prod_check > 200) {
            error_log("ALERT: Extremely high productivity: $prod_check IBC/h - kontrollera data!");
        }
    }

    /**
     * Simulera bonus för given input
     * Returnerar resultat för alla tre produkttyper
     */
    public function simulateAllProducts(array $data): array {
        $results = [];

        foreach ([1, 4, 5] as $produkt) {
            $results[$produkt] = $this->calculateAdvancedKPIs($data, $produkt);
        }

        return $results;
    }

    /**
     * Jämför två formler (nuvarande vs förbättrad)
     */
    public function compareFormulas(array $data, int $produkt): array {
        // Gammal formel (40/40/20 linear)
        $old_result = $this->calculateOldFormula($data);

        // Ny formel (tier multipliers + målbaserad)
        $new_result = $this->calculateAdvancedKPIs($data, $produkt);

        return [
            'old' => $old_result,
            'new' => $new_result,
            'difference' => round($new_result['bonus_poang'] - $old_result['bonus_poang'], 2),
            'improvement' => round(
                (($new_result['bonus_poang'] - $old_result['bonus_poang']) / max($old_result['bonus_poang'], 1)) * 100,
                2
            )
        ];
    }

    /**
     * Gammal formel för jämförelse
     */
    private function calculateOldFormula(array $data): array {
        $ibc_ok = max((int)($data['ibc_ok'] ?? 0), 0);
        $ibc_ej_ok = max((int)($data['ibc_ej_ok'] ?? 0), 0);
        $bur_ej_ok = max((int)($data['bur_ej_ok'] ?? 0), 0);
        $runtime = max((int)($data['runtime_plc'] ?? 1), 1);

        $total = $ibc_ok + $ibc_ej_ok;

        $eff = $total > 0 ? round(($ibc_ok / $total) * 100, 2) : 0;
        $prod = round(($ibc_ok * 60) / $runtime, 2);
        $qual = $ibc_ok > 0 ? round((($ibc_ok - $bur_ej_ok) / $ibc_ok) * 100, 2) : 0;

        $bonus = round(
            ($eff * 0.4) + (min($prod, 100) * 0.4) + ($qual * 0.2),
            2
        );

        return [
            'effektivitet' => $eff,
            'produktivitet' => $prod,
            'kvalitet' => $qual,
            'bonus_poang' => $bonus,
            'formula' => 'Old (40/40/20 linear)'
        ];
    }

    /**
     * Generera HTML-rapport för operatör
     */
    public function generateHTMLReport(array $kpi_result, string $operator_id, string $period): string {
        $html = <<<HTML
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Bonusrapport - Operatör {$operator_id}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #2c3e50; color: white; padding: 20px; border-radius: 10px; }
        .kpi-box { background: #ecf0f1; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .bonus-final { font-size: 48px; font-weight: bold; color: #27ae60; text-align: center; }
        .tier { background: #f39c12; color: white; padding: 10px; border-radius: 5px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #34495e; color: white; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏆 Bonusrapport</h1>
        <p>Operatör: {$operator_id} | Period: {$period}</p>
    </div>

    <div class="bonus-final">
        {$kpi_result['bonus_poang']} poäng
    </div>

    <div class="tier">
        Tier: {$kpi_result['bonus_tier_name']} (×{$kpi_result['bonus_tier_multiplier']})
    </div>

    <h2>KPI Breakdown</h2>
    <table>
        <tr>
            <th>KPI</th>
            <th>Värde</th>
            <th>Vikt</th>
            <th>Bidrag</th>
        </tr>
        <tr>
            <td>Effektivitet</td>
            <td>{$kpi_result['effektivitet']}%</td>
            <td>{$kpi_result['weights_used']['eff']}</td>
            <td>{$kpi_result['breakdown']['effektivitet_weighted']}</td>
        </tr>
        <tr>
            <td>Produktivitet</td>
            <td>{$kpi_result['produktivitet']} IBC/h</td>
            <td>{$kpi_result['weights_used']['prod']}</td>
            <td>{$kpi_result['breakdown']['produktivitet_weighted']}</td>
        </tr>
        <tr>
            <td>Kvalitet</td>
            <td>{$kpi_result['kvalitet']}%</td>
            <td>{$kpi_result['weights_used']['qual']}</td>
            <td>{$kpi_result['breakdown']['kvalitet_weighted']}</td>
        </tr>
    </table>

    <div class="kpi-box">
        <h3>Basbonus: {$kpi_result['bonus_base']}</h3>
        <h3>Efter Tier (×{$kpi_result['bonus_tier_multiplier']}): {$kpi_result['bonus_after_tier']}</h3>
        <h3>Final Bonus: {$kpi_result['bonus_poang']}</h3>
    </div>
</body>
</html>
HTML;

        return $html;
    }
}

<?php
/**
 * BonusPDFReport.php
 * PDF-rapportgenerator för månadsbonus
 *
 * Använder FPDF för att skapa professionella bonusrapporter
 *
 * Installation:
 * composer require setasign/fpdf
 *
 * Användning:
 * $report = new BonusPDFReport($pdo);
 * $report->generateOperatorMonthlyReport(123, '2026-02');
 */

require_once __DIR__ . '/vendor/autoload.php';

class BonusPDFReport extends FPDF {
    private $pdo;
    private $operator_id;
    private $period;
    private $operator_data = [];

    // Färgschema
    private const COLOR_PRIMARY = [44, 62, 80];      // #2c3e50
    private const COLOR_SUCCESS = [39, 174, 96];     // #27ae60
    private const COLOR_WARNING = [243, 156, 18];    // #f39c12
    private const COLOR_DANGER = [231, 76, 60];      // #e74c3c
    private const COLOR_LIGHT = [236, 240, 241];     // #ecf0f1

    public function __construct($pdo = null) {
        parent::__construct();
        $this->pdo = $pdo;
    }

    /**
     * Generera månadsrapport för operatör
     */
    public function generateOperatorMonthlyReport(int $operator_id, string $period): string {
        $this->operator_id = $operator_id;
        $this->period = $period;

        // Hämta data från databas
        $this->loadOperatorData();

        if (empty($this->operator_data['cycles'])) {
            throw new Exception("Ingen data hittades för operatör $operator_id i period $period");
        }

        // Bygg PDF
        $this->AliasNbPages();
        $this->AddPage();
        $this->SetAutoPageBreak(true, 15);

        // Innehåll
        $this->renderHeader();
        $this->renderSummary();
        $this->renderKPIBreakdown();
        $this->renderDailyDetails();
        $this->renderTrend();
        $this->renderFooter();

        // Returnera filnamn
        $filename = "bonus_report_{$operator_id}_{$period}.pdf";
        $filepath = __DIR__ . "/reports/{$filename}";

        // Skapa reports-katalog om den inte finns
        if (!is_dir(__DIR__ . '/reports')) {
            mkdir(__DIR__ . '/reports', 0755, true);
        }

        $this->Output('F', $filepath);

        return $filepath;
    }

    /**
     * Ladda operatörsdata från databas
     */
    private function loadOperatorData(): void {
        if (!$this->pdo) {
            throw new Exception("PDO connection required");
        }

        // Hämta alla cykler för operatören i perioden
        $stmt = $this->pdo->prepare("
            SELECT
                datum,
                produkt,
                ibc_ok,
                ibc_ej_ok,
                bur_ej_ok,
                runtime_plc,
                rasttime_plc,
                effektivitet,
                produktivitet,
                kvalitet,
                bonus_poang,
                skiftraknare
            FROM rebotling_ibc
            WHERE DATE_FORMAT(datum, '%Y-%m') = :period
            AND (op1 = :operator_id OR op2 = :operator_id OR op3 = :operator_id)
            AND bonus_poang IS NOT NULL
            ORDER BY datum ASC
        ");

        $stmt->execute([
            'period' => $this->period,
            'operator_id' => $this->operator_id
        ]);

        $cycles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Beräkna sammanfattning
        $total_cycles = count($cycles);
        $total_ibc_ok = array_sum(array_column($cycles, 'ibc_ok'));
        $total_ibc_ej_ok = array_sum(array_column($cycles, 'ibc_ej_ok'));
        $total_bur_ej_ok = array_sum(array_column($cycles, 'bur_ej_ok'));
        $total_runtime = array_sum(array_column($cycles, 'runtime_plc'));

        $avg_effektivitet = $total_cycles > 0
            ? array_sum(array_column($cycles, 'effektivitet')) / $total_cycles
            : 0;
        $avg_produktivitet = $total_cycles > 0
            ? array_sum(array_column($cycles, 'produktivitet')) / $total_cycles
            : 0;
        $avg_kvalitet = $total_cycles > 0
            ? array_sum(array_column($cycles, 'kvalitet')) / $total_cycles
            : 0;
        $avg_bonus = $total_cycles > 0
            ? array_sum(array_column($cycles, 'bonus_poang')) / $total_cycles
            : 0;

        $total_bonus = array_sum(array_column($cycles, 'bonus_poang'));

        $this->operator_data = [
            'cycles' => $cycles,
            'summary' => [
                'total_cycles' => $total_cycles,
                'total_ibc_ok' => $total_ibc_ok,
                'total_ibc_ej_ok' => $total_ibc_ej_ok,
                'total_bur_ej_ok' => $total_bur_ej_ok,
                'total_runtime_hours' => round($total_runtime / 60, 2),
                'avg_effektivitet' => round($avg_effektivitet, 2),
                'avg_produktivitet' => round($avg_produktivitet, 2),
                'avg_kvalitet' => round($avg_kvalitet, 2),
                'avg_bonus' => round($avg_bonus, 2),
                'total_bonus' => round($total_bonus, 2)
            ]
        ];
    }

    /**
     * Header för varje sida
     */
    public function Header(): void {
        if ($this->PageNo() == 1) {
            return; // Första sidan har custom header
        }

        $this->SetFillColor(...self::COLOR_PRIMARY);
        $this->Rect(0, 0, 210, 20, 'F');

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 20, 'Bonusrapport - Operatör ' . $this->operator_id, 0, 0, 'L');
        $this->Ln(25);
    }

    /**
     * Rendrera huvudheader
     */
    private function renderHeader(): void {
        // Stor färgad header
        $this->SetFillColor(...self::COLOR_PRIMARY);
        $this->Rect(0, 0, 210, 50, 'F');

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 24);
        $this->SetY(15);
        $this->Cell(0, 10, utf8_decode('🏆 Månadsbonus Rapport'), 0, 1, 'C');

        $this->SetFont('Arial', '', 14);
        $this->Cell(0, 8, utf8_decode("Operatör #{$this->operator_id} | Period: {$this->period}"), 0, 1, 'C');

        $this->SetTextColor(0, 0, 0);
        $this->Ln(10);
    }

    /**
     * Rendrera sammanfattning
     */
    private function renderSummary(): void {
        $summary = $this->operator_data['summary'];

        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, utf8_decode('Sammanfattning'), 0, 1);
        $this->Ln(2);

        // Total bonus - stor box
        $this->SetFillColor(...self::COLOR_SUCCESS);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 36);
        $this->Cell(0, 25, $summary['total_bonus'] . ' poäng', 0, 1, 'C', true);

        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 8, utf8_decode('Total bonuspoäng för perioden'), 0, 1, 'C');
        $this->Ln(5);

        // Statistik i 2 kolumner
        $col_width = 95;
        $row_height = 8;

        $this->SetFont('Arial', 'B', 11);
        $this->SetFillColor(...self::COLOR_LIGHT);

        // Vänster kolumn
        $x_start = $this->GetX();
        $y_start = $this->GetY();

        $this->Cell($col_width, $row_height, utf8_decode('Produktionsstatistik'), 1, 0, 'C', true);
        $this->SetX($x_start + $col_width);
        $this->Cell($col_width, $row_height, utf8_decode('KPI Genomsnitt'), 1, 1, 'C', true);

        $this->SetFont('Arial', '', 10);

        $stats_left = [
            utf8_decode('Antal cykler') => $summary['total_cycles'],
            utf8_decode('IBC OK') => $summary['total_ibc_ok'],
            utf8_decode('IBC Ej OK') => $summary['total_ibc_ej_ok'],
            utf8_decode('Bur Ej OK') => $summary['total_bur_ej_ok'],
            utf8_decode('Arbetstid') => $summary['total_runtime_hours'] . ' h'
        ];

        $stats_right = [
            utf8_decode('Effektivitet') => $summary['avg_effektivitet'] . '%',
            utf8_decode('Produktivitet') => $summary['avg_produktivitet'] . ' IBC/h',
            utf8_decode('Kvalitet') => $summary['avg_kvalitet'] . '%',
            utf8_decode('Snittbonus/cykel') => $summary['avg_bonus'] . ' p',
            ''  => ''
        ];

        foreach ($stats_left as $label => $value) {
            $this->SetX($x_start);
            $this->Cell($col_width / 2, $row_height, $label . ':', 1, 0, 'L');
            $this->Cell($col_width / 2, $row_height, (string)$value, 1, 0, 'R');

            reset($stats_right);
            $right_label = key($stats_right);
            $right_value = current($stats_right);
            next($stats_right);

            if ($right_label) {
                $this->Cell($col_width / 2, $row_height, $right_label . ':', 1, 0, 'L');
                $this->Cell($col_width / 2, $row_height, (string)$right_value, 1, 1, 'R');
            } else {
                $this->Ln();
            }
        }

        $this->Ln(8);
    }

    /**
     * Rendrera KPI breakdown
     */
    private function renderKPIBreakdown(): void {
        $summary = $this->operator_data['summary'];

        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, utf8_decode('KPI Breakdown'), 0, 1);
        $this->Ln(2);

        // Progress bars för varje KPI
        $kpis = [
            ['label' => 'Effektivitet', 'value' => $summary['avg_effektivitet'], 'target' => 95],
            ['label' => 'Produktivitet', 'value' => min($summary['avg_produktivitet'] * 5, 100), 'target' => 80],
            ['label' => 'Kvalitet', 'value' => $summary['avg_kvalitet'], 'target' => 98]
        ];

        foreach ($kpis as $kpi) {
            $this->renderProgressBar(
                utf8_decode($kpi['label']),
                $kpi['value'],
                $kpi['target']
            );
            $this->Ln(3);
        }

        $this->Ln(5);
    }

    /**
     * Rendrera progress bar
     */
    private function renderProgressBar(string $label, float $value, float $target): void {
        $bar_width = 140;
        $bar_height = 12;

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(50, $bar_height, $label . ':', 0, 0, 'L');

        // Background
        $this->SetFillColor(220, 220, 220);
        $this->Rect($this->GetX(), $this->GetY(), $bar_width, $bar_height, 'F');

        // Fill
        $fill_width = min(($value / 100) * $bar_width, $bar_width);

        // Färg baserat på target
        if ($value >= $target) {
            $this->SetFillColor(...self::COLOR_SUCCESS);
        } elseif ($value >= $target * 0.8) {
            $this->SetFillColor(...self::COLOR_WARNING);
        } else {
            $this->SetFillColor(...self::COLOR_DANGER);
        }

        $this->Rect($this->GetX(), $this->GetY(), $fill_width, $bar_height, 'F');

        // Value text
        $this->SetTextColor(0, 0, 0);
        $this->Cell($bar_width, $bar_height, round($value, 1) . '%', 0, 1, 'C');

        $this->SetTextColor(0, 0, 0);
    }

    /**
     * Rendrera dagliga detaljer
     */
    private function renderDailyDetails(): void {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, utf8_decode('Dagliga Prestationer'), 0, 1);
        $this->Ln(2);

        // Tabell header
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(...self::COLOR_PRIMARY);
        $this->SetTextColor(255, 255, 255);

        $headers = ['Datum', 'Prod', 'IBC OK', 'Eff %', 'Prod', 'Kval %', 'Bonus'];
        $widths = [30, 15, 20, 20, 25, 20, 25];

        foreach ($headers as $i => $header) {
            $this->Cell($widths[$i], 8, utf8_decode($header), 1, 0, 'C', true);
        }
        $this->Ln();

        // Tabell data
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(0, 0, 0);

        // Gruppera per dag
        $daily_data = [];
        foreach ($this->operator_data['cycles'] as $cycle) {
            $date = substr($cycle['datum'], 0, 10);

            if (!isset($daily_data[$date])) {
                $daily_data[$date] = [
                    'produkt' => $cycle['produkt'],
                    'ibc_ok' => 0,
                    'effektivitet' => [],
                    'produktivitet' => [],
                    'kvalitet' => [],
                    'bonus' => 0
                ];
            }

            $daily_data[$date]['ibc_ok'] += $cycle['ibc_ok'];
            $daily_data[$date]['effektivitet'][] = $cycle['effektivitet'];
            $daily_data[$date]['produktivitet'][] = $cycle['produktivitet'];
            $daily_data[$date]['kvalitet'][] = $cycle['kvalitet'];
            $daily_data[$date]['bonus'] += $cycle['bonus_poang'];
        }

        $fill = false;
        foreach ($daily_data as $date => $data) {
            $avg_eff = round(array_sum($data['effektivitet']) / count($data['effektivitet']), 1);
            $avg_prod = round(array_sum($data['produktivitet']) / count($data['produktivitet']), 1);
            $avg_qual = round(array_sum($data['kvalitet']) / count($data['kvalitet']), 1);

            $this->SetFillColor(245, 245, 245);

            $this->Cell($widths[0], 7, $date, 1, 0, 'L', $fill);
            $this->Cell($widths[1], 7, $data['produkt'], 1, 0, 'C', $fill);
            $this->Cell($widths[2], 7, $data['ibc_ok'], 1, 0, 'C', $fill);
            $this->Cell($widths[3], 7, $avg_eff . '%', 1, 0, 'C', $fill);
            $this->Cell($widths[4], 7, $avg_prod, 1, 0, 'C', $fill);
            $this->Cell($widths[5], 7, $avg_qual . '%', 1, 0, 'C', $fill);

            // Bonus färgkodad
            if ($data['bonus'] >= 80) {
                $this->SetTextColor(...self::COLOR_SUCCESS);
            } elseif ($data['bonus'] >= 70) {
                $this->SetTextColor(...self::COLOR_WARNING);
            } else {
                $this->SetTextColor(...self::COLOR_DANGER);
            }

            $this->SetFont('Arial', 'B', 8);
            $this->Cell($widths[6], 7, round($data['bonus'], 1), 1, 1, 'C', $fill);
            $this->SetFont('Arial', '', 8);
            $this->SetTextColor(0, 0, 0);

            $fill = !$fill;
        }

        $this->Ln(5);
    }

    /**
     * Rendrera trend
     */
    private function renderTrend(): void {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, utf8_decode('Prestationstrend'), 0, 1);
        $this->Ln(2);

        $this->SetFont('Arial', '', 10);

        // Beräkna veckovis trend
        $weekly_data = [];
        foreach ($this->operator_data['cycles'] as $cycle) {
            $week = date('W', strtotime($cycle['datum']));

            if (!isset($weekly_data[$week])) {
                $weekly_data[$week] = [
                    'bonus' => [],
                    'effektivitet' => []
                ];
            }

            $weekly_data[$week]['bonus'][] = $cycle['bonus_poang'];
            $weekly_data[$week]['effektivitet'][] = $cycle['effektivitet'];
        }

        if (count($weekly_data) > 1) {
            $weeks = array_keys($weekly_data);
            $first_week_avg = array_sum($weekly_data[$weeks[0]]['bonus']) / count($weekly_data[$weeks[0]]['bonus']);
            $last_week_avg = array_sum($weekly_data[end($weeks)]['bonus']) / count($weekly_data[end($weeks)]['bonus']);

            $trend = $last_week_avg - $first_week_avg;
            $trend_pct = $first_week_avg > 0 ? ($trend / $first_week_avg) * 100 : 0;

            $trend_text = $trend > 0 ? 'Uppåtgående' : ($trend < 0 ? 'Nedåtgående' : 'Stabil');
            $trend_emoji = $trend > 0 ? '📈' : ($trend < 0 ? '📉' : '➡️');

            $this->Cell(0, 8, utf8_decode("$trend_emoji Trend: $trend_text (" . round($trend_pct, 1) . "%)"), 0, 1);
        } else {
            $this->Cell(0, 8, utf8_decode('Otillräcklig data för trendanalys'), 0, 1);
        }

        $this->Ln(5);
    }

    /**
     * Footer för varje sida
     */
    private function renderFooter(): void {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, utf8_decode('Genererad: ' . date('Y-m-d H:i:s') . ' | Sida ' . $this->PageNo()), 0, 0, 'C');
    }
}

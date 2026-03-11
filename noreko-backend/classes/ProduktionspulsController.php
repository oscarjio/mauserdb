<?php
/**
 * ProduktionspulsController
 * GET ?action=produktionspuls&run=latest&limit=50  — senaste IBC:er
 * GET ?action=produktionspuls&run=hourly-stats     — timstatistik + trend
 */
class ProduktionspulsController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        $run = trim($_GET['run'] ?? '');

        if ($run === 'latest') {
            $this->getLatest();
        } elseif ($run === 'hourly-stats') {
            $this->getHourlyStats();
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig run-parameter']);
        }
    }

    /**
     * Senaste X IBC:er med operatörsnamn, produktnamn, cykeltid, status
     */
    private function getLatest() {
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));

        $stmt = $this->pdo->prepare("
            SELECT
                i.id,
                i.datum,
                i.ibc_count,
                i.skiftraknare,
                i.ibc_ok,
                i.ibc_ej_ok,
                i.bur_ej_ok,
                i.op1,
                i.produkt,
                TIMESTAMPDIFF(SECOND,
                    LAG(i.datum) OVER (PARTITION BY i.skiftraknare ORDER BY i.datum),
                    i.datum
                ) AS cycle_time_seconds,
                o1.name AS operator_namn,
                p.name AS produkt_namn,
                p.cycle_time_minutes AS target_cycle_minutes
            FROM rebotling_ibc i
            LEFT JOIN operators o1 ON i.op1 = o1.number
            LEFT JOIN rebotling_products p ON i.produkt = p.id
            ORDER BY i.datum DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Bearbeta raderna
        $items = [];
        foreach ($rows as $row) {
            $cycleSeconds = $row['cycle_time_seconds'] !== null ? (int)$row['cycle_time_seconds'] : null;
            $cycleMinutes = $cycleSeconds !== null ? round($cycleSeconds / 60, 1) : null;
            $targetMinutes = $row['target_cycle_minutes'] !== null ? (float)$row['target_cycle_minutes'] : null;

            // Filtrera bort orimliga cykeltider (>30 min = troligen stopp)
            if ($cycleMinutes !== null && $cycleMinutes > 30) {
                $cycleMinutes = null;
            }

            // Status: kasserad om ibc_ej_ok > 0 eller bur_ej_ok > 0
            $kasserad = ((int)($row['ibc_ej_ok'] ?? 0) > 0) || ((int)($row['bur_ej_ok'] ?? 0) > 0);

            // Över snitt: cykeltid > target
            $overTarget = false;
            if ($cycleMinutes !== null && $targetMinutes !== null && $targetMinutes > 0) {
                $overTarget = $cycleMinutes > $targetMinutes;
            }

            $items[] = [
                'id'              => (int)$row['id'],
                'datum'           => $row['datum'],
                'operator'        => $row['operator_namn'] ?? ('Op ' . ($row['op1'] ?? '?')),
                'produkt'         => $row['produkt_namn'] ?? 'Okänd',
                'cykeltid'        => $cycleMinutes,
                'target_cykeltid' => $targetMinutes,
                'kasserad'        => $kasserad,
                'over_target'     => $overTarget,
                'ibc_nr'          => (int)($row['ibc_count'] ?? 0),
                'skift'           => (int)($row['skiftraknare'] ?? 0),
            ];
        }

        echo json_encode([
            'success' => true,
            'data'    => $items,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Timstatistik: IBC/h, snittcykeltid, godkända/kasserade — senaste + föregående timme
     */
    private function getHourlyStats() {
        $now = date('Y-m-d H:i:s');
        $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $twoHoursAgo = date('Y-m-d H:i:s', strtotime('-2 hours'));

        // Senaste timmen
        $current = $this->getHourData($oneHourAgo, $now);
        // Föregående timme
        $previous = $this->getHourData($twoHoursAgo, $oneHourAgo);

        echo json_encode([
            'success'  => true,
            'current'  => $current,
            'previous' => $previous,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function getHourData(string $from, string $to): array {
        // Antal IBC:er
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) AS total,
                   SUM(CASE WHEN (ibc_ej_ok > 0 OR bur_ej_ok > 0) THEN 1 ELSE 0 END) AS kasserade
            FROM rebotling_ibc
            WHERE datum BETWEEN :from_dt AND :to_dt
        ");
        $stmt->execute(['from_dt' => $from, 'to_dt' => $to]);
        $counts = $stmt->fetch(PDO::FETCH_ASSOC);

        $total = (int)($counts['total'] ?? 0);
        $kasserade = (int)($counts['kasserade'] ?? 0);
        $godkanda = $total - $kasserade;

        // Snittcykeltid (beräknad via TIMESTAMPDIFF)
        $stmt2 = $this->pdo->prepare("
            SELECT AVG(diff_sec) AS avg_cycle_seconds
            FROM (
                SELECT TIMESTAMPDIFF(SECOND,
                    LAG(i.datum) OVER (PARTITION BY i.skiftraknare ORDER BY i.datum),
                    i.datum
                ) AS diff_sec
                FROM rebotling_ibc i
                WHERE i.datum BETWEEN :from_dt AND :to_dt
            ) sub
            WHERE diff_sec > 0 AND diff_sec <= 1800
        ");
        $stmt2->execute(['from_dt' => $from, 'to_dt' => $to]);
        $avgRow = $stmt2->fetch(PDO::FETCH_ASSOC);
        $avgCycleMinutes = $avgRow['avg_cycle_seconds'] !== null
            ? round((float)$avgRow['avg_cycle_seconds'] / 60, 1)
            : null;

        return [
            'ibc_count'       => $total,
            'godkanda'        => $godkanda,
            'kasserade'       => $kasserade,
            'snitt_cykeltid'  => $avgCycleMinutes,
        ];
    }
}

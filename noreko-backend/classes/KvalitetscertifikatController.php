<?php

/**
 * KvalitetscertifikatController
 * Genererar och hanterar kvalitetscertifikat/intyg for avslutade batchar.
 *
 * Endpoints via ?action=kvalitetscertifikat&run=XXX:
 *
 *   GET  run=overview           — KPI:er: totala certifikat, godkand%, senaste certifikat, snittpoang
 *   GET  run=lista              — Lista certifikat med filter (status, period, operator)
 *   GET  run=detalj             (?id) — Hamta komplett certifikat for en batch
 *   POST run=generera           — Skapa nytt certifikat baserat pa batch-data
 *   POST run=bedom              — Godkann/underkann certifikat med kommentar
 *   GET  run=kriterier          — Hamta kvalitetskriterier
 *   POST run=uppdatera-kriterier — Uppdatera kriterier (admin)
 *   GET  run=statistik          — Kvalitetspoang per batch for trenddiagram
 */
class KvalitetscertifikatController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->ensureTables();
    }

    public function handle(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(['read_and_close' => true]);
        }

        if (empty($_SESSION['user_id'])) {
            $this->sendError('Inloggning kravs', 401);
            return;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $run    = trim($_GET['run'] ?? '');

        if ($method === 'GET') {
            switch ($run) {
                case 'overview':    $this->getOverview();    break;
                case 'lista':       $this->getLista();       break;
                case 'detalj':      $this->getDetalj();      break;
                case 'kriterier':   $this->getKriterier();   break;
                case 'statistik':   $this->getStatistik();   break;
                default:
                    $this->sendError('Okand run-parameter: ' . htmlspecialchars($run), 404);
            }
            return;
        }

        if ($method === 'POST') {
            switch ($run) {
                case 'generera':
                    $this->generera();
                    break;
                case 'bedom':
                    $this->bedom();
                    break;
                case 'uppdatera-kriterier':
                    $this->requireAdmin();
                    $this->uppdateraKriterier();
                    break;
                default:
                    $this->sendError('Okand run-parameter', 404);
            }
            return;
        }

        $this->sendError('Ogiltig metod', 405);
    }

    // =========================================================================
    // Auth
    // =========================================================================

    private function requireAdmin(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Sessionen har gatt ut. Logga in igen.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin-behorighet kravs.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    private function currentUserName(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(['read_and_close' => true]);
        }
        return $_SESSION['username'] ?? 'Okand';
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function sendSuccess(array $data): void {
        echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);
    }

    private function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    }

    private function ensureTables(): void {
        try {
            $check = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = 'kvalitetscertifikat'"
            )->fetchColumn();
            if (!$check) {
                $sql = file_get_contents(__DIR__ . '/../migrations/2026-03-12_kvalitetscertifikat.sql');
                if ($sql) {
                    $this->pdo->exec($sql);
                }
            }
        } catch (\PDOException $e) {
            error_log('KvalitetscertifikatController::ensureTables: ' . $e->getMessage());
        }
    }

    /**
     * Hamta datumgranser baserat pa period.
     */
    private function getPeriodBounds(string $period): array {
        $today = date('Y-m-d');
        switch ($period) {
            case 'vecka':
                $dt = new \DateTime($today);
                $dayOfWeek = (int)$dt->format('N');
                $from = (clone $dt)->modify('-' . ($dayOfWeek - 1) . ' days')->format('Y-m-d');
                return ['from' => $from, 'to' => $today];
            case 'manad':
                $dt = new \DateTime($today);
                $from = $dt->format('Y-m-01');
                return ['from' => $from, 'to' => $today];
            case 'kvartal':
                $dt = new \DateTime($today);
                $month = (int)$dt->format('n');
                $quarterStart = (int)(floor(($month - 1) / 3) * 3 + 1);
                $from = $dt->format('Y') . '-' . str_pad($quarterStart, 2, '0', STR_PAD_LEFT) . '-01';
                return ['from' => $from, 'to' => $today];
            default:
                // Senaste 30 dagarna som standard
                $from = date('Y-m-d', strtotime('-30 days'));
                return ['from' => $from, 'to' => $today];
        }
    }

    // =========================================================================
    // GET run=overview
    // =========================================================================

    private function getOverview(): void {
        try {
            // Totala certifikat
            $total = (int)$this->pdo->query(
                "SELECT COUNT(*) FROM kvalitetscertifikat"
            )->fetchColumn();

            // Godkanda
            $godkanda = (int)$this->pdo->query(
                "SELECT COUNT(*) FROM kvalitetscertifikat WHERE status = 'godkand'"
            )->fetchColumn();

            $godkandPct = $total > 0 ? round(($godkanda / $total) * 100, 1) : 0;

            // Senaste certifikat
            $stmt = $this->pdo->query(
                "SELECT batch_nummer, datum FROM kvalitetscertifikat ORDER BY datum DESC, id DESC LIMIT 1"
            );
            $senaste = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Genomsnittlig kvalitetspoang
            $snittPoang = (float)$this->pdo->query(
                "SELECT COALESCE(AVG(kvalitetspoang), 0) FROM kvalitetscertifikat WHERE kvalitetspoang > 0"
            )->fetchColumn();

            $this->sendSuccess([
                'data' => [
                    'totala_certifikat'    => $total,
                    'godkand_procent'      => $godkandPct,
                    'godkanda'             => $godkanda,
                    'senaste_datum'        => $senaste['datum'] ?? null,
                    'senaste_batch'        => $senaste['batch_nummer'] ?? null,
                    'snitt_kvalitetspoang' => round($snittPoang, 1),
                ],
            ]);
        } catch (\Exception $e) {
            error_log('KvalitetscertifikatController::getOverview: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta oversikt', 500);
        }
    }

    // =========================================================================
    // GET run=lista
    // =========================================================================

    private function getLista(): void {
        try {
            $status   = trim($_GET['status'] ?? '');
            $period   = trim($_GET['period'] ?? '');
            $opId     = isset($_GET['operator_id']) ? (int)$_GET['operator_id'] : 0;

            $where  = '1=1';
            $params = [];

            if ($status && in_array($status, ['godkand', 'underkand', 'ej_bedomd'], true)) {
                $where .= ' AND status = :status';
                $params[':status'] = $status;
            }

            if ($period) {
                $bounds = $this->getPeriodBounds($period);
                $where .= ' AND datum BETWEEN :from_date AND :to_date';
                $params[':from_date'] = $bounds['from'];
                $params[':to_date']   = $bounds['to'];
            }

            if ($opId > 0) {
                $where .= ' AND operator_id = :op_id';
                $params[':op_id'] = $opId;
            }

            $stmt = $this->pdo->prepare("
                SELECT id, batch_nummer, datum, operator_id, operator_namn,
                       antal_ibc, kassation_procent, cykeltid_snitt,
                       kvalitetspoang, status, kommentar, bedomd_av, bedomd_datum, skapad_datum
                FROM kvalitetscertifikat
                WHERE {$where}
                ORDER BY datum DESC, id DESC
                LIMIT 500
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Hamta unika operatorer for filter
            $operatorer = $this->pdo->query(
                "SELECT DISTINCT operator_id, operator_namn FROM kvalitetscertifikat WHERE operator_id IS NOT NULL ORDER BY operator_namn"
            )->fetchAll(\PDO::FETCH_ASSOC);

            $this->sendSuccess([
                'data' => [
                    'certifikat' => $rows,
                    'total'      => count($rows),
                    'operatorer' => $operatorer,
                ],
            ]);
        } catch (\Exception $e) {
            error_log('KvalitetscertifikatController::getLista: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta lista', 500);
        }
    }

    // =========================================================================
    // GET run=detalj
    // =========================================================================

    private function getDetalj(): void {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            $this->sendError('Ogiltigt certifikat-ID');
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM kvalitetscertifikat WHERE id = :id
            ");
            $stmt->execute([':id' => $id]);
            $cert = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$cert) {
                $this->sendError('Certifikat hittades inte', 404);
                return;
            }

            // Hamta kvalitetskriterier for bedomning
            $kriterier = $this->pdo->query(
                "SELECT * FROM kvalitetskriterier WHERE aktiv = 1 ORDER BY vikt DESC"
            )->fetchAll(\PDO::FETCH_ASSOC);

            $this->sendSuccess([
                'data' => [
                    'certifikat' => $cert,
                    'kriterier'  => $kriterier,
                ],
            ]);
        } catch (\Exception $e) {
            error_log('KvalitetscertifikatController::getDetalj: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta certifikat', 500);
        }
    }

    // =========================================================================
    // POST run=generera
    // =========================================================================

    private function generera(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $batchNummer     = trim($data['batch_nummer'] ?? '');
        $datum           = trim($data['datum'] ?? date('Y-m-d'));
        $operatorId      = isset($data['operator_id']) ? (int)$data['operator_id'] : null;
        $operatorNamn    = trim($data['operator_namn'] ?? '');
        $antalIbc        = max(0, (int)($data['antal_ibc'] ?? 0));
        $kassationPct    = max(0, min(100, (float)($data['kassation_procent'] ?? 0)));
        $cykeltidSnitt   = max(0, (float)($data['cykeltid_snitt'] ?? 0));

        if (!$batchNummer) {
            $this->sendError('Batchnummer kravs');
            return;
        }

        // Berakna kvalitetspoang baserat pa kriterier
        $kvalitetspoang = $this->beraknaKvalitetspoang($kassationPct, $cykeltidSnitt, $antalIbc);

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO kvalitetscertifikat
                    (batch_nummer, datum, operator_id, operator_namn, antal_ibc,
                     kassation_procent, cykeltid_snitt, kvalitetspoang, status, skapad_datum)
                VALUES
                    (:batch_nummer, :datum, :operator_id, :operator_namn, :antal_ibc,
                     :kassation_procent, :cykeltid_snitt, :kvalitetspoang, 'ej_bedomd', NOW())
            ");
            $stmt->execute([
                ':batch_nummer'      => $batchNummer,
                ':datum'             => $datum,
                ':operator_id'       => $operatorId,
                ':operator_namn'     => $operatorNamn,
                ':antal_ibc'         => $antalIbc,
                ':kassation_procent' => $kassationPct,
                ':cykeltid_snitt'    => $cykeltidSnitt,
                ':kvalitetspoang'    => $kvalitetspoang,
            ]);

            $newId = (int)$this->pdo->lastInsertId();

            $this->sendSuccess([
                'message' => 'Certifikat skapat',
                'id'      => $newId,
                'kvalitetspoang' => $kvalitetspoang,
            ]);
        } catch (\PDOException $e) {
            error_log('KvalitetscertifikatController::generera: ' . $e->getMessage());
            $this->sendError('Kunde inte skapa certifikat', 500);
        }
    }

    /**
     * Berakna kvalitetspoang baserat pa aktiva kriterier.
     */
    private function beraknaKvalitetspoang(float $kassation, float $cykeltid, int $antalIbc): float {
        try {
            $kriterier = $this->pdo->query(
                "SELECT * FROM kvalitetskriterier WHERE aktiv = 1"
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException) {
            // Fallback: enkel berakning
            return $this->beraknaEnkelPoang($kassation, $cykeltid, $antalIbc);
        }

        if (empty($kriterier)) {
            return $this->beraknaEnkelPoang($kassation, $cykeltid, $antalIbc);
        }

        $totalVikt  = 0;
        $totalPoang = 0;

        foreach ($kriterier as $k) {
            $vikt   = (float)$k['vikt'];
            $namn   = strtolower($k['namn']);
            $min    = $k['min_varde'] !== null ? (float)$k['min_varde'] : null;
            $max    = $k['max_varde'] !== null ? (float)$k['max_varde'] : null;
            $poang  = 0;

            if (strpos($namn, 'kassation') !== false) {
                // Lagre ar battre, max_varde ar grans
                if ($max !== null && $max > 0) {
                    $poang = max(0, min(100, (1 - $kassation / ($max * 2)) * 100));
                    if ($kassation <= $max) $poang = max($poang, 70);
                }
            } elseif (strpos($namn, 'cykeltid') !== false) {
                // Lagre ar battre
                if ($max !== null && $max > 0) {
                    $poang = max(0, min(100, (1 - $cykeltid / ($max * 2)) * 100));
                    if ($cykeltid <= $max) $poang = max($poang, 70);
                }
            } elseif (strpos($namn, 'antal') !== false) {
                // Hogre ar battre
                if ($min !== null && $min > 0) {
                    $poang = min(100, ($antalIbc / $min) * 80);
                    if ($antalIbc >= $min) $poang = max($poang, 80);
                }
            } elseif (strpos($namn, 'jamn') !== false || strpos($namn, 'jaemn') !== false) {
                // Jamnhet - anvand cykeltid som proxy (lagre = battre)
                if ($max !== null && $max > 0) {
                    $spridning = abs($cykeltid - 38) / 10; // Approximation
                    $poang = max(0, min(100, (1 - $spridning / $max) * 100));
                }
            } else {
                // Default: ge 80 poang
                $poang = 80;
            }

            $totalVikt  += $vikt;
            $totalPoang += $poang * $vikt;
        }

        return $totalVikt > 0 ? round($totalPoang / $totalVikt, 1) : 0;
    }

    /**
     * Enkel fallback-berakning av kvalitetspoang.
     */
    private function beraknaEnkelPoang(float $kassation, float $cykeltid, int $antalIbc): float {
        $poang = 100;
        // Kassation: -10 poang per procent over 1%
        if ($kassation > 1) $poang -= ($kassation - 1) * 10;
        // Cykeltid: -5 poang per sekund over 40s
        if ($cykeltid > 40) $poang -= ($cykeltid - 40) * 5;
        // Antal IBC bonus
        if ($antalIbc >= 200) $poang += 5;
        return max(0, min(100, round($poang, 1)));
    }

    // =========================================================================
    // POST run=bedom
    // =========================================================================

    private function bedom(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $id        = isset($data['id']) ? (int)$data['id'] : 0;
        $status    = trim($data['status'] ?? '');
        $kommentar = trim($data['kommentar'] ?? '');

        if ($id <= 0) {
            $this->sendError('Ogiltigt certifikat-ID');
            return;
        }

        if (!in_array($status, ['godkand', 'underkand'], true)) {
            $this->sendError('Status maste vara godkand eller underkand');
            return;
        }

        $bedomdAv = $this->currentUserName();

        try {
            $stmt = $this->pdo->prepare("
                UPDATE kvalitetscertifikat
                SET status = :status,
                    kommentar = :kommentar,
                    bedomd_av = :bedomd_av,
                    bedomd_datum = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':id'        => $id,
                ':status'    => $status,
                ':kommentar' => $kommentar,
                ':bedomd_av' => $bedomdAv,
            ]);

            if ($stmt->rowCount() === 0) {
                $this->sendError('Certifikat hittades inte', 404);
                return;
            }

            $this->sendSuccess([
                'message' => 'Certifikat bedomt som ' . ($status === 'godkand' ? 'godkant' : 'underkant'),
            ]);
        } catch (\PDOException $e) {
            error_log('KvalitetscertifikatController::bedom: ' . $e->getMessage());
            $this->sendError('Kunde inte bedoma certifikat', 500);
        }
    }

    // =========================================================================
    // GET run=kriterier
    // =========================================================================

    private function getKriterier(): void {
        try {
            $rows = $this->pdo->query(
                "SELECT * FROM kvalitetskriterier ORDER BY vikt DESC, id ASC"
            )->fetchAll(\PDO::FETCH_ASSOC);

            $this->sendSuccess(['data' => $rows]);
        } catch (\Exception $e) {
            error_log('KvalitetscertifikatController::getKriterier: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta kriterier', 500);
        }
    }

    // =========================================================================
    // POST run=uppdatera-kriterier
    // =========================================================================

    private function uppdateraKriterier(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        if (!is_array($data) || empty($data)) {
            $this->sendError('Ogiltig data');
            return;
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                UPDATE kvalitetskriterier
                SET namn = :namn, beskrivning = :beskrivning,
                    min_varde = :min_varde, max_varde = :max_varde,
                    vikt = :vikt, aktiv = :aktiv
                WHERE id = :id
            ");

            $updated = 0;
            foreach ($data as $item) {
                $id = isset($item['id']) ? (int)$item['id'] : 0;
                if ($id <= 0) continue;

                $stmt->execute([
                    ':id'          => $id,
                    ':namn'        => trim($item['namn'] ?? ''),
                    ':beskrivning' => trim($item['beskrivning'] ?? ''),
                    ':min_varde'   => isset($item['min_varde']) ? (float)$item['min_varde'] : null,
                    ':max_varde'   => isset($item['max_varde']) ? (float)$item['max_varde'] : null,
                    ':vikt'        => max(0, (float)($item['vikt'] ?? 1)),
                    ':aktiv'       => !empty($item['aktiv']) ? 1 : 0,
                ]);
                $updated++;
            }

            $this->pdo->commit();

            $this->sendSuccess([
                'message' => "Kriterier uppdaterade ({$updated} st)",
                'updated' => $updated,
            ]);
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('KvalitetscertifikatController::uppdateraKriterier: ' . $e->getMessage());
            $this->sendError('Kunde inte uppdatera kriterier', 500);
        }
    }

    // =========================================================================
    // GET run=statistik
    // =========================================================================

    private function getStatistik(): void {
        try {
            $limit = isset($_GET['limit']) ? min(100, max(5, (int)$_GET['limit'])) : 30;

            $stmt = $this->pdo->prepare("
                SELECT id, batch_nummer, datum, kvalitetspoang, status, operator_namn
                FROM kvalitetscertifikat
                WHERE kvalitetspoang > 0
                ORDER BY datum DESC, id DESC
                LIMIT :lim
            ");
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Reversa sa aldre forst (for diagram)
            $rows = array_reverse($rows);

            $this->sendSuccess(['data' => $rows]);
        } catch (\Exception $e) {
            error_log('KvalitetscertifikatController::getStatistik: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta statistik', 500);
        }
    }
}

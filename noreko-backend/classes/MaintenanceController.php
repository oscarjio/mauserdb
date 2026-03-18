<?php
/**
 * MaintenanceController.php
 * Hanterar underhållslogg — planerat/akut underhåll, reparationer, driftstopp.
 *
 * Endpoints (alla kräver admin-session):
 * GET  ?action=maintenance&run=list            → Lista poster (filter: line, status, from_date)
 * POST ?action=maintenance&run=add             → Lägg till post
 * POST ?action=maintenance&run=update&id=X     → Uppdatera post
 * POST ?action=maintenance&run=delete&id=X     → Soft-delete (status=avbokat)
 * GET  ?action=maintenance&run=stats           → KPI-statistik senaste 30 dagar
 * GET  ?action=maintenance&run=equipment-list  → Hämta aktiva utrustningar
 * GET  ?action=maintenance&run=equipment-stats → Statistik per utrustning (90 dagar)
 * GET  ?action=maintenance&run=service-intervals → Alla serviceintervall med aktuell status
 * POST ?action=maintenance&run=set-service-interval → Skapa/uppdatera serviceintervall (admin)
 * POST ?action=maintenance&run=reset-service-counter → Nollställ räknare efter utförd service (admin)
 */
class MaintenanceController {
    private $pdo;

    private const VALID_LINES = ['rebotling', 'tvattlinje', 'saglinje', 'klassificeringslinje', 'allmant'];
    private const VALID_TYPES = ['planerat', 'akut', 'inspektion', 'kalibrering', 'rengoring', 'ovrigt'];
    private const VALID_STATUSES = ['planerat', 'pagaende', 'klart', 'avbokat'];

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (session_status() === PHP_SESSION_NONE) {
            if ($method === 'POST') {
                session_start();
            } else {
                session_start(['read_and_close' => true]);
            }
        }

        if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Åtkomst nekad'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $run = trim($_GET['run'] ?? 'list');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        match ($run) {
            'list'             => $this->listEntries(),
            'add'              => $method === 'POST' ? $this->addEntry() : $this->sendError('POST krävs', 405),
            'update'           => $method === 'POST' ? $this->updateEntry() : $this->sendError('POST krävs', 405),
            'delete'           => $method === 'POST' ? $this->deleteEntry() : $this->sendError('POST krävs', 405),
            'stats'            => $this->getStats(),
            'equipment-list'   => $this->getEquipmentList(),
            'equipment-stats'  => $this->getEquipmentStats(),
            'mttr-mtbf'              => $this->getMttrMtbf(),
            'service-intervals'      => $this->getServiceIntervals(),
            'set-service-interval'   => $method === 'POST' ? $this->setServiceInterval() : $this->sendError('POST krävs', 405),
            'reset-service-counter'  => $method === 'POST' ? $this->resetServiceCounter() : $this->sendError('POST krävs', 405),
            default                  => $this->sendError('Okänd metod', 400)
        };
    }

    private function listEntries(): void {
        try {
            $line     = isset($_GET['line']) ? trim($_GET['line']) : null;
            $status   = isset($_GET['status']) ? trim($_GET['status']) : null;
            $fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : null;

            // Validera filter-värden
            if ($line !== null && !in_array($line, self::VALID_LINES, true)) {
                $line = null;
            }
            if ($status !== null && !in_array($status, self::VALID_STATUSES, true)) {
                $status = null;
            }
            if ($fromDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
                $fromDate = null;
            }

            // Standardperiod: senaste 90 dagar om inget from_date anges
            if ($fromDate === null) {
                $fromDate = date('Y-m-d', strtotime('-90 days'));
            }

            $conditions = ['start_time >= :from_date'];
            $params = [':from_date' => $fromDate . ' 00:00:00'];

            if ($line !== null) {
                $conditions[] = 'line = :line';
                $params[':line'] = $line;
            }
            if ($status !== null) {
                $conditions[] = 'status = :status';
                $params[':status'] = $status;
            }

            $where = implode(' AND ', $conditions);

            $sql = "SELECT id, line, maintenance_type, title, description, start_time,
                           duration_minutes, performed_by, cost_sek, status, created_by, created_at,
                           equipment, downtime_minutes, resolved
                    FROM maintenance_log
                    WHERE {$where}
                    ORDER BY start_time DESC
                    LIMIT 100";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Räkna totalt utan LIMIT (för display)
            $countSql = "SELECT COUNT(*) FROM maintenance_log WHERE {$where}";
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            echo json_encode([
                'success'     => true,
                'entries'     => $entries,
                'total_count' => $total
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('MaintenanceController::listEntries: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta underhållsposter', 500);
        }
    }

    private function addEntry(): void {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            $title           = strip_tags(trim($data['title'] ?? ''));
            $line            = $data['line'] ?? 'rebotling';
            $maintenanceType = $data['maintenance_type'] ?? 'ovrigt';
            $description     = strip_tags(trim($data['description'] ?? ''));
            $startTime       = $data['start_time'] ?? '';
            $durationMinutes = isset($data['duration_minutes']) && $data['duration_minutes'] !== '' && $data['duration_minutes'] !== null
                               ? intval($data['duration_minutes']) : null;
            $performedBy     = strip_tags(trim($data['performed_by'] ?? ''));
            $costSek         = isset($data['cost_sek']) && $data['cost_sek'] !== '' && $data['cost_sek'] !== null
                               ? floatval($data['cost_sek']) : null;
            $status          = $data['status'] ?? 'klart';
            $createdBy       = intval($_SESSION['user_id']);

            // Nya fält
            $equipment       = isset($data['equipment']) && $data['equipment'] !== '' ? strip_tags(trim($data['equipment'])) : null;
            if ($equipment !== null && mb_strlen($equipment) > 100) {
                $equipment = mb_substr($equipment, 0, 100);
            }
            $downtimeMinutes = isset($data['downtime_minutes']) && $data['downtime_minutes'] !== '' && $data['downtime_minutes'] !== null
                               ? intval($data['downtime_minutes']) : 0;
            $resolved        = isset($data['resolved']) ? ($data['resolved'] ? 1 : 0) : 0;

            // Validering
            if (empty($title)) {
                $this->sendError('Titel krävs', 400);
                return;
            }
            if (mb_strlen($title) > 150) {
                $this->sendError('Titel för lång (max 150 tecken)', 400);
                return;
            }
            if (!in_array($line, self::VALID_LINES, true)) {
                $this->sendError('Ogiltig linje', 400);
                return;
            }
            if (!in_array($maintenanceType, self::VALID_TYPES, true)) {
                $this->sendError('Ogiltig typ', 400);
                return;
            }
            if (!in_array($status, self::VALID_STATUSES, true)) {
                $this->sendError('Ogiltigt status', 400);
                return;
            }
            if (empty($startTime) || !preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $startTime)) {
                $this->sendError('Ogiltig starttid', 400);
                return;
            }
            if ($durationMinutes !== null && ($durationMinutes < 0 || $durationMinutes > 14400)) {
                $this->sendError('Varaktighet måste vara 0–14400 minuter', 400);
                return;
            }
            if ($downtimeMinutes < 0 || $downtimeMinutes > 14400) {
                $this->sendError('Driftstopp måste vara 0–14400 minuter', 400);
                return;
            }
            if ($costSek !== null && ($costSek < 0 || $costSek > 99999999)) {
                $this->sendError('Kostnad måste vara 0–99 999 999 kr', 400);
                return;
            }
            // Begränsa textfält för att undvika VARCHAR/TEXT-overflow
            if (mb_strlen($description) > 2000) {
                $description = mb_substr($description, 0, 2000);
            }
            if (mb_strlen($performedBy) > 100) {
                $performedBy = mb_substr($performedBy, 0, 100);
            }

            // Normalisera datetime (T → mellanslag)
            $startTime = str_replace('T', ' ', substr($startTime, 0, 16)) . ':00';

            $stmt = $this->pdo->prepare("
                INSERT INTO maintenance_log
                    (line, maintenance_type, title, description, start_time, duration_minutes,
                     performed_by, cost_sek, status, created_by, equipment, downtime_minutes, resolved)
                VALUES
                    (:line, :maintenance_type, :title, :description, :start_time, :duration_minutes,
                     :performed_by, :cost_sek, :status, :created_by, :equipment, :downtime_minutes, :resolved)
            ");
            $stmt->execute([
                ':line'             => $line,
                ':maintenance_type' => $maintenanceType,
                ':title'            => $title,
                ':description'      => $description ?: null,
                ':start_time'       => $startTime,
                ':duration_minutes' => $durationMinutes,
                ':performed_by'     => $performedBy ?: null,
                ':cost_sek'         => $costSek,
                ':status'           => $status,
                ':created_by'       => $createdBy,
                ':equipment'        => $equipment,
                ':downtime_minutes' => $downtimeMinutes,
                ':resolved'         => $resolved,
            ]);

            $newId = (int)$this->pdo->lastInsertId();
            echo json_encode([
                'success' => true,
                'message' => 'Underhållspost sparad',
                'id'      => $newId
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('MaintenanceController::addEntry: ' . $e->getMessage());
            $this->sendError('Kunde inte spara underhållspost', 500);
        }
    }

    private function updateEntry(): void {
        try {
            $id = intval($_GET['id'] ?? 0);
            if ($id <= 0) {
                $this->sendError('Ogiltigt ID', 400);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            $fields = [];
            $params = [];

            if (isset($data['title'])) {
                $title = strip_tags(trim($data['title']));
                if (empty($title)) {
                    $this->sendError('Titel krävs', 400);
                    return;
                }
                if (mb_strlen($title) > 150) {
                    $this->sendError('Titel för lång', 400);
                    return;
                }
                $fields[] = 'title = :title';
                $params[':title'] = $title;
            }
            if (isset($data['line'])) {
                if (!in_array($data['line'], self::VALID_LINES, true)) {
                    $this->sendError('Ogiltig linje', 400);
                    return;
                }
                $fields[] = 'line = :line';
                $params[':line'] = $data['line'];
            }
            if (isset($data['maintenance_type'])) {
                if (!in_array($data['maintenance_type'], self::VALID_TYPES, true)) {
                    $this->sendError('Ogiltig typ', 400);
                    return;
                }
                $fields[] = 'maintenance_type = :maintenance_type';
                $params[':maintenance_type'] = $data['maintenance_type'];
            }
            if (array_key_exists('description', $data)) {
                $desc = strip_tags(trim($data['description'])) ?: null;
                if ($desc !== null && mb_strlen($desc) > 2000) {
                    $desc = mb_substr($desc, 0, 2000);
                }
                $fields[] = 'description = :description';
                $params[':description'] = $desc;
            }
            if (isset($data['start_time'])) {
                $st = $data['start_time'];
                if (!preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $st)) {
                    $this->sendError('Ogiltig starttid', 400);
                    return;
                }
                $fields[] = 'start_time = :start_time';
                $params[':start_time'] = str_replace('T', ' ', substr($st, 0, 16)) . ':00';
            }
            if (array_key_exists('duration_minutes', $data)) {
                $dur = $data['duration_minutes'];
                $durVal = ($dur !== null && $dur !== '') ? max(0, min(14400, intval($dur))) : null;
                $fields[] = 'duration_minutes = :duration_minutes';
                $params[':duration_minutes'] = $durVal;
            }
            if (array_key_exists('performed_by', $data)) {
                $pb = strip_tags(trim($data['performed_by'])) ?: null;
                if ($pb !== null && mb_strlen($pb) > 100) {
                    $pb = mb_substr($pb, 0, 100);
                }
                $fields[] = 'performed_by = :performed_by';
                $params[':performed_by'] = $pb;
            }
            if (array_key_exists('cost_sek', $data)) {
                $cost = $data['cost_sek'];
                $costVal = ($cost !== null && $cost !== '') ? floatval($cost) : null;
                if ($costVal !== null && ($costVal < 0 || $costVal > 99999999)) {
                    $this->sendError('Kostnad måste vara 0–99 999 999 kr', 400);
                    return;
                }
                $fields[] = 'cost_sek = :cost_sek';
                $params[':cost_sek'] = $costVal;
            }
            if (isset($data['status'])) {
                if (!in_array($data['status'], self::VALID_STATUSES, true)) {
                    $this->sendError('Ogiltigt status', 400);
                    return;
                }
                $fields[] = 'status = :status';
                $params[':status'] = $data['status'];
            }
            // Nya fält
            if (array_key_exists('equipment', $data)) {
                $eq = $data['equipment'] !== '' ? strip_tags(trim($data['equipment'])) : null;
                if ($eq !== null && mb_strlen($eq) > 100) $eq = mb_substr($eq, 0, 100);
                $fields[] = 'equipment = :equipment';
                $params[':equipment'] = $eq;
            }
            if (array_key_exists('downtime_minutes', $data)) {
                $dt = $data['downtime_minutes'];
                $fields[] = 'downtime_minutes = :downtime_minutes';
                $params[':downtime_minutes'] = ($dt !== null && $dt !== '') ? max(0, min(14400, intval($dt))) : 0;
            }
            if (array_key_exists('resolved', $data)) {
                $fields[] = 'resolved = :resolved';
                $params[':resolved'] = $data['resolved'] ? 1 : 0;
            }

            if (empty($fields)) {
                $this->sendError('Inga fält att uppdatera', 400);
                return;
            }

            $params[':id'] = $id;
            $sql = 'UPDATE maintenance_log SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $this->pdo->prepare($sql)->execute($params);

            echo json_encode(['success' => true, 'message' => 'Underhållspost uppdaterad'], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('MaintenanceController::updateEntry: ' . $e->getMessage());
            $this->sendError('Kunde inte uppdatera underhållspost', 500);
        }
    }

    private function deleteEntry(): void {
        try {
            $id = intval($_GET['id'] ?? 0);
            if ($id <= 0) {
                $this->sendError('Ogiltigt ID', 400);
                return;
            }

            // Soft delete: sätt status till 'avbokat' för att bevara historik
            $stmt = $this->pdo->prepare("UPDATE maintenance_log SET status = 'avbokat' WHERE id = :id");
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() === 0) {
                $this->sendError('Post hittades inte', 404);
                return;
            }

            echo json_encode(['success' => true, 'message' => 'Underhållspost borttagen'], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('MaintenanceController::deleteEntry: ' . $e->getMessage());
            $this->sendError('Kunde inte ta bort underhållspost', 500);
        }
    }

    private function getStats(): void {
        try {
            $stmt = $this->pdo->query("
                SELECT
                    COUNT(*) AS total_events,
                    COALESCE(SUM(duration_minutes), 0) AS total_minutes,
                    COALESCE(SUM(cost_sek), 0) AS total_cost,
                    COUNT(CASE WHEN maintenance_type = 'akut' THEN 1 END) AS akut_count,
                    COUNT(CASE WHEN status = 'pagaende' THEN 1 END) AS pagaende_count
                FROM maintenance_log
                WHERE start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  AND status != 'avbokat'
            ");
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'stats'   => $stats
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('MaintenanceController::getStats: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta statistik', 500);
        }
    }

    private function getEquipmentList(): void {
        try {
            $stmt = $this->pdo->query("
                SELECT id, namn, kategori, linje
                FROM maintenance_equipment
                WHERE aktiv = 1
                ORDER BY kategori, namn
            ");
            $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success'   => true,
                'equipment' => $equipment
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('MaintenanceController::getEquipmentList: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta utrustningslista', 500);
        }
    }

    private function getEquipmentStats(): void {
        try {
            $stmt = $this->pdo->query("
                SELECT
                    e.namn,
                    e.kategori,
                    COUNT(m.id) AS antal_handelser,
                    COALESCE(SUM(m.downtime_minutes), 0) AS total_driftstopp_min,
                    COALESCE(AVG(CASE WHEN m.downtime_minutes > 0 THEN m.downtime_minutes END), 0) AS snitt_driftstopp_min,
                    COALESCE(SUM(m.cost_sek), 0) AS total_kostnad,
                    MAX(m.created_at) AS senaste_handelse
                FROM maintenance_equipment e
                LEFT JOIN maintenance_log m
                    ON m.equipment = e.namn
                    AND m.deleted_at IS NULL
                    AND m.status != 'avbokat'
                    AND m.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                WHERE e.aktiv = 1
                GROUP BY e.id, e.namn, e.kategori
                ORDER BY total_driftstopp_min DESC
            ");
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Totalsummor
            $totalDowntime = 0;
            $totalCost = 0;
            $worstEquipment = null;
            $worstMinutes = 0;

            foreach ($stats as $row) {
                $totalDowntime += (int)$row['total_driftstopp_min'];
                $totalCost += (float)$row['total_kostnad'];
                if ((int)$row['total_driftstopp_min'] > $worstMinutes) {
                    $worstMinutes = (int)$row['total_driftstopp_min'];
                    $worstEquipment = $row['namn'];
                }
            }

            echo json_encode([
                'success'           => true,
                'stats'             => $stats,
                'summary' => [
                    'total_downtime_min' => $totalDowntime,
                    'total_cost'         => $totalCost,
                    'worst_equipment'    => $worstEquipment
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('MaintenanceController::getEquipmentStats: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta utrustningsstatistik', 500);
        }
    }

    private function getMttrMtbf(): void {
        try {
            $days = intval($_GET['days'] ?? 90);
            // Tillåt bara giltiga intervall
            if (!in_array($days, [30, 90, 180, 365], true)) {
                $days = 90;
            }

            // MTTR = snitt reparationstid (downtime_minutes / 60 → timmar) per utrustning
            // MTBF = dagar mellan felinträffanden: DATEDIFF(MAX, MIN) / (COUNT-1)
            // Använder start_time som tidsstämpel för händelsen
            $stmt = $this->pdo->prepare("
                SELECT
                    equipment,
                    COUNT(*) AS antal_fel,
                    ROUND(SUM(downtime_minutes) / 60.0, 1) AS total_stillestand_h,
                    ROUND(AVG(downtime_minutes) / 60.0, 1) AS avg_mttr_h,
                    ROUND(
                        DATEDIFF(MAX(start_time), MIN(start_time)) / NULLIF(COUNT(*) - 1, 0),
                        1
                    ) AS avg_mtbf_dagar
                FROM maintenance_log
                WHERE start_time >= DATE_SUB(NOW(), INTERVAL :days DAY)
                  AND status != 'avbokat'
                  AND equipment IS NOT NULL
                  AND equipment != ''
                GROUP BY equipment
                ORDER BY antal_fel DESC
            ");
            $stmt->execute([':days' => $days]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Typkonvertering
            foreach ($rows as &$row) {
                $row['antal_fel']           = (int)$row['antal_fel'];
                $row['total_stillestand_h'] = (float)$row['total_stillestand_h'];
                $row['avg_mttr_h']          = (float)$row['avg_mttr_h'];
                $row['avg_mtbf_dagar']      = $row['avg_mtbf_dagar'] !== null ? (float)$row['avg_mtbf_dagar'] : null;
            }
            unset($row);

            echo json_encode([
                'success' => true,
                'days'    => $days,
                'kpis'    => $rows
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('MaintenanceController::getMttrMtbf: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta MTTR/MTBF-data', 500);
        }
    }

    // ---- Serviceintervall (prediktivt underhåll) ----

    private function getServiceIntervals(): void {
        try {
            $stmt = $this->pdo->query("
                SELECT id, maskin_namn, intervall_ibc, senaste_service_datum, senaste_service_ibc, skapad, uppdaterad
                FROM service_intervals
                ORDER BY maskin_namn
            ");
            $intervals = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Hämta nuvarande total IBC (max ibc_ok från rebotling_ibc)
            $ibcStmt = $this->pdo->query("SELECT COALESCE(MAX(ibc_ok), 0) AS total_ibc FROM rebotling_ibc");
            $totalIbc = (int)$ibcStmt->fetchColumn();

            // Beräkna status för varje intervall
            $countStmt = $this->pdo->prepare("
                SELECT COALESCE(MAX(ibc_ok), 0) AS ibc_now FROM rebotling_ibc WHERE datum >= :datum
            ");
            foreach ($intervals as &$row) {
                $row['id'] = (int)$row['id'];
                $row['intervall_ibc'] = (int)$row['intervall_ibc'];
                $row['senaste_service_ibc'] = (int)$row['senaste_service_ibc'];

                // Räkna IBC sedan senaste service
                if ($row['senaste_service_datum']) {
                    $countStmt->execute([':datum' => $row['senaste_service_datum']]);
                    $ibcSinceService = (int)$countStmt->fetchColumn();
                } else {
                    $ibcSinceService = $totalIbc - $row['senaste_service_ibc'];
                }

                $intervall = $row['intervall_ibc'];
                $kvar = max(0, $intervall - $ibcSinceService);
                $procentKvar = $intervall > 0
                    ? round((($intervall - $ibcSinceService) / $intervall) * 100, 1)
                    : 0;
                $procentKvar = max(0, min(100, $procentKvar));

                $row['ibc_sedan_service'] = $ibcSinceService;
                $row['kvar'] = $kvar;
                $row['procent_kvar'] = $procentKvar;

                if ($procentKvar > 25) {
                    $row['status'] = 'ok';
                } elseif ($procentKvar > 10) {
                    $row['status'] = 'varning';
                } else {
                    $row['status'] = 'kritisk';
                }
            }
            unset($row);

            echo json_encode([
                'success'   => true,
                'intervals' => $intervals,
                'total_ibc' => $totalIbc
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('MaintenanceController::getServiceIntervals: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta serviceintervall', 500);
        }
    }

    private function setServiceInterval(): void {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            $maskinNamn       = strip_tags(trim($data['maskin_namn'] ?? ''));
            $intervallIbc     = isset($data['intervall_ibc']) ? intval($data['intervall_ibc']) : 0;
            $senasteDatum     = $data['senaste_service_datum'] ?? null;
            $senasteIbc       = isset($data['senaste_service_ibc']) ? intval($data['senaste_service_ibc']) : 0;
            $id               = isset($data['id']) ? intval($data['id']) : 0;

            if (empty($maskinNamn) || mb_strlen($maskinNamn) > 100) {
                $this->sendError('Maskinnamn krävs (max 100 tecken)', 400);
                return;
            }
            if ($intervallIbc <= 0) {
                $this->sendError('Intervall måste vara > 0', 400);
                return;
            }
            if ($senasteDatum !== null && !preg_match('/^\d{4}-\d{2}-\d{2}/', $senasteDatum)) {
                $senasteDatum = null;
            }
            if ($senasteDatum) {
                $senasteDatum = substr(str_replace('T', ' ', $senasteDatum), 0, 19);
            }

            if ($id > 0) {
                // Uppdatera befintligt
                $stmt = $this->pdo->prepare("
                    UPDATE service_intervals
                    SET maskin_namn = :maskin_namn, intervall_ibc = :intervall_ibc,
                        senaste_service_datum = :senaste_datum, senaste_service_ibc = :senaste_ibc
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':maskin_namn'    => $maskinNamn,
                    ':intervall_ibc'  => $intervallIbc,
                    ':senaste_datum'  => $senasteDatum,
                    ':senaste_ibc'    => $senasteIbc,
                    ':id'             => $id
                ]);
            } else {
                // Skapa ny
                $stmt = $this->pdo->prepare("
                    INSERT INTO service_intervals (maskin_namn, intervall_ibc, senaste_service_datum, senaste_service_ibc)
                    VALUES (:maskin_namn, :intervall_ibc, :senaste_datum, :senaste_ibc)
                ");
                $stmt->execute([
                    ':maskin_namn'    => $maskinNamn,
                    ':intervall_ibc'  => $intervallIbc,
                    ':senaste_datum'  => $senasteDatum,
                    ':senaste_ibc'    => $senasteIbc
                ]);
                $id = (int)$this->pdo->lastInsertId();
            }

            echo json_encode([
                'success' => true,
                'message' => 'Serviceintervall sparat',
                'id'      => $id
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('MaintenanceController::setServiceInterval: ' . $e->getMessage());
            $this->sendError('Kunde inte spara serviceintervall', 500);
        }
    }

    private function resetServiceCounter(): void {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $id = intval($data['id'] ?? 0);

            if ($id <= 0) {
                $this->sendError('Ogiltigt ID', 400);
                return;
            }

            $this->pdo->beginTransaction();

            // Hämta aktuell total IBC
            $ibcStmt = $this->pdo->query("SELECT COALESCE(MAX(ibc_ok), 0) FROM rebotling_ibc");
            $currentIbc = (int)$ibcStmt->fetchColumn();

            $stmt = $this->pdo->prepare("
                UPDATE service_intervals
                SET senaste_service_datum = NOW(), senaste_service_ibc = :ibc
                WHERE id = :id
            ");
            $stmt->execute([
                ':ibc' => $currentIbc,
                ':id'  => $id
            ]);

            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();
                $this->sendError('Serviceintervall hittades inte', 404);
                return;
            }

            $this->pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Serviceräknare nollställd'
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('MaintenanceController::resetServiceCounter: ' . $e->getMessage());
            $this->sendError('Kunde inte nollställa serviceräknare', 500);
        }
    }

    private function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    }
}

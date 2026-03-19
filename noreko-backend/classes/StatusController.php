<?php
require_once __DIR__ . '/AuthHelper.php';

class StatusController {
    public function handle() {
        $run = trim($_GET['run'] ?? '');

        // ============================================================
        // GET ?action=status&run=all-lines
        // Publik endpoint — ingen session-check.
        // Returnerar live-status for alla 4 produktionslinjer.
        // ============================================================
        if ($run === 'all-lines') {
            $this->getAllLinesStatus();
            return;
        }

        // --- Default: session-status (inloggad/ej inloggad) ---

        // Oppna sessionen i read_and_close-lage sa att session-fillaset
        // slapps omedelbart efter lasning. Hindrar polling-requests pa foregaende
        // sida fran att blockera den nya status-checken vid sidomladdning.
        if (session_status() === PHP_SESSION_NONE) {
            session_start(['read_and_close' => true]);
        }

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => true, 'loggedIn' => false], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Kontrollera session-timeout (inaktivitet) via AuthHelper-konstanten.
        // Sessionen öppnades med read_and_close ovan, så vi kan inte skriva last_activity här.
        // Istället kollar vi bara — om den har gått ut, förstör sessionen och returnera utloggad.
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > AuthHelper::SESSION_TIMEOUT) {
            // Sessionen har gått ut — öppna den igen i skrivbart läge för att förstöra den
            session_start();
            session_unset();
            session_destroy();
            echo json_encode(['success' => true, 'loggedIn' => false], JSON_UNESCAPED_UNICODE);
            return;
        }

        $userId = (int)$_SESSION['user_id'];
        // Session-laset ar nu fritt -- gor DB-fragan utan att blockera andra requests.

        try {
            global $pdo;
            $stmt = $pdo->prepare("SELECT username, email, admin, operator_id, role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                // Anvandaren borttagen ur DB -- forstor sessionen
                session_start();
                session_unset();
                session_destroy();
                echo json_encode(['success' => true, 'loggedIn' => false], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Använd role-kolumnen om den finns, annars fallback till admin-kolumnen
            $role = $user['role'] ?? (((int)$user['admin'] === 1) ? 'admin' : 'user');

            echo json_encode([
                'success' => true,
                'loggedIn' => true,
                'user' => [
                    'id' => $userId,
                    'username' => $user['username'],
                    'email' => $user['email'] ?? null,
                    'role' => $role,
                    'operator_id' => $user['operator_id'] ? (int)$user['operator_id'] : null
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('StatusController::handle: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte kontrollera session'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ============================================================
    // Publik endpoint: alla produktionslinjers status
    // Rebotling: hamtar senaste data fran rebotling_ibc
    //   - < 15 min => running, 15-60 min => idle, > 60 min => offline
    // Tvattlinje, Saglinje, Klassificeringslinje: statiskt "not_started"
    // ============================================================
    private function getAllLinesStatus(): void {
        try {
            global $pdo;
            $lines = [];

            // --- Rebotling ---
            try {
                $tz  = new DateTimeZone('Europe/Stockholm');
                $now = new DateTime('now', $tz);

                // Senaste raden i rebotling_ibc
                $stmt = $pdo->query(
                    "SELECT datum FROM rebotling_ibc ORDER BY datum DESC LIMIT 1"
                );
                $latestRow = $stmt->fetch(PDO::FETCH_ASSOC);

                $senaste_data_min = null;
                $status = 'offline';
                $statusLabel = 'Offline';
                if ($latestRow && !empty($latestRow['datum'])) {
                    $latestDt = new DateTime($latestRow['datum'], $tz);
                    $diffSec  = $now->getTimestamp() - $latestDt->getTimestamp();
                    $senaste_data_min = round($diffSec / 60, 1);

                    if ($diffSec < 900) {           // < 15 min
                        $status = 'running';
                        $statusLabel = 'Kor';
                    } elseif ($diffSec < 3600) {    // 15-60 min
                        $status = 'idle';
                        $statusLabel = 'Vila';
                    } else {                         // > 60 min
                        $status = 'offline';
                        $statusLabel = 'Offline';
                    }
                }

                // IBC idag
                $ibcIdag = (int)$pdo->query(
                    "SELECT COUNT(*) FROM rebotling_ibc WHERE DATE(datum) = CURDATE()"
                )->fetchColumn();

                // OEE idag
                $oeePct = null;
                try {
                    $oeeRow = $pdo->query(
                        "SELECT
                            SUM(max_ibc_ok)      AS ibc_ok,
                            SUM(max_runtime_plc) AS runtime,
                            SUM(max_rasttime)    AS rasttime
                         FROM (
                            SELECT
                                skiftraknare,
                                MAX(ibc_ok)      AS max_ibc_ok,
                                MAX(runtime_plc) AS max_runtime_plc,
                                MAX(rasttime)    AS max_rasttime
                            FROM rebotling_ibc
                            WHERE DATE(datum) = CURDATE()
                            GROUP BY skiftraknare
                         ) agg"
                    )->fetch(PDO::FETCH_ASSOC);

                    if ($oeeRow && $oeeRow['runtime'] > 0) {
                        $ibcOk      = (float)$oeeRow['ibc_ok'];
                        $runtimeSek = (float)$oeeRow['runtime'];
                        $rastSek    = (float)$oeeRow['rasttime'];
                        $prodTid    = $runtimeSek - $rastSek;
                        if ($prodTid > 0) {
                            $snittCykel = 60;
                            try {
                                $cRow = $pdo->query(
                                    "SELECT AVG(cykel_tid) FROM rebotling_ibc WHERE DATE(datum) = CURDATE() AND cykel_tid > 0"
                                )->fetchColumn();
                                if ($cRow > 0) $snittCykel = (float)$cRow;
                            } catch (Exception $e) { error_log('StatusController::getAllLinesStatus snittCykel: ' . $e->getMessage()); }
                            $maxMojlig = $prodTid / $snittCykel;
                            if ($maxMojlig > 0) {
                                $oeePct = round(($ibcOk / $maxMojlig) * 100, 1);
                            }
                        }
                    }
                } catch (Exception $e) { error_log('StatusController::getAllLinesStatus OEE: ' . $e->getMessage()); }

                $lines[] = [
                    'id'              => 'rebotling',
                    'namn'            => 'Rebotling',
                    'status'          => $status,
                    'status_label'    => $statusLabel,
                    'kor'             => ($status === 'running'),
                    'senaste_data_min'=> $senaste_data_min,
                    'ibc_idag'        => $ibcIdag,
                    'oee_pct'         => $oeePct,
                    'ej_i_drift'      => false
                ];
            } catch (Exception $e) {
                error_log('StatusController::all-lines rebotling: ' . $e->getMessage());
                $lines[] = [
                    'id'           => 'rebotling',
                    'namn'         => 'Rebotling',
                    'status'       => 'offline',
                    'status_label' => 'Offline',
                    'kor'          => false,
                    'ej_i_drift'   => false
                ];
            }

            // --- Tvattlinje ---
            $lines[] = [
                'id'           => 'tvattlinje',
                'namn'         => 'Tvattlinje',
                'status'       => 'not_started',
                'status_label' => 'Ej igang',
                'kor'          => false,
                'ej_i_drift'   => true
            ];

            // --- Saglinje ---
            $lines[] = [
                'id'           => 'saglinje',
                'namn'         => 'Saglinje',
                'status'       => 'not_started',
                'status_label' => 'Ej igang',
                'kor'          => false,
                'ej_i_drift'   => true
            ];

            // --- Klassificeringslinje ---
            $lines[] = [
                'id'           => 'klassificeringslinje',
                'namn'         => 'Klassificeringslinje',
                'status'       => 'not_started',
                'status_label' => 'Ej igang',
                'kor'          => false,
                'ej_i_drift'   => true
            ];

            echo json_encode(['success' => true, 'lines' => $lines], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('StatusController::all-lines: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hamta linjestatus'], JSON_UNESCAPED_UNICODE);
        }
    }
}

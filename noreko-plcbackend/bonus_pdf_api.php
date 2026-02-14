<?php
/**
 * Bonus PDF API
 * API endpoint för att generera PDF-rapporter
 *
 * Endpoints:
 * - POST ?operator_id=123&period=2026-02 → Generera PDF
 * - GET  ?download=bonus_report_123_2026-02.pdf → Ladda ner PDF
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/BonusPDFReport.php';
require_once __DIR__ . '/db.php'; // Assuming this file provides $pdo

try {
    $method = $_SERVER['REQUEST_METHOD'];

    // Handle file download
    if ($method === 'GET' && isset($_GET['download'])) {
        $filename = basename($_GET['download']); // Security: basename prevents directory traversal
        $filepath = __DIR__ . '/reports/' . $filename;

        // Validate filename format
        if (!preg_match('/^bonus_report_\d+_\d{4}-\d{2}\.pdf$/', $filename)) {
            throw new Exception('Invalid filename format');
        }

        if (!file_exists($filepath)) {
            http_response_code(404);
            echo json_encode(['error' => true, 'message' => 'File not found']);
            exit;
        }

        // Send file
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }

    // Handle PDF generation
    if ($method === 'POST') {
        // Validate input
        if (!isset($_POST['operator_id']) || !isset($_POST['period'])) {
            throw new Exception('Missing required parameters: operator_id, period');
        }

        $operator_id = filter_var($_POST['operator_id'], FILTER_VALIDATE_INT);
        if ($operator_id === false || $operator_id < 1) {
            throw new Exception('Invalid operator_id (must be positive integer)');
        }

        $period = $_POST['period'];
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new Exception('Invalid period format (expected: YYYY-MM)');
        }

        // Generate PDF
        $report = new BonusPDFReport($pdo);
        $filepath = $report->generateOperatorMonthlyReport($operator_id, $period);

        $filename = basename($filepath);

        echo json_encode([
            'success' => true,
            'message' => 'PDF report generated',
            'filename' => $filename,
            'download_url' => "?download={$filename}",
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);

        exit;
    }

    // Invalid method
    http_response_code(405);
    echo json_encode([
        'error' => true,
        'message' => 'Method not allowed. Use POST to generate or GET to download.'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}

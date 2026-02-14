<?php
/**
 * Bonus Calculator API
 * API endpoint för interaktiv bonuskalkylator
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/BonusCalculator.php';

try {
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests are allowed');
    }

    // Läs JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Validate JSON decode
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }

    if (!$data || !is_array($data)) {
        throw new Exception('Invalid JSON input - expected object');
    }

    // Validate required fields
    $required = ['ibc_ok', 'ibc_ej_ok', 'bur_ej_ok', 'runtime_plc'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Missing required field: {$field}");
        }
    }

    // Validate and sanitize input
    $ibc_ok = filter_var($data['ibc_ok'], FILTER_VALIDATE_INT);
    $ibc_ej_ok = filter_var($data['ibc_ej_ok'], FILTER_VALIDATE_INT);
    $bur_ej_ok = filter_var($data['bur_ej_ok'], FILTER_VALIDATE_INT);
    $runtime_plc = filter_var($data['runtime_plc'], FILTER_VALIDATE_INT);

    if ($ibc_ok === false || $ibc_ej_ok === false ||
        $bur_ej_ok === false || $runtime_plc === false) {
        throw new Exception('All input values must be integers');
    }

    // Validate ranges
    if ($ibc_ok < 0 || $ibc_ok > 1000) {
        throw new Exception('ibc_ok must be between 0 and 1000');
    }
    if ($ibc_ej_ok < 0 || $ibc_ej_ok > 500) {
        throw new Exception('ibc_ej_ok must be between 0 and 500');
    }
    if ($bur_ej_ok < 0 || $bur_ej_ok > 200) {
        throw new Exception('bur_ej_ok must be between 0 and 200');
    }
    if ($runtime_plc < 1 || $runtime_plc > 960) {
        throw new Exception('runtime_plc must be between 1 and 960 minutes');
    }

    // Validate product ID
    $produkt = filter_var($data['produkt'] ?? 1, FILTER_VALIDATE_INT);
    if (!in_array($produkt, [1, 4, 5], true)) {
        throw new Exception('Invalid product ID (must be 1, 4, or 5)');
    }

    $calculator = new BonusCalculator();

    // Prepare validated data
    $validated_data = [
        'ibc_ok' => $ibc_ok,
        'ibc_ej_ok' => $ibc_ej_ok,
        'bur_ej_ok' => $bur_ej_ok,
        'runtime_plc' => $runtime_plc
    ];

    // Check if comparison mode
    if (isset($data['compare']) && $data['compare'] === true) {
        $result = $calculator->compareFormulas($validated_data, $produkt);
        echo json_encode($result, JSON_PRETTY_PRINT);
        exit;
    }

    // Validate and sanitize optional parameters
    $team_multiplier = filter_var(
        $data['team_multiplier'] ?? 1.0,
        FILTER_VALIDATE_FLOAT
    );
    $safety_factor = filter_var(
        $data['safety_factor'] ?? 1.0,
        FILTER_VALIDATE_FLOAT
    );
    $mentorship_bonus = filter_var(
        $data['mentorship_bonus'] ?? 0,
        FILTER_VALIDATE_FLOAT
    );

    // Validate multiplier ranges
    if ($team_multiplier < 1.0 || $team_multiplier > 2.0) {
        throw new Exception('team_multiplier must be between 1.0 and 2.0');
    }
    if ($safety_factor < 1.0 || $safety_factor > 2.0) {
        throw new Exception('safety_factor must be between 1.0 and 2.0');
    }
    if ($mentorship_bonus < 0 || $mentorship_bonus > 50) {
        throw new Exception('mentorship_bonus must be between 0 and 50');
    }

    $options = [
        'team_multiplier' => $team_multiplier,
        'safety_factor' => $safety_factor,
        'mentorship_bonus' => $mentorship_bonus
    ];

    $result = $calculator->calculateAdvancedKPIs($validated_data, $produkt, $options);

    echo json_encode($result, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}

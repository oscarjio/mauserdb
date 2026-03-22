<?php
/**
 * Cron script för att uppdatera väderdata varje timme
 * Anropas via: wget http://localhost/noreko-backend/update-weather.php
 */

// Säkerställ konsekvent timezone — samma som api.php.
// Utan detta kan date() returnera fel tid beroende på serverns php.ini-inställning.
date_default_timezone_set('Europe/Stockholm');

// Databasanslutning via db_config.php (inga hårdkodade credentials)
$dbConfig = __DIR__ . '/db_config.php';
if (!file_exists($dbConfig)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Databaskonfiguration saknas'], JSON_UNESCAPED_UNICODE);
    exit;
}
$db = require $dbConfig;
try {
    $pdo = new PDO($db['dsn'], $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (\Throwable $e) {
    error_log('[update-weather] Databasanslutning misslyckades: ' . get_class($e) . ': ' . $e->getMessage());
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Databasanslutning misslyckades'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
$isHttpsWeather = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                  (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
if ($isHttpsWeather) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
header_remove('X-Powered-By');

// API URL för väderdata
$apiUrl = 'https://api.open-meteo.com/v1/forecast?latitude=57.96&longitude=12.12&current=temperature_2m&temperature_unit=celsius&timezone=Europe/Stockholm';

try {
    // Hämta data från API
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'method' => 'GET',
            'header' => 'User-Agent: Noreko/1.0'
        ]
    ]);
    
    $response = @file_get_contents($apiUrl, false, $context);
    
    if ($response === false) {
        throw new Exception('Kunde inte hämta data från väder-API');
    }
    
    $data = json_decode($response, true);
    
    if (!$data || !isset($data['current']['temperature_2m'])) {
        throw new Exception('Ogiltigt svar från väder-API');
    }
    
    $temperature = (float)$data['current']['temperature_2m'];
    
    // Spara till databas
    $stmt = $pdo->prepare('
        INSERT INTO vader_data (utetemperatur, datum) 
        VALUES (:utetemperatur, NOW())
    ');
    
    $stmt->execute([
        'utetemperatur' => $temperature
    ]);
    
    // Returnera JSON för eventuell loggning
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => 'Väderdata uppdaterad',
        'temperature' => $temperature,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    
} catch (\Throwable $e) {
    // Logga fel men returnera JSON för cron-loggar
    // Fångar alla feltyper inkl. TypeError/Error — inte bara Exception
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Väderdata kunde inte uppdateras',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);

    // Skriv även till error log
    error_log('[update-weather] Fel: ' . get_class($e) . ': ' . $e->getMessage());
}


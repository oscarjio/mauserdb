<?php
// api.php - Tar emot API-anrop och routar till rätt hantering

// CORS-headers - begränsa till tillåtna domäner
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    'http://localhost:4200',
    'http://localhost',
    'https://localhost',
];
// Lägg till egna domäner via db_config om den finns
$corsConfig = __DIR__ . '/cors_origins.php';
if (file_exists($corsConfig)) {
    $extraOrigins = require $corsConfig;
    if (is_array($extraOrigins)) {
        $allowedOrigins = array_merge($allowedOrigins, $extraOrigins);
    }
}
if ($origin && in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

// Hantera preflight OPTIONS-request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Databasanslutning
global $pdo;
try {
    $dbConfig = __DIR__ . '/db_config.php';
    if (file_exists($dbConfig)) {
        $db = require $dbConfig;
    } else {
        $db = [
            'dsn' => 'mysql:host=localhost:33061;dbname=mauserdb;charset=utf8mb4',
            'user' => 'aiab',
            'pass' => 'Noreko2025'
        ];
    }
    $pdo = new PDO($db['dsn'], $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Databasanslutning misslyckades']);
    exit;
}

// Enkel PSR-4-liknande autoloader
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/classes/' . $class . '.php';
    if (file_exists($file)) require $file;
});

$action = $_GET['action'] ?? '';

// Mapping för actions som inte följer standardnamngivning
$classNameMap = [
    'rebotlingproduct' => 'RebotlingProductController',
    'skiftrapport' => 'SkiftrapportController',
    'vpn' => 'VpnController',
    'bonusadmin' => 'BonusAdminController',
    'stoppage' => 'StoppageController',
    'audit' => 'AuditController'
];
$className = $classNameMap[strtolower($action)] ?? ucfirst(strtolower($action)) . 'Controller';

// Ladda klassen manuellt
$file = __DIR__ . '/classes/' . $className . '.php';
if (file_exists($file)) {
    require_once $file;
}

if (class_exists($className)) {
    try {
        $controller = new $className();
        $controller->handle();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Internt serverfel'
        ]);
        error_log("API Error [{$action}]: " . $e->getMessage());
    }
} else {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Endpoint hittades inte'
    ]);
}

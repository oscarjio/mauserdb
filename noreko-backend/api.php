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
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

// Databasanslutning
global $pdo;
try {
    $dbConfig = __DIR__ . '/db_config.php';
    if (!file_exists($dbConfig)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Databaskonfiguration saknas (db_config.php)']);
        exit;
    }
    $db = require $dbConfig;
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

// Vitlistade actions → controller-klasser
$classNameMap = [
    'rebotling' => 'RebotlingController',
    'rebotlingproduct' => 'RebotlingProductController',
    'tvattlinje' => 'TvattlinjeController',
    'saglinje' => 'SaglinjeController',
    'klassificeringslinje' => 'KlassificeringslinjeController',
    'skiftrapport' => 'SkiftrapportController',
    'login' => 'LoginController',
    'register' => 'RegisterController',
    'profile' => 'ProfileController',
    'admin' => 'AdminController',
    'bonus' => 'BonusController',
    'bonusadmin' => 'BonusAdminController',
    'vpn' => 'VpnController',
    'stoppage' => 'StoppageController',
    'audit' => 'AuditController',
];

$actionKey = strtolower($action);
if (!isset($classNameMap[$actionKey])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Endpoint hittades inte']);
    exit;
}
$className = $classNameMap[$actionKey];

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

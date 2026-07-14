<?php
declare(strict_types=1);

// ============================================================================
// internal-api.php — FAS 1 Pi-aggregering (INTERN API)
// ----------------------------------------------------------------------------
// Körs på Pi:n (eller på dev-VPS via `php -S 127.0.0.1:8091`). Tar emot tunga
// read-actions från VPS-edge (api.php via RemoteAgg), kör controllern lokalt
// nära DB:n och returnerar kompakt JSON. Ingen användar-auth görs här —
// auth ligger kvar på VPS-edge. Denna endpoint skyddas i stället av:
//   (0) loopback-grind (endast 127.0.0.1/::1)
//   (1) delad hemlig token (X-Internal-Token)
// PIAGG_INTERNAL-konstanten hindrar controllers från att proxa vidare (loop).
// ============================================================================

define('PIAGG_INTERNAL', true);

header('Content-Type: application/json; charset=utf-8');

// (0) Loopback-grind — bara anrop från samma maskin släpps in.
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remoteAddr, ['127.0.0.1', '::1', ''], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

// (1) Token — getenv PIAGG_TOKEN, annars internal_token.php. hash_equals mot header.
$token = getenv('PIAGG_TOKEN');
if ($token === false || $token === '') {
    $tokenFile = __DIR__ . '/internal_token.php';
    if (is_file($tokenFile)) {
        $token = require $tokenFile;
    }
}
$provided = $_SERVER['HTTP_X_INTERNAL_TOKEN'] ?? '';
if (!is_string($token) || $token === '' || !hash_equals((string)$token, (string)$provided)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

// (1b) Kodversion — emittera Pi:ns deployade kodversion så VPS-edge (RemoteAgg) kan
// upptäcka om Pi:n kör ANNAN (äldre) kod än edge. Vid mismatch vägrar edge cacha Pi:ns
// svar (annars låstes gamla värden i 7 dygn) och kör lokal HEAD-kod i stället.
// Självläker automatiskt när Pi:n deployas till samma version.
require_once __DIR__ . '/classes/CodeVersion.php';
header('X-Code-Version: ' . CodeVersion::get());

// (2) PDO från db_config.php — samma optioner som api.php, global $pdo.
global $pdo;
try {
    $dbConfig = __DIR__ . '/db_config.php';
    if (!is_file($dbConfig)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Databaskonfiguration saknas (db_config.php)'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $db = require $dbConfig;
    $pdo = new PDO($db['dsn'], $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_COMPRESS => true,
        PDO::ATTR_PERSISTENT => true
    ]);
    try { $pdo->exec("SET time_zone = 'Europe/Stockholm'"); } catch (\Throwable $e) { /* timezone tables ej installerade — ignoreras */ }
} catch (\Throwable $e) {
    require_once __DIR__ . '/classes/ErrorLogger.php';
    ErrorLogger::log($e, 'internal-api: Databasanslutning misslyckades');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Databasanslutning misslyckades'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Autoloader för controller-klasser (samma layout som api.php).
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/classes/' . $class . '.php';
    if (is_file($file)) require $file;
});

// Minimal sessionskontext: vissa controllers (OperatorRanking, OeeTrendanalys,
// TvattlinjeOperator) självkontrollerar $_SESSION['user_id'] och returnerar 401
// annars. Auth är redan gjord på edge; här sätter vi en intern sentinel så att
// controllern kan köra. session_start() FÖRST → controllerns egna
// session_start() hoppas över (status ACTIVE) och sentineln bevaras.
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (empty($_SESSION['user_id'])) { $_SESSION['user_id'] = 'piagg-internal'; }

// (3) Whitelist action -> controller-klass. Allt annat = 404.
$whitelist = [
    'tvattlinje'          => 'TvattlinjeController',
    'tvattlinje-operator' => 'TvattlinjeOperatorController',
    'operator-ranking'    => 'OperatorRankingController',
    'bemanning'           => 'BemanningController',
    'oee-trendanalys'     => 'OeeTrendanalysController',
    'lineskiftrapport'    => 'LineSkiftrapportController',
    'rebotling'           => 'RebotlingController',
];
$action = strtolower(trim($_GET['action'] ?? ''));
if (!isset($whitelist[$action])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Endpoint hittades inte'], JSON_UNESCAPED_UNICODE);
    exit;
}
$className = $whitelist[$action];

// (4) Kort-TTL param-keyad filcache (15s). Nyckeln inkluderar ALLA GET-params.
$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0777, true); }
$cacheFile = $cacheDir . '/agg_' . $action . '_' . CodeVersion::get() . '_' . md5(json_encode($_GET)) . '.json';
if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 15) {
    $cached = file_get_contents($cacheFile);
    if ($cached !== false && $cached !== '') {
        echo $cached;
        exit;
    }
}

// (5) Dispatch med output-capture. Cacha bara giltig JSON (första tecknet '{').
try {
    require_once __DIR__ . '/classes/' . $className . '.php';
    if (!class_exists($className)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Endpoint hittades inte'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    ob_start();
    $controller = new $className();
    $controller->handle();
    $output = ob_get_clean();

    if (isset($output[0]) && $output[0] === '{') {
        @file_put_contents($cacheFile, $output, LOCK_EX);
    }
    echo $output;
} catch (\Throwable $e) {
    if (ob_get_level() > 0) { @ob_end_clean(); }
    require_once __DIR__ . '/classes/ErrorLogger.php';
    ErrorLogger::log($e, "internal-api Error [{$action}]");
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internt serverfel'], JSON_UNESCAPED_UNICODE);
}
